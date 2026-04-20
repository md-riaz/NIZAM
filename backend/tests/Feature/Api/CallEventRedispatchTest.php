<?php

namespace Tests\Feature\Api;

use App\Models\CallEventLog;
use App\Models\Organization;
use App\Models\User;
use App\Services\WebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallEventRedispatchTest extends TestCase
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

    public function test_can_redispatch_event(): void
    {
        $this->mock(WebhookDispatcher::class, function ($mock) {
            $mock->shouldReceive('dispatch')
                ->once()
                ->with($this->organization->id, CallEventLog::EVENT_CALL_CREATED, \Mockery::type('array'));
        });

        $event = CallEventLog::create([
            'organization_id' => $this->organization->id,
            'call_uuid' => 'redispatch-uuid-123',
            'event_type' => CallEventLog::EVENT_CALL_CREATED,
            'payload' => ['organization_id' => $this->organization->id, 'call_uuid' => 'redispatch-uuid-123'],
            'schema_version' => CallEventLog::SCHEMA_VERSION,
            'occurred_at' => now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/call-events/redispatch/{$event->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'event_type' => CallEventLog::EVENT_CALL_CREATED,
        ]);
    }

    public function test_redispatch_returns_404_for_missing_event(): void
    {
        $missingEventId = (string) \Illuminate\Support\Str::uuid();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/call-events/redispatch/{$missingEventId}");

        $response->assertStatus(404);
    }

    public function test_redispatch_enforces_organization_isolation(): void
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
            ->postJson("/api/v1/organizations/{$this->organization->id}/call-events/redispatch/{$event->id}");

        $response->assertStatus(404);
    }
}
