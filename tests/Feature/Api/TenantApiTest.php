<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantApiTest extends TestCase
{
    use RefreshDatabase;

    private function authenticatedUser(): User
    {
        return User::factory()->create();
    }

    public function test_unauthenticated_requests_return_401(): void
    {
        $response = $this->getJson('/api/tenants');

        $response->assertStatus(401);
    }

    public function test_can_list_tenants(): void
    {
        $user = $this->authenticatedUser();

        Tenant::create([
            'name' => 'Tenant One',
            'domain' => 'one.example.com',
            'slug' => 'tenant-one',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/tenants');

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Tenant One']);
    }

    public function test_can_create_a_tenant(): void
    {
        $user = $this->authenticatedUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tenants', [
                'name' => 'New Tenant',
                'domain' => 'new.example.com',
                'slug' => 'new-tenant',
                'max_extensions' => 100,
                'is_active' => true,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('tenants', [
            'name' => 'New Tenant',
            'domain' => 'new.example.com',
        ]);
    }

    public function test_can_show_a_single_tenant(): void
    {
        $user = $this->authenticatedUser();

        $tenant = Tenant::create([
            'name' => 'Show Tenant',
            'domain' => 'show.example.com',
            'slug' => 'show-tenant',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/tenants/{$tenant->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Show Tenant']);
    }

    public function test_can_update_a_tenant(): void
    {
        $user = $this->authenticatedUser();

        $tenant = Tenant::create([
            'name' => 'Old Name',
            'domain' => 'old.example.com',
            'slug' => 'old-tenant',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/tenants/{$tenant->id}", [
                'name' => 'Updated Name',
                'domain' => 'old.example.com',
                'slug' => 'old-tenant',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('tenants', ['name' => 'Updated Name']);
    }

    public function test_can_delete_a_tenant(): void
    {
        $user = $this->authenticatedUser();

        $tenant = Tenant::create([
            'name' => 'Delete Me',
            'domain' => 'delete.example.com',
            'slug' => 'delete-tenant',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/tenants/{$tenant->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
    }

    public function test_validates_required_fields_on_create(): void
    {
        $user = $this->authenticatedUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tenants', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'domain', 'slug']);
    }
}
