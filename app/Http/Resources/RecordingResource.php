<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecordingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'call_uuid' => $this->call_uuid,
            'file_name' => $this->file_name,
            'file_size' => $this->file_size,
            'format' => $this->format,
            'duration' => $this->duration,
            'direction' => $this->direction,
            'caller_id_number' => $this->caller_id_number,
            'destination_number' => $this->destination_number,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
