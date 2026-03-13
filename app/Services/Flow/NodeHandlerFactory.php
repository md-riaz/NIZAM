<?php

namespace App\Services\Flow;

use App\Domain\Flow\Contracts\NodeHandler;
use App\Services\Flow\Nodes\HangupNodeHandler;
use App\Services\Flow\Nodes\MenuNodeHandler;
use App\Services\Flow\Nodes\RingTeamNodeHandler;
use App\Services\Flow\Nodes\ScheduleCheckNodeHandler;
use App\Services\Flow\Nodes\StartNodeHandler;
use App\Services\Flow\Nodes\VoicemailNodeHandler;
use InvalidArgumentException;

class NodeHandlerFactory
{
    public function make(string $type): NodeHandler
    {
        return match ($type) {
            'start' => app(StartNodeHandler::class),
            'schedule_check', 'business_hours' => app(ScheduleCheckNodeHandler::class),
            'menu' => app(MenuNodeHandler::class),
            'ring_team' => app(RingTeamNodeHandler::class),
            'voicemail' => app(VoicemailNodeHandler::class),
            'hangup', 'end' => app(HangupNodeHandler::class),
            default => throw new InvalidArgumentException("Unsupported node type [{$type}]."),
        };
    }
}
