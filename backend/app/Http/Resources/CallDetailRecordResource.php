<?php

namespace App\Http\Resources;

use App\Models\Recording;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CallDetailRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
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
            // The raw storage path is only useful to an operator who may access
            // the audio anyway, so it follows the recordings permission.
            'recording_path' => $this->when(
                $request->user()?->can('viewAny', Recording::class) ?? false,
                fn () => $this->recording_path
            ),
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
            // Shaped through RecordingResource rather than dumped raw, so the
            // client gets a stable contract and no internal file paths.
            'recordings' => RecordingResource::collection($this->whenLoaded('recordings')),
            'has_recording' => $this->resolveHasRecording(),
            // Present only when the call was traced through the delivery
            // pipeline; lets call history link to the interaction journey.
            'call_session_id' => $this->whenLoaded('callSession', fn () => $this->callSession?->id),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Whether audio exists for this call.
     *
     * Prefers loaded Recording rows, falling back to the recording_path column
     * FreeSWITCH writes — a call can have a path before the file is ingested as
     * a Recording row, and the UI should still indicate audio is expected.
     */
    private function resolveHasRecording(): bool
    {
        if ($this->relationLoaded('recordings')) {
            return $this->recordings->isNotEmpty() || filled($this->recording_path);
        }

        return filled($this->recording_path);
    }
}
