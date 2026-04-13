<?php

namespace Tests\Feature\Api;

use App\Models\Extension;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_search_active_extensions_by_first_name_last_name_and_extension_number(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $john = Extension::factory()->create([
            'tenant_id' => $tenant->id,
            'extension' => '1001',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Smith',
            'is_active' => true,
        ]);

        $jane = Extension::factory()->create([
            'tenant_id' => $tenant->id,
            'extension' => '2002',
            'directory_first_name' => 'Jane',
            'directory_last_name' => 'Doe',
            'is_active' => true,
        ]);

        Extension::factory()->inactive()->create([
            'tenant_id' => $tenant->id,
            'extension' => '3003',
            'directory_first_name' => 'Inactive',
            'directory_last_name' => 'Person',
        ]);

        $otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'domain' => 'other.example.com',
        ]);

        Extension::factory()->create([
            'tenant_id' => $otherTenant->id,
            'extension' => '4004',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Outside',
            'is_active' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/tenants/{$tenant->id}/directory?search=John")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $john->id)
            ->assertJsonPath('data.0.extension', '1001');

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/tenants/{$tenant->id}/directory?search=Doe")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $jane->id)
            ->assertJsonPath('data.0.extension', '2002');

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/tenants/{$tenant->id}/directory?search=2002")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $jane->id)
            ->assertJsonPath('data.0.directory_first_name', 'Jane');
    }

    public function test_directory_route_requires_tenant_access(): void
    {
        $tenant = Tenant::create([
            'name' => 'Tenant A',
            'domain' => 'tenant-a.example.com',
        ]);

        $otherTenant = Tenant::create([
            'name' => 'Tenant B',
            'domain' => 'tenant-b.example.com',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        Extension::factory()->create([
            'tenant_id' => $tenant->id,
            'extension' => '1001',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Smith',
            'is_active' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/tenants/{$tenant->id}/directory")
            ->assertForbidden();
    }
}
