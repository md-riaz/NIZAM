<?php

namespace Tests\Feature\Api;

use App\Models\Bridge;
use App\Models\Extension;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BridgeDestinationValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_did_with_bridge_destination_type(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id, 'role' => 'admin']);
        $bridge = Bridge::factory()->create(['organization_id' => $organization->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/organizations/{$organization->id}/dids", [
                'number' => '+15550007777',
                'destination_type' => 'bridge',
                'destination_id' => $bridge->id,
                'is_active' => true,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('dids', [
            'organization_id' => $organization->id,
            'destination_type' => 'bridge',
            'destination_id' => $bridge->id,
        ]);
    }

    public function test_ring_group_accepts_bridge_fallback_destination_type(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id, 'role' => 'admin']);
        $bridge = Bridge::factory()->create(['organization_id' => $organization->id]);
        $extension = Extension::factory()->create(['organization_id' => $organization->id, 'is_active' => true]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/organizations/{$organization->id}/ring-groups", [
                'name' => 'Sales',
                'strategy' => 'simultaneous',
                'ring_timeout' => 20,
                'members' => [$extension->id],
                'fallback_destination_type' => 'bridge',
                'fallback_destination_id' => $bridge->id,
                'is_active' => true,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('ring_groups', [
            'organization_id' => $organization->id,
            'fallback_destination_type' => 'bridge',
            'fallback_destination_id' => $bridge->id,
        ]);
    }
}
