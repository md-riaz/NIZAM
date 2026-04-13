<?php

namespace Tests\Unit\Services\SupervisorReports;

use App\Models\CallDetailRecord;
use App\Models\CallEventLog;
use App\Models\Recording;
use App\Models\Tenant;
use App\Services\SupervisorReports\VoicemailsNeedingFollowUpReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoicemailsNeedingFollowUpReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_pending_voicemail_follow_up_when_no_returned_call_exists(): void
    {
        $tenant = Tenant::factory()->create();

        $event = CallEventLog::create([
            'tenant_id' => $tenant->id,
            'call_uuid' => 'vm-001',
            'event_id' => 'evt-001',
            'event_type' => CallEventLog::EVENT_VOICEMAIL_RECEIVED,
            'source' => 'freeswitch',
            'payload' => [
                'metadata' => [
                    'caller_id_number' => '+1 555 000 1111',
                    'user' => '1001',
                    'storage_path' => 'voicemail/test/1001/msg.wav',
                ],
            ],
            'occurred_at' => '2026-04-02 14:00:00',
            'received_at' => '2026-04-02 14:00:00',
        ]);

        $recording = Recording::factory()->create([
            'tenant_id' => $tenant->id,
            'call_uuid' => 'vm-001',
            'file_path' => 'voicemail/test/1001/msg.wav',
            'needs_review' => true,
            'review_reasons' => ['high_silence_ratio'],
        ]);

        $report = app(VoicemailsNeedingFollowUpReportService::class)->generate(
            $tenant,
            now()->parse('2026-04-01'),
            now()->parse('2026-04-03'),
        );

        $this->assertSame(1, $report['summary']['voicemails']);
        $this->assertSame(1, $report['summary']['pending_follow_up']);
        $this->assertSame(1, $report['summary']['needs_review']);
        $this->assertSame(1, $report['summary']['needs_attention']);
        $this->assertSame($event->id, $report['items'][0]['event_id']);
        $this->assertSame($recording->id, $report['items'][0]['recording']['id']);
        $this->assertSame('pending', $report['items'][0]['follow_up_status']);
        $this->assertTrue($report['items'][0]['needs_attention']);
        $this->assertNull($report['items'][0]['returned_call']);
    }

    public function test_reports_voicemail_as_returned_when_outbound_follow_up_exists(): void
    {
        $tenant = Tenant::factory()->create();

        CallEventLog::create([
            'tenant_id' => $tenant->id,
            'call_uuid' => 'vm-002',
            'event_id' => 'evt-002',
            'event_type' => CallEventLog::EVENT_VOICEMAIL_RECEIVED,
            'source' => 'freeswitch',
            'payload' => [
                'metadata' => [
                    'caller_id_number' => '+15559998888',
                    'user' => '1002',
                ],
            ],
            'occurred_at' => '2026-04-02 09:00:00',
            'received_at' => '2026-04-02 09:00:00',
        ]);

        CallDetailRecord::factory()->create([
            'tenant_id' => $tenant->id,
            'direction' => 'outbound',
            'destination_number' => '(555) 999-8888',
            'start_stamp' => '2026-04-03 11:30:00',
        ]);

        $report = app(VoicemailsNeedingFollowUpReportService::class)->generate(
            $tenant,
            now()->parse('2026-04-01'),
            now()->parse('2026-04-03'),
        );

        $this->assertSame(1, $report['summary']['voicemails']);
        $this->assertSame(0, $report['summary']['pending_follow_up']);
        $this->assertSame('returned', $report['items'][0]['follow_up_status']);
        $this->assertFalse($report['items'][0]['needs_attention']);
        $this->assertNotNull($report['items'][0]['returned_call']);
        $this->assertSame('15559998888', $report['items'][0]['normalized_caller_number']);
    }
}
