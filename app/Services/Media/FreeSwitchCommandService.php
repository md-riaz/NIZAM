<?php

namespace App\Services\Media;

use App\Services\EslConnectionManager;

class FreeSwitchCommandService
{
    public function execute(string $command, array $arguments = [], bool $background = false): array
    {
        $esl = EslConnectionManager::fromConfig();

        if (! $esl->connect()) {
            return [
                'command' => $command,
                'arguments' => $arguments,
                'executed' => false,
                'error' => 'Unable to connect to FreeSWITCH ESL.',
            ];
        }

        $commandString = trim($command.' '.implode(' ', array_filter($arguments, fn ($value) => $value !== null && $value !== '')));
        $response = $background
            ? $esl->bgapi($commandString)
            : $esl->api($commandString);

        $esl->disconnect();

        return [
            'command' => $command,
            'arguments' => $arguments,
            'executed' => $response !== null,
            'background' => $background,
            'response' => $response,
        ];
    }
}
