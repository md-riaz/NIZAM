<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Masks sensitive data (SIP passwords, tokens, API keys) in log output.
 *
 * Add to your logging stack in config/logging.php:
 *
 *   'daily' => [
 *       'driver' => 'daily',
 *       'path'   => storage_path('logs/laravel.log'),
 *       'tap'    => [App\Logging\SensitiveDataSanitizerTap::class],
 *   ],
 */
class SensitiveDataSanitizer implements ProcessorInterface
{
    /**
     * Patterns to mask in log messages and context values.
     * Each entry: [regex pattern, replacement].
     *
     * @var list<array{0: string, 1: string}>
     */
    protected array $patterns = [
        // SIP password fields (password=xxx or passwd=xxx)
        ['/(\bpassw(?:or)?d\s*[:=]\s*)["\']?[^\s"\'&,}\]]+/i', '$1****'],
        // Bearer tokens
        ['/(\bBearer\s+)[A-Za-z0-9\-._~+\/]+=*/i', '$1****'],
        // API key parameters (?api_key=xxx or &apikey=xxx)
        ['/(\bapi[_-]?key\s*[:=]\s*)["\']?[^\s"\'&,}\]]+/i', '$1****'],
        // Authorization headers
        ['/(\bAuthorization\s*:\s*(?:Basic|Digest)\s+)[^\s]+/i', '$1****'],
        // Secret fields in JSON-like context (secret, signing_secret, etc.)
        ['/(["\'](?>signing_)?secret["\']?\s*[:=]\s*)["\']?[^\s"\'&,}\]]+/i', '$1****'],
        // Credit card numbers (basic 13-19 digit sequences)
        ['/\b(\d{4})\d{5,11}(\d{4})\b/', '$1********$2'],
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        $message = $this->sanitize($record->message);
        $context = $this->sanitizeArray($record->context);
        $extra = $this->sanitizeArray($record->extra);

        return $record->with(message: $message, context: $context, extra: $extra);
    }

    /**
     * Apply all sanitization patterns to a string.
     */
    protected function sanitize(string $value): string
    {
        foreach ($this->patterns as [$pattern, $replacement]) {
            $value = preg_replace($pattern, $replacement, $value);
        }

        return $value;
    }

    /**
     * Recursively sanitize an array of values.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function sanitizeArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = $this->sanitize($value);
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitizeArray($value);
            }
        }

        return $data;
    }
}
