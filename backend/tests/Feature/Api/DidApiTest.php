<?php

namespace Tests\Feature\Api;

use App\Models\Did;
use App\Models\Gateway;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DidApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);

        // Permissions are deny-by-default, so the acting user is granted the
        // DID abilities these cases exercise rather than relying on a
        // permissive fallback.
        $slugs = ['dids.view', 'dids.create', 'dids.update', 'dids.delete'];
        foreach ($slugs as $slug) {
            Permission::updateOrCreate(['slug' => $slug], ['module' => 'core']);
        }
        $this->user->grantPermissions($slugs);
    }

    public function test_user_without_permission_cannot_list_dids(): void
    {
        $unprivileged = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($unprivileged, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/dids")
            ->assertForbidden();
    }

    public function test_can_list_dids_for_a_organization(): void
    {
        Did::factory()->count(3)->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/dids");

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_a_did(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/dids", [
                'number' => '+15551234567',
                'description' => 'Main line',
                'destination_type' => 'extension',
                'destination_id' => Str::uuid()->toString(),
                'is_active' => true,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('dids', [
            'organization_id' => $this->organization->id,
            'number' => '+15551234567',
        ]);
    }

    public function test_can_show_a_did(): void
    {
        $did = Did::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/dids/{$did->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['number' => $did->number]);
    }

    public function test_can_show_a_did_with_linked_gateway_fields(): void
    {
        $gateway = Gateway::factory()->create(['organization_id' => $this->organization->id]);
        $did = Did::factory()->create([
            'organization_id' => $this->organization->id,
            'gateway_id' => $gateway->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/dids/{$did->id}");

        $response->assertOk()
            ->assertJsonPath('data.gateway_id', $gateway->id)
            ->assertJsonPath('data.gateway.id', $gateway->id)
            ->assertJsonPath('data.gateway.name', $gateway->name)
            ->assertJsonPath('data.gateway.organization_id', $gateway->organization_id);
    }

    public function test_show_preserves_flow_destination_type(): void
    {
        $did = Did::factory()->create([
            'organization_id' => $this->organization->id,
            'destination_type' => 'flow',
            'destination_id' => Str::uuid()->toString(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/dids/{$did->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.destination_type', 'flow');
    }

    public function test_can_update_a_did(): void
    {
        $did = Did::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/organizations/{$this->organization->id}/dids/{$did->id}", [
                'number' => '+15559999999',
                'destination_type' => 'flow',
                'destination_id' => Str::uuid()->toString(),
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('dids', [
            'id' => $did->id,
            'number' => '+15559999999',
        ]);
    }

    public function test_can_update_a_did_recording_policy(): void
    {
        // Pinned to 'extension': the factory's default destination_type is a
        // random pick that can land outside the update endpoint's
        // extension|flow enum, making this test flaky independent of
        // permissions.
        $did = Did::factory()->create([
            'organization_id' => $this->organization->id,
            'destination_type' => 'extension',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/organizations/{$this->organization->id}/dids/{$did->id}", [
                'number' => '+12025550123',
                'destination_type' => $did->destination_type,
                'destination_id' => (string) $did->destination_id,
                'recording_policy' => 'incoming',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.recording_policy', 'incoming');

        $this->assertDatabaseHas('dids', [
            'id' => $did->id,
            'number' => '+12025550123',
            'recording_policy' => 'incoming',
        ]);
    }

    public function test_can_delete_a_did(): void
    {
        $did = Did::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$this->organization->id}/dids/{$did->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('dids', ['id' => $did->id]);
    }

    public function test_did_recording_policy_rejects_unknown_values(): void
    {
        $did = Did::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/organizations/{$this->organization->id}/dids/{$did->id}", [
                'number' => $did->number,
                'destination_type' => $did->destination_type,
                'destination_id' => (string) $did->destination_id,
                'recording_policy' => 'always',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['recording_policy']);
    }
}
