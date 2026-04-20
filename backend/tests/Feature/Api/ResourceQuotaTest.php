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
            'directory_first_name' => 'Test',
            'directory_last_name' => 'User',
        ]);

        // Try creating second extension (should fail)
        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/organizations/{$organization->id}/extensions", [
                'extension' => '1002',
                'password' => 'secret456789',
                'directory_first_name' => 'Test',
                'directory_last_name' => 'Two',
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
                'extension' => '1001',
                'password' => 'secret123456',
                'directory_first_name' => 'Test',
                'directory_last_name' => 'User',
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
            'directory_first_name' => 'Test',
            'directory_last_name' => 'User',
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

    public function test_ring_group_creation_blocked_when_quota_exceeded(): void
    {
        $organization = Organization::factory()->create(['max_ring_groups' => 1]);
        $user = $this->adminUser($organization);

        $extension = $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'directory_first_name' => 'Test',
            'directory_last_name' => 'User',
        ]);

        // Create first ring group
        $organization->ringGroups()->create([
            'name' => 'Group 1',
            'strategy' => 'simultaneous',
            'members' => [$extension->id],
        ]);

        // Try creating second ring group (should fail)
        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/organizations/{$organization->id}/ring-groups", [
                'name' => 'Group 2',
                'strategy' => 'simultaneous',
                'members' => [$extension->id],
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Ring group quota exceeded. Maximum allowed: 1']);
    }

    public function test_quotas_can_be_set_on_organization_creation(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/organizations', [
                'name' => 'Quota Organization',
                'domain' => 'quota.example.com',
                'max_extensions' => 50,
                'max_concurrent_calls' => 20,
                'max_dids' => 10,
                'max_ring_groups' => 5,
            ]);

        $response->assertStatus(201);
        $response->assertJsonFragment([
            'max_extensions' => 50,
            'max_concurrent_calls' => 20,
            'max_dids' => 10,
            'max_ring_groups' => 5,
        ]);
    }
}
