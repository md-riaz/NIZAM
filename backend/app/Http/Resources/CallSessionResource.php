<?php

namespace App\Http\Resources;

use App\Models\CallDeliveryAttempt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CallSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $winnerAttemptId = data_get($this->variables, 'winner_attempt_id');
        $winnerLegUuid = data_get($this->variables, 'winner_leg_uuid');
        $winnerCommittedAt = data_get($this->variables, 'winner_committed_at');
        $winningAttempt = $this->whenLoaded('winningDeliveryAttempt');
        $loadedAttempts = $this->whenLoaded('deliveryAttempts');
        $resolvedWinner = $loadedAttempts instanceof \Illuminate\Support\Collection
            ? $loadedAttempts->firstWhere('id', $winnerAttemptId)
            : null;

        if (! $resolvedWinner instanceof CallDeliveryAttempt && $winningAttempt instanceof CallDeliveryAttempt) {
            $resolvedWinner = $winningAttempt;
        }

        if (! $winnerAttemptId && $resolvedWinner instanceof CallDeliveryAttempt) {
            $winnerAttemptId = $resolvedWinner->id;
        }

        if (! $winnerLegUuid && $resolvedWinner instanceof CallDeliveryAttempt) {
            $winnerLegUuid = $resolvedWinner->freeswitch_leg_uuid;
        }

        if (! $winnerCommittedAt && $resolvedWinner instanceof CallDeliveryAttempt) {
            $winnerCommittedAt = $resolvedWinner->answered_at ?? $resolvedWinner->updated_at;
        }

        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'call_uuid' => $this->call_uuid,
            'did_id' => $this->did_id,
            'flow_id' => $this->flowVersion?->flow_id,
            'flow_version_id' => $this->flow_version_id,
            'current_node_id' => $this->current_node_id,
            'state' => $this->state,
            'variables' => $this->variables,
            'lock_version' => $this->lock_version,
            'winner' => [
                'attempt_id' => $winnerAttemptId,
                'leg_uuid' => $winnerLegUuid,
                'committed_at' => $winnerCommittedAt,
                'attempt' => $resolvedWinner instanceof CallDeliveryAttempt
                    ? (new CallDeliveryAttemptResource($resolvedWinner))->resolve($request)
                    : null,
            ],
            'delivery_attempts' => CallDeliveryAttemptResource::collection($loadedAttempts),
            'push_notification_logs' => PushNotificationLogResource::collection($this->whenLoaded('pushNotificationLogs')),
            'trace_events' => CallTraceEventResource::collection($this->whenLoaded('traceEvents')),
            'started_at' => $this->started_at,
            'ended_at' => $this->ended_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
