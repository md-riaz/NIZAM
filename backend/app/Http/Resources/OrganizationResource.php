<?php

namespace App\Http\Resources;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $suffix = $this->configuredSuffix();
        $matchesConfiguredSuffix = $suffix !== '' && Str::endsWith($this->domain, '.'.$suffix);
        $domainPrefix = $matchesConfiguredSuffix
            ? Str::beforeLast($this->domain, '.'.$suffix)
            : $this->domain;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'domain' => $this->domain,
            'domain_prefix' => $domainPrefix,
            'domain_suffix' => $suffix,
            'domain_matches_configured_suffix' => $matchesConfiguredSuffix,
            'default_schedule_id' => $this->default_schedule_id,
            'default_holiday_calendar_id' => $this->default_holiday_calendar_id,
            'settings' => $this->settings,
            'status' => $this->status,
            'max_extensions' => $this->max_extensions,
            'max_concurrent_calls' => $this->max_concurrent_calls,
            'max_dids' => $this->max_dids,
            'max_ring_groups' => $this->max_ring_groups,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function configuredSuffix(): string
    {
        return Str::of(SystemSetting::platformString(SystemSetting::ORGANIZATION_DOMAIN_SUFFIX, ''))
            ->trim()
            ->lower()
            ->replaceMatches('/^\.+/', '')
            ->replaceMatches('/\.+$/', '')
            ->value();
    }
}
