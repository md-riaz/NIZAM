<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Support\Arr;

class OfficeFeatureService
{
    /**
     * @return array<string, bool>
     */
    public function getFeatures(Organization $organization): array
    {
        $features = data_get($organization->settings ?? [], 'business_phone.office_features', []);

        return $this->normalizeFeatures(is_array($features) ? $features : []);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, bool>
     */
    public function updateFeatures(Organization $organization, array $attributes): array
    {
        $features = $this->normalizeFeatures(array_merge($this->getFeatures($organization), $attributes));
        $settings = $organization->settings ?? [];

        Arr::set($settings, 'business_phone.office_features', $features);

        $organization->forceFill([
            'settings' => $settings,
        ])->save();

        return $features;
    }

    /**
     * @param  array<string, mixed>  $features
     * @return array<string, bool>
     */
    private function normalizeFeatures(array $features): array
    {
        return [
            'parking_enabled' => (bool) ($features['parking_enabled'] ?? false),
            'pickup_enabled' => (bool) ($features['pickup_enabled'] ?? false),
            'paging_enabled' => (bool) ($features['paging_enabled'] ?? false),
            'intercom_enabled' => (bool) ($features['intercom_enabled'] ?? false),
            'directory_enabled' => (bool) ($features['directory_enabled'] ?? false),
        ];
    }
}
