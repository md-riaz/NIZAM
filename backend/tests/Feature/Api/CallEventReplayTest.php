<?php

namespace Tests\Feature\Api;

use App\Models\CallEventLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallEventReplayTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role' => 'admin',
        ]);
    }

    public function test_can_replay_event_by_id(): void
    {
        $event = CallEventLog::create([
            'organization_id' => $this->organization->id,
            'call_uuid' => 'replay-uuid-123',
            'event_type' => CallEventLog::EVENT_CALL_CREATED,
            'payload' => ['organization_id' => $this->organization->id, 'call_uuid' => 'replay-uuid-123'],
            'schema_version' => CallEventLog::SCHEMA_VERSION,
            'occurred_at' => now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/call-events/replay/{$event->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'call_uuid' => 'replay-uuid-123',
            'event_type' => CallEventLog::EVENT_CALL_CREATED,
            'schema_version' => CallEventLog::SCHEMA_VERSION,
        ]);
    }

    public function test_replay_returns_404_for_unknown_event(): void
    {
        $missingEventId = (string) \Illuminate\Support\Str::uuid();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/call-events/replay/{$missingEventId}");

        $response->assertStatus(404);
    }

    public function test_replay_enforces_organization_isolation(): void
    {
        $otherOrganization = Organization::factory()->create();
        $event = CallEventLog::create([
            'organization_id' => $otherOrganization->id,
            'call_uuid' => 'other-organization-uuid',
            'event_type' => CallEventLog::EVENT_CALL_CREATED,
            'payload' => ['test' => true],
            'schema_version' => CallEventLog::SCHEMA_VERSION,
            'occurred_at' => now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/call-events/replay/{$event->id}");

        $response->assertStatus(404);
    }
}
