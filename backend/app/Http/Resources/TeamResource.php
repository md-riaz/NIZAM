<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'schedule_id' => $this->schedule_id,
            'holiday_calendar_id' => $this->holiday_calendar_id,
            'name' => $this->name,
            'strategy' => $this->strategy,
            'timeout' => $this->timeout,
            'is_active' => $this->is_active,
            'members' => $this->whenLoaded('members'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
