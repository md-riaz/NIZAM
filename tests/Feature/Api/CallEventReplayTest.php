<?php

namespace Tests\Feature\Api;

use App\Models\CallEventLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallEventReplayTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);
    }

    public function test_can_replay_event_by_id(): void
    {
        $event = CallEventLog::create([
            'tenant_id' => $this->tenant->id,
            'call_uuid' => 'replay-uuid-123',
            'event_type' => CallEventLog::EVENT_CALL_CREATED,
            'payload' => ['tenant_id' => $this->tenant->id, 'call_uuid' => 'replay-uuid-123'],
            'schema_version' => CallEventLog::SCHEMA_VERSION,
            'occurred_at' => now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/tenants/{$this->tenant->id}/call-events/replay/{$event->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'call_uuid' => 'replay-uuid-123',
            'event_type' => CallEventLog::EVENT_CALL_CREATED,
            'schema_version' => CallEventLog::SCHEMA_VERSION,
        ]);
    }

    public function test_replay_returns_404_for_unknown_event(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/tenants/{$this->tenant->id}/call-events/replay/nonexistent-id");

        $response->assertStatus(404);
    }

    public function test_replay_enforces_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        $event = CallEventLog::create([
            'tenant_id' => $otherTenant->id,
            'call_uuid' => 'other-tenant-uuid',
            'event_type' => CallEventLog::EVENT_CALL_CREATED,
            'payload' => ['test' => true],
            'schema_version' => CallEventLog::SCHEMA_VERSION,
            'occurred_at' => now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/tenants/{$this->tenant->id}/call-events/replay/{$event->id}");

        $response->assertStatus(404);
    }
}
