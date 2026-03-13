<?php

namespace App\Services\Call;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use RuntimeException;

class CallLockService
{
    public function __construct(
        protected CacheRepository $cache,
    ) {}

    public function withLock(string $callUuid, callable $callback, int $seconds = 10): mixed
    {
        $lock = $this->cache->lock('call-lock:'.$callUuid, $seconds);

        if (! $lock->get()) {
            throw new RuntimeException('Unable to acquire call lock for '.$callUuid);
        }

        try {
            return $callback();
        } finally {
            optional($lock)->release();
        }
    }
}
