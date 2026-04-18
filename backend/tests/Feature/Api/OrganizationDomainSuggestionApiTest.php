<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationDomainSuggestionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_gets_unique_prefix_suggestion_with_suffix(): void
    {
        SystemSetting::upsertPlatformString(SystemSetting::ORGANIZATION_DOMAIN_SUFFIX, 'example.test');
        Organization::factory()->create(['domain' => 'abgd.example.test']);
        $user = User::factory()->create([
            'role' => 'superadmin',
            'organization_id' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/organizations/domain-suggestion?name=Alpha Beta Gamma Delta');

        $response->assertOk()
            ->assertJsonPath('data.prefix', 'abgd2')
            ->assertJsonPath('data.suffix', 'example.test')
            ->assertJsonPath('data.domain', 'abgd2.example.test');
    }

    public function test_suggestion_expands_characters_until_prefix_reaches_four_characters(): void
    {
        SystemSetting::upsertPlatformString(SystemSetting::ORGANIZATION_DOMAIN_SUFFIX, 'example.test');
        $user = User::factory()->create([
            'role' => 'superadmin',
            'organization_id' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/organizations/domain-suggestion?name=Acme Co');

        $response->assertOk()
            ->assertJsonPath('data.prefix', 'acco')
            ->assertJsonPath('data.domain', 'acco.example.test');
    }

    public function test_suggestion_requires_platform_org_create_access(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'role' => 'admin',
            'organization_id' => $organization->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/organizations/domain-suggestion?name=Acme Co');

        $response->assertForbidden();
    }
}
