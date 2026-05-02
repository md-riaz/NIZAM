<?php

namespace App\Services\Team;

use App\Models\Team;

class TeamRoutingService
{
    protected function normalizeStrategy(?string $strategy, Team $team): string
    {
        return match ($strategy ?: $team->strategy) {
            'sequence' => 'sequential',
            'enterprise' => 'round_robin',
            null, '' => 'simultaneous',
            default => (string) ($strategy ?: $team->strategy),
        };
    }

    public function resolveMembers(Team $team, ?string $strategy = null): array
    {
        $members = $team->members()
            ->where('is_active', true)
            ->orderBy('priority')
            ->get()
            ->map(fn ($member) => [
                'endpoint_type' => $member->endpoint_type,
                'endpoint_id' => $member->endpoint_id,
                'priority' => $member->priority,
            ])
            ->values()
            ->all();

        $effectiveStrategy = $this->normalizeStrategy($strategy, $team);

        return match ($effectiveStrategy) {
            'round_robin', 'sequential' => $this->roundRobin($members),
            'priority' => $this->priority($members),
            'simultaneous' => $members,
            default => $members,
        };
    }

    public function buildDialString(Team $team, string $domain, ?string $strategy = null): string
    {
        $members = $this->resolveMembers($team, $strategy);
        $separator = match ($this->normalizeStrategy($strategy, $team)) {
            'simultaneous' => ',',
            default => '|',
        };

        $destinations = collect($members)
            ->map(function (array $member) use ($team, $domain) {
                $endpointType = $member['endpoint_type'] ?? null;
                $endpointId = $member['endpoint_id'] ?? null;

                if (! is_string($endpointId) || $endpointId === '') {
                    return null;
                }

                if ($endpointType === 'extension' || $endpointType === \App\Models\Extension::class) {
                    $extension = $team->organization->extensions()->whereKey($endpointId)->where('is_active', true)->first();

                    return $extension ? 'user/'.$extension->extension.'@'.$domain : null;
                }

                if ($endpointType === 'agent' || $endpointType === \App\Models\Agent::class) {
                    $agent = $team->organization->agents()->whereKey($endpointId)->where('is_active', true)->first();
                    $extension = $agent?->extension;

                    return $extension && $extension->is_active ? 'user/'.$extension->extension.'@'.$domain : null;
                }

                return null;
            })
            ->filter()
            ->values();

        return $destinations->implode($separator);
    }

    protected function roundRobin(array $members): array
    {
        if (count($members) <= 1) {
            return $members;
        }

        $first = array_shift($members);
        $members[] = $first;

        return $members;
    }

    protected function priority(array $members): array
    {
        usort($members, function ($left, $right) {
            return (int) ($left['priority'] ?? 9999) <=> (int) ($right['priority'] ?? 9999);
        });

        return $members;
    }
}
