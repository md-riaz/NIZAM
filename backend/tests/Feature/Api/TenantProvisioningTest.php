<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_can_provision_tenant(): void
    {
        $user = $this->adminUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/tenants/provision', [
                'name' => 'Acme Corp',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'tenant',
                'default_extension' => ['extension'],
                'provisioning_domain',
            ],
        ]);
        $response->assertJsonPath('data.default_extension.extension', '1000');

        $this->assertDatabaseHas('tenants', ['name' => 'Acme Corp']);
        $this->assertDatabaseHas('extensions', ['extension' => '1000']);
    }

    public function test_provisioning_creates_trial_tenant_by_default(): void
    {
        $user = $this->adminUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/tenants/provision', [
                'name' => 'Trial Corp',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('tenants', ['name' => 'Trial Corp', 'status' => 'trial']);
    }

    public function test_provisioning_with_custom_domain(): void
    {
        $user = $this->adminUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/tenants/provision', [
                'name' => 'Custom Domain Corp',
                'domain' => 'custom.example.com',
                'slug' => 'custom-corp',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.provisioning_domain', 'custom.example.com');
    }

    public function test_provisioning_with_quotas(): void
    {
        $user = $this->adminUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/tenants/provision', [
                'name' => 'Quota Corp',
                'max_extensions' => 50,
                'max_concurrent_calls' => 20,
                'max_dids' => 10,
                'max_ring_groups' => 5,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('tenants', [
            'name' => 'Quota Corp',
            'max_extensions' => 50,
            'max_concurrent_calls' => 20,
        ]);
    }

    public function test_non_admin_cannot_provision(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['role' => 'user', 'tenant_id' => $tenant->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/tenants/provision', [
                'name' => 'Should Fail',
            ]);

        $response->assertStatus(403);
    }

    public function test_provisioning_validates_unique_domain(): void
    {
        $user = $this->adminUser();
        Tenant::factory()->create(['domain' => 'taken.example.com']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/tenants/provision', [
                'name' => 'Duplicate Domain',
                'domain' => 'taken.example.com',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['domain']);
    }
}
