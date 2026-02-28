<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExtensionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'extension' => $this->extension,
            'directory_first_name' => $this->directory_first_name,
            'directory_last_name' => $this->directory_last_name,
            'effective_caller_id_name' => $this->effective_caller_id_name,
            'effective_caller_id_number' => $this->effective_caller_id_number,
            'outbound_caller_id_name' => $this->outbound_caller_id_name,
            'outbound_caller_id_number' => $this->outbound_caller_id_number,
            'voicemail_enabled' => $this->voicemail_enabled,
            'voicemail_pin' => $this->voicemail_pin,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
