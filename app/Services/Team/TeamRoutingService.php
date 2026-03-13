<?php

namespace App\Services\Team;

use App\Models\RingGroup;

class TeamRoutingService
{
    public function resolveMembers(RingGroup $ringGroup): array
    {
        $members = collect($ringGroup->members ?? [])
            ->filter(fn ($member) => ! empty($member['extension'] ?? $member['id'] ?? null))
            ->values()
            ->all();

        return match ($ringGroup->strategy) {
            'round_robin' => $this->roundRobin($members),
            'simultaneous', 'ring_all' => $members,
            'priority' => $this->priority($members),
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
