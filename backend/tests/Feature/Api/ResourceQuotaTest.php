<?php

namespace Tests\Feature\Api;

use App\Models\Extension;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceQuotaTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(Organization $organization): User
    {
        return User::factory()->create(['role' => 'admin', 'organization_id' => $organization->id]);
    }

    public function test_extension_creation_blocked_when_quota_exceeded(): void
    {
        $organization = Organization::factory()->create(['max_extensions' => 1]);
        $user = $this->adminUser($organization);

        // Create first extension (should succeed)
        $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);

        // Try creating second extension (should fail)
        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/organizations/{$organization->id}/extensions", [
                'extension' => '102',
                'password' => 'secret456789',
                'first_name' => 'Test',
                'last_name' => 'Two',
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Extension quota exceeded. Maximum allowed: 1']);
    }

    public function test_extension_creation_allowed_when_quota_zero(): void
    {
        $organization = Organization::factory()->create(['max_extensions' => 0]);
        $user = $this->adminUser($organization);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/organizations/{$organization->id}/extensions", [
                'extension' => '101',
                'password' => 'secret123456',
                'first_name' => 'Test',
                'last_name' => 'User',
            ]);

        $response->assertStatus(201);
    }

    public function test_did_creation_blocked_when_quota_exceeded(): void
    {
        $organization = Organization::factory()->create(['max_dids' => 1]);
        $user = $this->adminUser($organization);

        $extension = $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);

        // Create first DID
        $organization->dids()->create([
            'number' => '+15551234567',
            'destination_type' => 'extension',
            'destination_id' => $extension->id,
        ]);

        // Try creating second DID (should fail)
        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/organizations/{$organization->id}/dids", [
                'number' => '+15559876543',
                'destination_type' => 'extension',
                'destination_id' => $extension->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'DID quota exceeded. Maximum allowed: 1']);
    }

    public function test_quotas_can_be_set_on_organization_creation(): void
    {
        $user = User::factory()->create(['role' => 'superadmin', 'organization_id' => null]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/organizations', [
                'name' => 'Quota Organization',
                'domain_prefix' => 'quota',
                'max_extensions' => 50,
                'max_concurrent_calls' => 20,
                'max_dids' => 10,
                'max_teams' => 5,
            ]);

        $response->assertStatus(201);
        $response->assertJsonFragment([
            'max_extensions' => 50,
            'max_concurrent_calls' => 20,
            'max_dids' => 10,
            'max_teams' => 5,
        ]);

        $organization = Organization::findOrFail($response->json('data.id'));

        $this->assertSame(5, $organization->max_teams);
    }

    public function test_quotas_can_be_updated_through_public_max_teams_field(): void
    {
        $organization = Organization::factory()->create([
            'max_extensions' => 1,
            'max_concurrent_calls' => 2,
            'max_dids' => 3,
            'max_teams' => 4,
        ]);
        $user = User::factory()->create(['role' => 'superadmin', 'organization_id' => null]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/organizations/{$organization->id}", [
                'name' => $organization->name,
                'domain_prefix' => $organization->domain,
                'max_extensions' => 10,
                'max_concurrent_calls' => 20,
                'max_dids' => 30,
                'max_teams' => 40,
                'is_active' => $organization->is_active,
                'status' => $organization->status,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.max_extensions', 10)
            ->assertJsonPath('data.max_concurrent_calls', 20)
            ->assertJsonPath('data.max_dids', 30)
            ->assertJsonPath('data.max_teams', 40);

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'max_extensions' => 10,
            'max_concurrent_calls' => 20,
            'max_dids' => 30,
            'max_teams' => 40,
        ]);
    }
}
