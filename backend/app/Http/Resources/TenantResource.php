<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'domain' => $this->domain,
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
}
