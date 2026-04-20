<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'holiday_calendar_id' => $this->holiday_calendar_id,
            'name' => $this->name,
            'timezone' => $this->timezone,
            'is_active' => $this->is_active,
            'rules' => $this->whenLoaded('rules'),
            'breaks' => $this->whenLoaded('breaks'),
            'exceptions' => $this->whenLoaded('exceptions'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
