<?php

namespace Tests\Feature\Api;

use App\Models\CallEventLog;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallEventApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);

        Permission::updateOrCreate(['slug' => 'call_events.view'], ['module' => 'core']);
        $this->user->grantPermissions(['call_events.view']);
    }

    public function test_can_list_call_events_for_organization(): void
    {
        CallEventLog::create([
            'organization_id' => $this->organization->id,
            'call_uuid' => 'test-uuid-1',
            'event_type' => 'started',
            'payload' => ['caller_id_number' => '1001'],
            'occurred_at' => now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/call-events");

        $response->assertStatus(200);
        $response->assertJsonFragment(['call_uuid' => 'test-uuid-1']);
    }

    public function test_can_filter_events_by_call_uuid(): void
    {
        CallEventLog::create([
            'organization_id' => $this->organization->id,
            'call_uuid' => 'uuid-a',
            'event_type' => 'started',
            'payload' => [],
            'occurred_at' => now(),
        ]);
        CallEventLog::create([
            'organization_id' => $this->organization->id,
            'call_uuid' => 'uuid-b',
            'event_type' => 'started',
            'payload' => [],
            'occurred_at' => now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/call-events?call_uuid=uuid-a");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('uuid-a', $data[0]['call_uuid']);
    }

    public function test_can_trace_call_by_uuid(): void
    {
        $uuid = 'trace-uuid-123';

        foreach (['started', 'answered', 'bridge', 'hangup'] as $type) {
            CallEventLog::create([
                'organization_id' => $this->organization->id,
                'call_uuid' => $uuid,
                'event_type' => $type,
                'payload' => ['test' => true],
                'occurred_at' => now(),
            ]);
        }

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/call-events/{$uuid}/trace");

        $response->assertStatus(200);
        $response->assertJsonPath('call_uuid', $uuid);
        $response->assertJsonPath('event_count', 4);
    }

    public function test_trace_returns_404_for_unknown_uuid(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/call-events/nonexistent-uuid/trace");

        $response->assertStatus(404);
    }

    public function test_call_events_are_organization_scoped(): void
    {
        $otherOrganization = Organization::factory()->create();
        CallEventLog::create([
            'organization_id' => $otherOrganization->id,
            'call_uuid' => 'other-organization-uuid',
            'event_type' => 'started',
            'payload' => [],
            'occurred_at' => now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/call-events?call_uuid=other-organization-uuid");

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }
}
