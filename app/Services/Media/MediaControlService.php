<?php

namespace App\Services\Media;

use App\Models\CallSession;
use App\Services\Call\TraceWriter;

class MediaControlService
{
    public function __construct(
        protected TraceWriter $traceWriter,
    ) {}

    public function playback(CallSession $callSession, string $prompt): array
    {
        $payload = [
            'command' => 'playback',
            'prompt' => $prompt,
        ];

        $this->traceWriter->write($callSession, 'media.playback.requested', $payload);

        return $payload;
    }

    public function ringTeam(CallSession $callSession, string $teamId, int $timeout = 20, array $members = []): array
    {
        $payload = [
            'command' => 'ring_team',
            'team_id' => $teamId,
            'timeout' => $timeout,
            'members' => $members,
        ];

        $this->traceWriter->write($callSession, 'media.ring_team.requested', $payload);

        return $payload;
    }

    public function hangup(CallSession $callSession, string $cause = 'NORMAL_CLEARING'): array
    {
        $payload = [
            'command' => 'hangup',
            'cause' => $cause,
        ];

        $this->traceWriter->write($callSession, 'media.hangup.requested', $payload);

        return $payload;
    }
}
