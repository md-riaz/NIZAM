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
        $this->hangup('b-leg-uuid', 'outbound');

        $this->assertSame(1, CallDetailRecord::query()->count(), 'The b leg was recorded as a second call.');
        $this->assertDatabaseHas('call_detail_records', ['uuid' => 'a-leg-uuid']);
        $this->assertDatabaseMissing('call_detail_records', ['uuid' => 'b-leg-uuid']);
    }

    public function test_the_originated_leg_does_not_meter_the_call_twice(): void
    {
        $this->hangup('billed-a', 'inbound', billsec: 120);
        $this->hangup('billed-b', 'outbound', billsec: 120);

        $records = UsageRecord::query()
            ->where('metric', UsageRecord::METRIC_CALL_MINUTES)
            ->get();

        $this->assertCount(1, $records, 'The call was metered once per channel instead of once per call.');
        $this->assertEquals(2.0, (float) $records->first()->value);
    }

    /**
     * A channel with no direction is still recorded.
     *
     * Suppressing anything that is not explicitly inbound would trade a duplicate
     * — which the uuid key makes recoverable — for a missing record, which
     * nothing can recover.
     */
    public function test_a_channel_without_a_direction_is_still_recorded(): void
    {
        $this->hangup('no-direction', null);

        $this->assertDatabaseHas('call_detail_records', ['uuid' => 'no-direction']);
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
