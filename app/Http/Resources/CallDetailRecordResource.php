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
            'call_type' => $this->call_type,
            'recording_path' => $this->recording_path,
            'sip_user_agent' => $this->sip_user_agent,
            'remote_media_ip' => $this->remote_media_ip,
            'quality' => [
                'score' => $this->quality_score,
                'mos' => $this->mos_score,
                'packet_loss' => $this->packet_loss,
                'jitter_ms' => $this->jitter,
                'latency_ms' => $this->latency,
            ],
            'tags' => $this->tags,
            'metadata' => $this->metadata,
            'enrichment' => $this->whenLoaded('enrichment', fn () => [
                'destination_country' => $this->enrichment->destination_country,
                'destination_city' => $this->enrichment->destination_city,
                'carrier_name' => $this->enrichment->carrier_name,
                'number_type' => $this->enrichment->number_type,
                'enriched_at' => $this->enrichment->enriched_at,
            ]),
            'recordings' => $this->whenLoaded('recordings'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
