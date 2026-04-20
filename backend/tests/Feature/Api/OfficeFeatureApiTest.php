<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeFeatureApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_organization_office_features(): void
    {
        $organization = Organization::factory()->create([
            'settings' => [
                'business_phone' => [
                    'office_features' => [
                        'parking_enabled' => true,
                        'pickup_enabled' => false,
                        'paging_enabled' => true,
                    ],
                ],
            ],
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'organization_id' => $organization->id,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/organizations/{$organization->id}/office-features")
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'parking_enabled' => true,
                    'pickup_enabled' => false,
                    'paging_enabled' => true,
                    'intercom_enabled' => false,
                    'directory_enabled' => false,
                ],
            ]);
    }

    public function test_it_updates_organization_office_features_at_business_phone_settings_path(): void
    {
        $organization = Organization::factory()->create([
            'settings' => [
                'timezone' => 'UTC',
                'business_phone' => [
                    'default_entrypoint' => [
                        'flow_id' => 'flow-123',
                    ],
                    'office_features' => [
                        'parking_enabled' => false,
                        'pickup_enabled' => true,
                    ],
                ],
            ],
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'organization_id' => $organization->id,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/organizations/{$organization->id}/office-features", [
                'parking_enabled' => true,
                'intercom_enabled' => true,
            ])
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'parking_enabled' => true,
                    'pickup_enabled' => true,
                    'paging_enabled' => false,
                    'intercom_enabled' => true,
                    'directory_enabled' => false,
                ],
            ]);

        $organization->refresh();

        $this->assertSame('UTC', data_get($organization->settings, 'timezone'));
        $this->assertSame('flow-123', data_get($organization->settings, 'business_phone.default_entrypoint.flow_id'));
        $this->assertSame([
            'parking_enabled' => true,
            'pickup_enabled' => true,
            'paging_enabled' => false,
            'intercom_enabled' => true,
            'directory_enabled' => false,
        ], data_get($organization->settings, 'business_phone.office_features'));
    }
}
