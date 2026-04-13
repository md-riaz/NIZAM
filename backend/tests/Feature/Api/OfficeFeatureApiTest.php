<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeFeatureApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_tenant_office_features(): void
    {
        $tenant = Tenant::factory()->create([
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
            'tenant_id' => $tenant->id,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/tenants/{$tenant->id}/office-features")
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

    public function test_it_updates_tenant_office_features_at_business_phone_settings_path(): void
    {
        $tenant = Tenant::factory()->create([
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
            'tenant_id' => $tenant->id,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/tenants/{$tenant->id}/office-features", [
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

        $tenant->refresh();

        $this->assertSame('UTC', data_get($tenant->settings, 'timezone'));
        $this->assertSame('flow-123', data_get($tenant->settings, 'business_phone.default_entrypoint.flow_id'));
        $this->assertSame([
            'parking_enabled' => true,
            'pickup_enabled' => true,
            'paging_enabled' => false,
            'intercom_enabled' => true,
            'directory_enabled' => false,
        ], data_get($tenant->settings, 'business_phone.office_features'));
    }
}
