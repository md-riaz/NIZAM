<?php

namespace Tests\Unit\Services\SupervisorReports;

use App\Models\CallDetailRecord;
use App\Models\Organization;
use App\Services\SupervisorReports\MissedReturnedCallsReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MissedReturnedCallsReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_missed_call_as_returned_when_outbound_call_happens_within_window(): void
    {
        $organization = Organization::factory()->create();

        $missed = CallDetailRecord::factory()->create([
            'organization_id' => $organization->id,
            'direction' => 'inbound',
            'caller_id_number' => '+1 (555) 123-4567',
            'start_stamp' => '2026-04-01 09:00:00',
            'answer_stamp' => null,
        ]);

        $returned = CallDetailRecord::factory()->create([
            'organization_id' => $organization->id,
            'direction' => 'outbound',
            'destination_number' => '15551234567',
            'start_stamp' => '2026-04-03 10:00:00',
        ]);

        $report = app(MissedReturnedCallsReportService::class)->generate(
            $organization,
            now()->parse('2026-04-01'),
            now()->parse('2026-04-05'),
        );

        $this->assertSame(1, $report['summary']['missed_calls']);
        $this->assertSame(1, $report['summary']['returned_calls']);
        $this->assertSame(0, $report['summary']['open_missed_calls']);
        $this->assertSame($missed->id, $report['items'][0]['cdr_id']);
        $this->assertTrue($report['items'][0]['returned']);
        $this->assertSame($returned->id, $report['items'][0]['returned_call']['cdr_id']);
        $this->assertSame('15551234567', $report['items'][0]['normalized_caller_number']);
    }

    public function test_leaves_missed_call_open_when_return_happens_outside_window(): void
    {
        $organization = Organization::factory()->create();

        CallDetailRecord::factory()->create([
            'organization_id' => $organization->id,
            'direction' => 'inbound',
            'caller_id_number' => '+15557654321',
            'start_stamp' => '2026-04-01 09:00:00',
            'answer_stamp' => null,
        ]);

        CallDetailRecord::factory()->create([
            'organization_id' => $organization->id,
            'direction' => 'outbound',
            'destination_number' => '+15557654321',
            'start_stamp' => '2026-04-12 09:00:00',
        ]);

        $report = app(MissedReturnedCallsReportService::class)->generate(
            $organization,
            now()->parse('2026-04-01'),
            now()->parse('2026-04-05'),
            7,
        );

        $this->assertSame(1, $report['summary']['missed_calls']);
        $this->assertSame(0, $report['summary']['returned_calls']);
        $this->assertSame(1, $report['summary']['open_missed_calls']);
        $this->assertFalse($report['items'][0]['returned']);
        $this->assertNull($report['items'][0]['returned_call']);
    }
}
