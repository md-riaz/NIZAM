<?php

namespace App\Http\Resources;

use App\Models\CallRoutingPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CallBlockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var CallRoutingPolicy $policy */
        $policy = $this->resource;

        return [
            'id' => $policy->id,
            'organization_id' => $policy->organization_id,
            'name' => $policy->name,
            'description' => $policy->description,
            'number' => $this->blockedNumber($policy),
            'action' => 'reject',
            'is_active' => $policy->is_active,
            'created_at' => $policy->created_at,
            'updated_at' => $policy->updated_at,
        ];
    }

    private function blockedNumber(CallRoutingPolicy $policy): ?string
    {
        foreach ($policy->conditions ?? [] as $condition) {
            if (($condition['type'] ?? null) !== 'blacklist') {
                continue;
            }

            $numbers = $condition['params']['numbers'] ?? [];

            return preg_replace('/\D+/', '', (string) ($numbers[0] ?? '')) ?: null;
        }

        return null;
    }
}
