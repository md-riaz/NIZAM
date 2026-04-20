<?php

namespace Tests\Feature\Api;

use App\Models\Extension;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_search_active_extensions_by_first_name_last_name_and_extension_number(): void
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'domain' => 'test.example.com',
        ]);

        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $john = Extension::factory()->create([
            'organization_id' => $organization->id,
            'extension' => '1001',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Smith',
            'is_active' => true,
        ]);

        $jane = Extension::factory()->create([
            'organization_id' => $organization->id,
            'extension' => '2002',
            'directory_first_name' => 'Jane',
            'directory_last_name' => 'Doe',
            'is_active' => true,
        ]);

        Extension::factory()->inactive()->create([
            'organization_id' => $organization->id,
            'extension' => '3003',
            'directory_first_name' => 'Inactive',
            'directory_last_name' => 'Person',
        ]);

        $otherOrganization = Organization::create([
            'name' => 'Other Organization',
            'domain' => 'other.example.com',
        ]);

        Extension::factory()->create([
            'organization_id' => $otherOrganization->id,
            'extension' => '4004',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Outside',
            'is_active' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/organizations/{$organization->id}/directory?search=John")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $john->id)
            ->assertJsonPath('data.0.extension', '1001');

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/organizations/{$organization->id}/directory?search=Doe")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $jane->id)
            ->assertJsonPath('data.0.extension', '2002');

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/organizations/{$organization->id}/directory?search=2002")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $jane->id)
            ->assertJsonPath('data.0.directory_first_name', 'Jane');
    }

    public function test_directory_route_requires_organization_access(): void
    {
        $organization = Organization::create([
            'name' => 'Organization A',
            'domain' => 'organization-a.example.com',
        ]);

        $otherOrganization = Organization::create([
            'name' => 'Organization B',
            'domain' => 'organization-b.example.com',
        ]);

        $user = User::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);

        Extension::factory()->create([
            'organization_id' => $organization->id,
            'extension' => '1001',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Smith',
            'is_active' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/organizations/{$organization->id}/directory")
            ->assertForbidden();
    }
}
