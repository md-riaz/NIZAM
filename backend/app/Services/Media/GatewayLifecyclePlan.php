<?php

namespace App\Services\Media;

final class GatewayLifecyclePlan
{
    public function __construct(
        public string $action,
        public string $reason,
        public ?string $profile,
        public ?string $outcome = null,
        public ?string $oldProfile = null,
        public bool $shouldWriteFile = false,
        public bool $shouldDeleteFile = false,
        public bool $shouldStart = false,
        public bool $shouldKill = false,
        public bool $shouldReloadXml = false,
        public bool $shouldRescan = false,
    ) {}
}
