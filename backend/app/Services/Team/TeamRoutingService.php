<?php

namespace App\Services\Team;

use App\Models\Team;

class TeamRoutingService
{
    public function resolveMembers(Team $team): array
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

        return match ($team->strategy) {
            'round_robin' => $this->roundRobin($members),
            'priority' => $this->priority($members),
            'simultaneous' => $members,
            default => $members,
        };
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
