<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HolidayCalendarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'timezone' => $this->timezone,
            'is_active' => $this->is_active,
            'holidays' => $this->whenLoaded('holidays'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
