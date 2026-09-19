<?php

namespace Tests\Unit\Services;

use App\Events\CallEvent;
use App\Models\CallDetailRecord;
use App\Models\Organization;
use App\Models\UsageRecord;
use App\Services\EventProcessor;
use App\Services\UsageMeteringService;
use App\Services\WebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * One call detail record per call, not one per channel.
 *
 * `CHANNEL_HANGUP_COMPLETE` fires for every channel, so a bridged call raises it
 * twice with two distinct ids. Both were recorded, which wrote two rows for one
 * conversation and metered its minutes twice — so every internal call was billed
 * double, and the reports counted it twice.
 */
class CdrLegDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create([
            'domain' => 'legs.example.com',
            'is_active' => true,
            'status' => Organization::STATUS_ACTIVE,
        ]);

        Event::fake([CallEvent::class]);
    }

    public function test_the_originated_leg_does_not_create_a_second_record(): void
    {
        $this->hangup('a-leg-uuid', 'inbound');
        $this->hangup('b-leg-uuid', 'outbound', extra: $this->originatedBy('a-leg-uuid'));

        $this->assertSame(1, CallDetailRecord::query()->count(), 'The b leg was recorded as a second call.');
        $this->assertDatabaseHas('call_detail_records', ['uuid' => 'a-leg-uuid']);
        $this->assertDatabaseMissing('call_detail_records', ['uuid' => 'b-leg-uuid']);
    }

    public function test_the_originated_leg_does_not_meter_the_call_twice(): void
    {
        $this->hangup('billed-a', 'inbound', billsec: 120);
        $this->hangup('billed-b', 'outbound', billsec: 120, extra: $this->originatedBy('billed-a'));

        $records = UsageRecord::query()
            ->where('metric', UsageRecord::METRIC_CALL_MINUTES)
            ->get();

        $this->assertCount(1, $records, 'The call was metered once per channel instead of once per call.');
        $this->assertEquals(2.0, (float) $records->first()->value);
    }

    /**
     * A channel with no direction is still recorded.
     */
    public function test_a_channel_without_a_direction_is_still_recorded(): void
    {
        $this->hangup('no-direction', null);

        $this->assertDatabaseHas('call_detail_records', ['uuid' => 'no-direction']);
    }

    /**
     * A call placed through the originate API is still recorded and billed.
     *
     * It has no accepted inbound leg: FreeSWITCH dials out to reach the
     * extension, so the first channel of the call is an outbound one. Deciding
     * by direction skipped every leg, and the call vanished from both reporting
     * and billing — the record could at least be restored from the spool later,
     * the billable minutes could not.
     */
    public function test_an_api_originated_call_is_recorded_and_billed(): void
    {
        // Both legs outbound: the leg dialling the extension, then the leg it
        // bridged to the carrier.
        $this->hangup('originate-a', 'outbound', billsec: 60);
        $this->hangup('originate-b', 'outbound', billsec: 60, extra: $this->originatedBy('originate-a'));

        $this->assertSame(1, CallDetailRecord::query()->count(), 'An originated call produced the wrong number of records.');
        $this->assertDatabaseHas('call_detail_records', ['uuid' => 'originate-a']);
        $this->assertCount(1, UsageRecord::query()->where('metric', UsageRecord::METRIC_CALL_MINUTES)->get());
    }

    /**
     * `originating_leg_uuid` alone is enough to recognise the far end.
     *
     * The two headers are set on the same occasions, but `Other-Type` reports
     * whichever role the channel took most recently, so a channel that both
     * originated and was originated can report the other one.
     */
    public function test_a_leg_naming_its_originator_is_not_recorded_again(): void
    {
        $this->hangup('bridge-a', 'inbound');
        $this->hangup('bridge-b', 'outbound', extra: ['variable_originating_leg_uuid' => 'bridge-a']);

        $this->assertDatabaseMissing('call_detail_records', ['uuid' => 'bridge-b']);
    }

    /**
     * How FreeSWITCH marks a channel another channel brought into being.
     *
     * @return array<string, string>
     */
    private function originatedBy(string $originator): array
    {
        return [
            'Other-Type' => 'originator',
            'Other-Leg-Unique-ID' => $originator,
            'variable_originating_leg_uuid' => $originator,
        ];
    }

    /**
     * The same call arriving twice updates one row rather than failing.
     *
     * The live path inserted blindly, so a replayed event collided with the
     * unique key and was swallowed by the error handler. The spooled copy of the
     * same call has to be able to complete the row either way round.
     */
    public function test_the_same_call_seen_twice_updates_one_record(): void
    {
        $this->hangup('replayed', 'inbound', billsec: 30);
        $this->hangup('replayed', 'inbound', billsec: 45);

        $this->assertSame(1, CallDetailRecord::query()->where('uuid', 'replayed')->count());
        $this->assertSame(45, CallDetailRecord::query()->where('uuid', 'replayed')->value('billsec'));
    }

    /**
     * Direction describes the call, not the channel.
     *
     * Every a-leg is "inbound" as far as FreeSWITCH is concerned, so without the
     * dialplan's own declaration an extension dialling another extension was
     * filed as an inbound call from a carrier.
     */
    public function test_the_dialplan_declaration_decides_the_direction(): void
    {
        $this->hangup('declared-local', 'inbound', extra: ['variable_call_direction' => 'local']);

        $this->assertSame('local', CallDetailRecord::query()->where('uuid', 'declared-local')->value('direction'));
    }

    /**
     * Without a declaration, a caller from outside the organization's own domain
     * is an inbound call — the comparison FusionPBX falls back to as well.
     */
    public function test_a_caller_from_another_domain_is_inbound_without_a_declaration(): void
    {
        $this->hangup('carrier-call', 'inbound', extra: ['variable_sip_from_domain' => 'carrier.example.net']);

        $this->assertSame('inbound', CallDetailRecord::query()->where('uuid', 'carrier-call')->value('direction'));
    }

    /**
     * And a caller inside it is not, however the channel describes itself.
     *
     * This is the case the channel direction got wrong: an extension dialling
     * another extension raises an a-leg FreeSWITCH calls "inbound", and every
     * such call was filed as though a carrier had placed it.
     */
    public function test_a_caller_within_the_domain_is_local_without_a_declaration(): void
    {
        $this->hangup('internal-call', 'inbound', extra: ['variable_sip_from_domain' => 'legs.example.com']);

        $this->assertSame('local', CallDetailRecord::query()->where('uuid', 'internal-call')->value('direction'));
    }

    /**
     * A writer that loses the insert race keeps what it knew.
     *
     * Looking the row up and inserting it are two steps, and the live path and
     * the spool can both look before either inserts. The loser's insert then
     * fails on the unique key. Letting that surface as an error discarded
     * everything that writer carried — for the live path, the whole of the RTP
     * quality and SIP detail the spooled copy never has.
     *
     * The competing row is inserted from inside the save here, which is the one
     * way to land in that window on purpose.
     */
    public function test_a_writer_that_loses_the_insert_race_still_records_what_it_knew(): void
    {
        $organization = $this->organization;
        $inserted = false;

        CallDetailRecord::creating(function (CallDetailRecord $cdr) use ($organization, &$inserted) {
            if ($inserted || $cdr->uuid !== 'raced') {
                return;
            }

            $inserted = true;

            // Another writer gets there first, between the lookup and the insert.
            CallDetailRecord::query()->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'organization_id' => $organization->id,
                'uuid' => 'raced',
                'caller_id_number' => '1001',
                'destination_number' => '1002',
                'direction' => 'inbound',
                'start_stamp' => '2026-09-18 10:00:00',
                'duration' => 0,
                'billsec' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        try {
            $this->hangup('raced', 'inbound', billsec: 99, extra: [
                'variable_rtp_audio_in_mos' => '4.2',
                'variable_sip_user_agent' => 'Yealink',
            ]);
        } finally {
            CallDetailRecord::flushEventListeners();
        }

        $this->assertTrue($inserted, 'The race was never triggered, so this proves nothing.');

        $rows = CallDetailRecord::query()->where('uuid', 'raced')->get();

        $this->assertCount(1, $rows, 'The race produced more than one record.');
        $this->assertSame(99, $rows->first()->billsec, 'The losing writer\'s fields were discarded.');
        $this->assertSame('Yealink', $rows->first()->sip_user_agent);
    }

    /**
     * @param  array<string, string>  $extra
     */
    private function hangup(string $uuid, ?string $channelDirection, int $billsec = 60, array $extra = []): void
    {
        $processor = new EventProcessor(
            $this->createMock(WebhookDispatcher::class),
            new UsageMeteringService,
        );

        $event = array_merge([
            'Event-Name' => 'CHANNEL_HANGUP_COMPLETE',
            'variable_domain_name' => $this->organization->domain,
            'Unique-ID' => $uuid,
            'Caller-Caller-ID-Name' => 'Caller',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Hangup-Cause' => 'NORMAL_CLEARING',
            'variable_duration' => (string) $billsec,
            'variable_billsec' => (string) $billsec,
            'variable_start_stamp' => '2026-09-18 10:00:00',
            'variable_end_stamp' => '2026-09-18 10:01:00',
            'Caller-Context' => 'default',
        ], $extra);

        if ($channelDirection !== null) {
            $event['Call-Direction'] = $channelDirection;
        }

        $processor->process($event);
    }
}
