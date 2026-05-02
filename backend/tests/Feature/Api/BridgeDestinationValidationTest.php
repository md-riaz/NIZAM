<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BridgeDestinationValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_did_rejects_bridge_destination_type(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id, 'role' => 'admin']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/organizations/{$organization->id}/dids", [
                'number' => '+15550007777',
                'destination_type' => 'bridge',
                'destination_id' => (string) fake()->uuid(),
                'is_active' => true,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['destination_type']);
    }
}
