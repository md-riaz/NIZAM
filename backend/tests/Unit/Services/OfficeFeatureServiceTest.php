<?php

namespace Tests\Unit\Services;

use App\Models\Tenant;
use App\Services\OfficeFeatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeFeatureServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_default_office_features_when_none_are_stored(): void
    {
        $tenant = Tenant::factory()->create([
            'settings' => [],
        ]);

        $features = app(OfficeFeatureService::class)->getFeatures($tenant);

        $this->assertSame([
            'parking_enabled' => false,
            'pickup_enabled' => false,
            'paging_enabled' => false,
            'intercom_enabled' => false,
            'directory_enabled' => false,
        ], $features);
    }

    public function test_it_updates_office_features_without_overwriting_other_settings(): void
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

        $features = app(OfficeFeatureService::class)->updateFeatures($tenant, [
            'parking_enabled' => true,
            'directory_enabled' => true,
        ]);

        $this->assertSame([
            'parking_enabled' => true,
            'pickup_enabled' => true,
            'paging_enabled' => false,
            'intercom_enabled' => false,
            'directory_enabled' => true,
        ], $features);

        $tenant->refresh();

        $this->assertSame('UTC', data_get($tenant->settings, 'timezone'));
        $this->assertSame('flow-123', data_get($tenant->settings, 'business_phone.default_entrypoint.flow_id'));
        $this->assertSame($features, data_get($tenant->settings, 'business_phone.office_features'));
    }
}
