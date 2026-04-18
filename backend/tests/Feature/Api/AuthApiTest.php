<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_disabled(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Self-registration is disabled.',
            ]);

        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_registration_remains_disabled_without_payload(): void
    {
        $response = $this->postJson('/api/v1/auth/register', []);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Self-registration is disabled.',
            ]);
    }

    public function test_registration_remains_disabled_for_existing_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Another User',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Self-registration is disabled.',
            ]);
    }

    public function test_platform_superadmin_can_login_with_null_organization(): void
    {
        $user = User::factory()->create([
            'role' => 'superadmin',
            'organization_id' => null,
            'email' => 'system@app.local',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.role', 'superadmin')
            ->assertJsonPath('user.organization_id', null)
            ->assertJsonPath('user.organization', null);
    }

    public function test_organization_admin_can_login_with_organization_payload(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => 'admin',
            'email' => 'org-admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonPath('user.organization_id', (string) $organization->id)
            ->assertJsonPath('user.organization.id', (string) $organization->id);
    }

    public function test_seeded_platform_superadmin_role_matches_platform_contract(): void
    {
        $this->seed();

        $this->assertDatabaseHas('users', [
            'email' => 'system@app.local',
            'role' => 'superadmin',
            'organization_id' => null,
        ]);
    }

    public function test_seeded_organization_admin_role_matches_organization_contract(): void
    {
        $this->seed();

        $this->assertDatabaseHas('users', [
            'email' => env('ADMIN_EMAIL', 'admin@app.local'),
            'role' => 'admin',
        ]);
    }

    public function test_platform_admin_gate_only_allows_superadmin_without_organization(): void
    {
        $superadmin = User::factory()->create([
            'role' => 'superadmin',
            'organization_id' => null,
        ]);
        $organizationAdmin = User::factory()->create([
            'role' => 'admin',
            'organization_id' => (string) Organization::factory()->create()->id,
        ]);

        $this->assertTrue($superadmin->can('platform-admin'));
        $this->assertFalse($organizationAdmin->can('platform-admin'));
    }

    public function test_organization_access_middleware_blocks_organization_admin_from_other_organization(): void
    {
        $ownedOrganization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $user = User::factory()->create([
            'role' => 'admin',
            'organization_id' => $ownedOrganization->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/organizations/'.$otherOrganization->id.'/extensions');

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Forbidden.',
            ]);
    }

    public function test_organization_access_middleware_allows_platform_superadmin_across_organizations(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'role' => 'superadmin',
            'organization_id' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/organizations/'.$organization->id.'/extensions');

        $response->assertStatus(200);
    }

    public function test_organization_access_middleware_allows_organization_admin_within_own_organization(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'role' => 'admin',
            'organization_id' => $organization->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/organizations/'.$organization->id.'/extensions');

        $response->assertStatus(200);
    }

    public function test_platform_admin_gate_blocks_superadmin_with_organization_assignment(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'role' => 'superadmin',
            'organization_id' => $organization->id,
        ]);

        $this->assertFalse($user->can('platform-admin'));
    }

    public function test_platform_admin_gate_blocks_orgless_admin(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'organization_id' => null,
        ]);

        $this->assertFalse($user->can('platform-admin'));
    }

    public function test_login_response_keeps_explicit_organization_fields(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => 'admin',
            'email' => 'login-shape@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.organization_id', (string) $organization->id)
            ->assertJsonPath('user.organization.id', (string) $organization->id);
    }

    public function test_me_response_keeps_explicit_organization_fields(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('user.organization_id', (string) $organization->id)
            ->assertJsonPath('user.organization.id', (string) $organization->id);
    }

    public function test_can_login_with_valid_credentials(): void
    {
        $organization = Organization::factory()->create();
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => bcrypt('password123'),
            'organization_id' => $organization->id,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'organization_id',
                    'role',
                    'email_verified_at',
                    'organization',
                    'created_at',
                    'updated_at',
                ],
                'token',
            ])
            ->assertJsonPath('user.email', 'login@example.com')
            ->assertJsonPath('user.organization_id', $organization->id)
            ->assertJsonPath('user.organization.id', $organization->id)
            ->assertJsonPath('user.organization.name', $organization->name);
    }

    public function test_login_returns_explicit_role_and_organization_payload(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => 'admin',
            'email' => 'org-admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonPath('user.organization_id', (string) $organization->id)
            ->assertJsonPath('user.organization.id', (string) $organization->id);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'login@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
        $response->assertJsonFragment(['message' => 'Invalid credentials.']);
    }

    public function test_can_get_authenticated_user_profile(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'organization_id',
                    'role',
                    'email_verified_at',
                    'organization' => [
                        'id',
                        'name',
                        'domain',
                        'default_schedule_id',
                        'default_holiday_calendar_id',
                        'settings',
                        'status',
                        'max_extensions',
                        'max_concurrent_calls',
                        'max_dids',
                        'max_ring_groups',
                        'is_active',
                        'created_at',
                        'updated_at',
                    ],
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.organization_id', $organization->id)
            ->assertJsonPath('user.organization.id', $organization->id)
            ->assertJsonPath('user.organization.name', $organization->name);
    }

    public function test_superadmin_me_payload_has_null_organization(): void
    {
        $user = User::factory()->create([
            'role' => 'superadmin',
            'organization_id' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('user.role', 'superadmin')
            ->assertJsonPath('user.organization_id', null)
            ->assertJsonPath('user.organization', null);
    }

    public function test_unauthenticated_user_cannot_access_me(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    public function test_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(204);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
