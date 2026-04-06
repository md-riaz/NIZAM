<?php

namespace Tests\Unit\Services;

use App\Events\CallEvent;
use App\Models\CallDetailRecord;
use App\Models\Tenant;
use App\Models\UsageRecord;
use App\Services\EventProcessor;
use App\Services\UsageMeteringService;
use App\Services\WebhookDispatcher;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CallMinutesMeteringTest extends TestCase
{
    use RefreshDatabase;

    public function test_hangup_event_records_call_minutes(): void
    {
        $tenant = Tenant::factory()->create([
            'domain' => 'meter.example.com',
            'is_active' => true,
            'status' => Tenant::STATUS_ACTIVE,
        ]);

        Event::fake([CallEvent::class]);

        $dispatcher = $this->createMock(WebhookDispatcher::class);
        $metering = new UsageMeteringService;
        $processor = new EventProcessor($dispatcher, $metering);

        $event = [
            'Event-Name' => 'CHANNEL_HANGUP_COMPLETE',
            'variable_domain_name' => 'meter.example.com',
            'Unique-ID' => 'uuid-meter-test',
            'Caller-Caller-ID-Name' => 'Caller',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
            'Hangup-Cause' => 'NORMAL_CLEARING',
            'variable_duration' => '120',
            'variable_billsec' => '90',
            'variable_start_stamp' => '2026-02-28 10:00:00',
            'variable_answer_stamp' => '2026-02-28 10:00:30',
            'variable_end_stamp' => '2026-02-28 10:02:00',
            'Caller-Context' => 'default',
        ];

        $processor->process($event);

        $this->assertDatabaseHas('usage_records', [
            'tenant_id' => $tenant->id,
            'metric' => UsageRecord::METRIC_CALL_MINUTES,
        ]);

        $record = UsageRecord::where('tenant_id', $tenant->id)
            ->where('metric', UsageRecord::METRIC_CALL_MINUTES)
            ->first();

        // 90 billsec / 60 = 1.5 minutes
        $this->assertEquals(1.5, (float) $record->value);
    }

    public function test_hangup_with_zero_billsec_does_not_record_call_minutes(): void
    {
        Tenant::factory()->create([
            'domain' => 'zero.example.com',
            'is_active' => true,
            'status' => Tenant::STATUS_ACTIVE,
        ]);

        Event::fake([CallEvent::class]);

        $dispatcher = $this->createMock(WebhookDispatcher::class);
        $metering = new UsageMeteringService;
        $processor = new EventProcessor($dispatcher, $metering);

        $event = [
            'Event-Name' => 'CHANNEL_HANGUP_COMPLETE',
            'variable_domain_name' => 'zero.example.com',
            'Unique-ID' => 'uuid-zero-test',
            'Caller-Caller-ID-Name' => 'Caller',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
            'Hangup-Cause' => 'NO_ANSWER',
            'variable_duration' => '30',
            'variable_billsec' => '0',
        ];

        $processor->process($event);

        $this->assertDatabaseMissing('usage_records', [
            'metric' => UsageRecord::METRIC_CALL_MINUTES,
        ]);
    }

    public function test_reconcile_call_minutes_matches(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
            'status' => Tenant::STATUS_ACTIVE,
        ]);

        $today = Carbon::today();

        // Create CDR records
        CallDetailRecord::factory()->create([
            'tenant_id' => $tenant->id,
            'billsec' => 120,
            'start_stamp' => $today->copy()->setTime(10, 0),
        ]);
        CallDetailRecord::factory()->create([
            'tenant_id' => $tenant->id,
            'billsec' => 180,
            'start_stamp' => $today->copy()->setTime(11, 0),
        ]);

        // Record matching usage
        $metering = new UsageMeteringService;
        $metering->record($tenant, UsageRecord::METRIC_CALL_MINUTES, 2.0, null, $today);
        $metering->record($tenant, UsageRecord::METRIC_CALL_MINUTES, 3.0, null, $today);

        $result = $metering->reconcileCallMinutes($tenant, $today, $today);

        $this->assertEquals(300, $result['cdr_total_seconds']);
        $this->assertEquals(5.0, $result['cdr_total_minutes']);
        $this->assertEquals(5.0, $result['metered_minutes']);
        $this->assertTrue($result['matched']);
    }

    public function test_reconcile_detects_mismatch(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
            'status' => Tenant::STATUS_ACTIVE,
        ]);

        $today = Carbon::today();

        // Create CDR with 300 seconds = 5 minutes
        CallDetailRecord::factory()->create([
            'tenant_id' => $tenant->id,
            'billsec' => 300,
            'start_stamp' => $today->copy()->setTime(10, 0),
        ]);

        // Record only 3 minutes (mismatch)
        $metering = new UsageMeteringService;
        $metering->record($tenant, UsageRecord::METRIC_CALL_MINUTES, 3.0, null, $today);

        $result = $metering->reconcileCallMinutes($tenant, $today, $today);

        $this->assertEquals(5.0, $result['cdr_total_minutes']);
        $this->assertEquals(3.0, $result['metered_minutes']);
        $this->assertFalse($result['matched']);
        $this->assertEquals(2.0, $result['difference_minutes']);
    }
}
