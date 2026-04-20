<?php

namespace Tests\Feature\Api;

use App\Models\CallDetailRecord;
use App\Models\CallEventLog;
use App\Models\Recording;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisorReportApiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role' => 'admin',
        ]);
    }

    public function test_can_fetch_supervisor_call_summary_report(): void
    {
        CallDetailRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'direction' => 'inbound',
            'start_stamp' => '2026-04-10 10:00:00',
            'answer_stamp' => '2026-04-10 10:00:05',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/supervisor-reports/call-summary?date_from=2026-04-10&date_to=2026-04-10");

        $response->assertStatus(200)
            ->assertJsonPath('data.totals.calls', 1)
            ->assertJsonPath('data.totals.answered_calls', 1);
    }

    public function test_can_fetch_missed_returned_calls_report(): void
    {
        CallDetailRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'direction' => 'inbound',
            'caller_id_number' => '+15551231234',
            'start_stamp' => '2026-04-10 09:00:00',
            'answer_stamp' => null,
        ]);

        CallDetailRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'direction' => 'outbound',
            'destination_number' => '15551231234',
            'start_stamp' => '2026-04-11 09:00:00',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/supervisor-reports/missed-returned-calls?date_from=2026-04-10&date_to=2026-04-10");

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.missed_calls', 1)
            ->assertJsonPath('data.summary.returned_calls', 1)
            ->assertJsonPath('data.items.0.returned', true);
    }

    public function test_can_fetch_voicemails_needing_follow_up_report(): void
    {
        CallEventLog::create([
            'organization_id' => $this->organization->id,
            'call_uuid' => 'vm-api-001',
            'event_id' => 'evt-api-001',
            'event_type' => CallEventLog::EVENT_VOICEMAIL_RECEIVED,
            'source' => 'freeswitch',
            'payload' => [
                'metadata' => [
                    'caller_id_number' => '+15557654321',
                    'user' => '2001',
                    'storage_path' => 'voicemail/api/2001/msg.wav',
                ],
            ],
            'occurred_at' => '2026-04-10 14:00:00',
            'received_at' => '2026-04-10 14:00:00',
        ]);

        Recording::factory()->create([
            'organization_id' => $this->organization->id,
            'call_uuid' => 'vm-api-001',
            'file_path' => 'voicemail/api/2001/msg.wav',
            'needs_review' => true,
            'review_reasons' => ['short_call'],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/supervisor-reports/voicemails-needing-follow-up?date_from=2026-04-10&date_to=2026-04-10");

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.voicemails', 1)
            ->assertJsonPath('data.summary.pending_follow_up', 1)
            ->assertJsonPath('data.summary.needs_review', 1)
            ->assertJsonPath('data.items.0.follow_up_status', 'pending');
    }
}
