<?php

namespace App\Services\Media;

class FreeSwitchCommandService
{
    public function execute(string $command, array $arguments = []): array
    {
        return [
            'command' => $command,
            'arguments' => $arguments,
            'executed' => false,
        ];
    }
}
