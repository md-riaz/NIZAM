<?php

namespace Tests\Unit\Services\SupervisorReports;

use App\Models\CallDetailRecord;
use App\Models\Tenant;
use App\Services\SupervisorReports\CallSummaryReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallSummaryReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_supervisor_call_summary(): void
    {
        $tenant = Tenant::factory()->create();

        CallDetailRecord::factory()->create([
            'tenant_id' => $tenant->id,
            'direction' => 'inbound',
            'start_stamp' => '2026-04-10 10:00:00',
            'answer_stamp' => '2026-04-10 10:00:05',
            'duration' => 120,
            'billsec' => 110,
            'hangup_cause' => 'NORMAL_CLEARING',
        ]);

        CallDetailRecord::factory()->create([
            'tenant_id' => $tenant->id,
            'direction' => 'inbound',
            'start_stamp' => '2026-04-10 11:00:00',
            'answer_stamp' => null,
            'duration' => 45,
            'billsec' => 0,
            'hangup_cause' => 'NO_ANSWER',
        ]);

        CallDetailRecord::factory()->create([
            'tenant_id' => $tenant->id,
            'direction' => 'outbound',
            'destination_number' => 'voicemail',
            'start_stamp' => '2026-04-10 12:00:00',
            'answer_stamp' => null,
            'duration' => 30,
            'billsec' => 0,
            'hangup_cause' => 'VOICEMAIL',
        ]);

        $report = app(CallSummaryReportService::class)->generate(
            $tenant,
            now()->parse('2026-04-10'),
            now()->parse('2026-04-10'),
        );

        $this->assertSame(3, $report['totals']['calls']);
        $this->assertSame(1, $report['totals']['answered_calls']);
        $this->assertSame(1, $report['totals']['missed_calls']);
        $this->assertSame(1, $report['totals']['voicemail_calls']);
        $this->assertSame(195, $report['totals']['total_duration_seconds']);
        $this->assertSame(110, $report['totals']['total_billsec_seconds']);
        $this->assertSame(33.33, $report['totals']['answer_rate']);
        $this->assertSame(2, $report['by_direction']['inbound']);
        $this->assertSame(1, $report['by_direction']['outbound']);
    }
}
