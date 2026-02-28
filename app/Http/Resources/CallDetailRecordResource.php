<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CallDetailRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'uuid' => $this->uuid,
            'caller_id_name' => $this->caller_id_name,
            'caller_id_number' => $this->caller_id_number,
            'destination_number' => $this->destination_number,
            'context' => $this->context,
            'start_stamp' => $this->start_stamp,
            'answer_stamp' => $this->answer_stamp,
            'end_stamp' => $this->end_stamp,
            'duration' => $this->duration,
            'billsec' => $this->billsec,
            'hangup_cause' => $this->hangup_cause,
            'direction' => $this->direction,
            'recording_path' => $this->recording_path,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
