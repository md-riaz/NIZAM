<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->user = User::factory()->create(['organization_id' => $this->organization->id, 'role' => 'user']);
    }

    public function test_admin_can_list_users(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/users');

        $response->assertStatus(200);
    }

    public function test_non_admin_cannot_list_users(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/users');

        $response->assertStatus(403);
    }

    public function test_admin_can_create_user(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/users', [
                'name' => 'New User',
                'email' => 'newuser@example.com',
                'password' => 'password123',
                'role' => 'user',
                'organization_id' => $this->organization->id,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    }

    public function test_admin_can_update_user(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/users/{$this->user->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_admin_can_delete_user(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/users/{$this->user->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('users', ['id' => $this->user->id]);
    }

    public function test_admin_can_view_user_permissions(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/users/{$this->user->id}/permissions");

        $response->assertStatus(200);
        $response->assertJsonStructure(['permissions']);
    }

    public function test_admin_can_grant_permissions(): void
    {
        Permission::create(['slug' => 'extensions.view', 'module' => 'core']);
        Permission::create(['slug' => 'extensions.create', 'module' => 'core']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/users/{$this->user->id}/permissions/grant", [
                'permissions' => ['extensions.view', 'extensions.create'],
            ]);

        $response->assertStatus(200);
        $this->assertTrue($this->user->fresh()->hasPermission('extensions.view'));
    }

    public function test_admin_can_revoke_permissions(): void
    {
        Permission::create(['slug' => 'extensions.view', 'module' => 'core']);
        $this->user->grantPermissions(['extensions.view']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/users/{$this->user->id}/permissions/revoke", [
                'permissions' => ['extensions.view'],
            ]);

        $response->assertStatus(200);
    }

    public function test_admin_can_list_available_permissions(): void
    {
        Permission::create(['slug' => 'extensions.view', 'module' => 'core']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/permissions');

        $response->assertStatus(200);
        $response->assertJsonStructure(['permissions']);
    }

    public function test_non_admin_cannot_create_users(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/users', [
                'name' => 'New User',
                'email' => 'newuser@example.com',
                'password' => 'password123',
            ]);

        $response->assertStatus(403);
    }

    public function test_can_filter_users_by_organization(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/users?organization_id={$this->organization->id}");

        $response->assertStatus(200);
    }
}
