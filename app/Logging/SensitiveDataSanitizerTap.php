<?php

namespace App\Logging;

use Illuminate\Log\Logger;

/**
 * Laravel logging tap that adds the SensitiveDataSanitizer processor.
 *
 * Usage in config/logging.php:
 *
 *   'channels' => [
 *       'daily' => [
 *           'driver' => 'daily',
 *           'path'   => storage_path('logs/laravel.log'),
 *           'tap'    => [App\Logging\SensitiveDataSanitizerTap::class],
 *       ],
 *   ],
 */
class SensitiveDataSanitizerTap
{
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->pushProcessor(new SensitiveDataSanitizer);
        }
    }
}
