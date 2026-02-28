<?php

namespace Tests\Feature\Api;

use App\Models\CallEventLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\WebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallEventRedispatchTest extends TestCase
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

    public function test_can_redispatch_event(): void
    {
        $this->mock(WebhookDispatcher::class, function ($mock) {
            $mock->shouldReceive('dispatch')
                ->once()
                ->with($this->tenant->id, CallEventLog::EVENT_CALL_CREATED, \Mockery::type('array'));
        });

        $event = CallEventLog::create([
            'tenant_id' => $this->tenant->id,
            'call_uuid' => 'redispatch-uuid-123',
            'event_type' => CallEventLog::EVENT_CALL_CREATED,
            'payload' => ['tenant_id' => $this->tenant->id, 'call_uuid' => 'redispatch-uuid-123'],
            'schema_version' => CallEventLog::SCHEMA_VERSION,
            'occurred_at' => now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/tenants/{$this->tenant->id}/call-events/redispatch/{$event->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'event_type' => CallEventLog::EVENT_CALL_CREATED,
        ]);
    }

    public function test_redispatch_returns_404_for_missing_event(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/tenants/{$this->tenant->id}/call-events/redispatch/nonexistent-id");

        $response->assertStatus(404);
    }

    public function test_redispatch_enforces_tenant_isolation(): void
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
            ->postJson("/api/tenants/{$this->tenant->id}/call-events/redispatch/{$event->id}");

        $response->assertStatus(404);
    }
}
