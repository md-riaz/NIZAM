<?php

namespace App\Services\Flow\Validation;

abstract class AbstractNodeValidator implements NodeValidator
{
    protected function requireConfig(array $node, string $key, string $message): ?string
    {
        $value = data_get($node, 'config.'.$key);

        if ($value === null || $value === '') {
            return $message;
        }

        return null;
    }

    protected function validateTimeout(array $node, string $key = 'timeout', int $min = 1, int $max = 300): ?string
    {
        $value = data_get($node, 'config.'.$key);

        if ($value === null) {
            return null;
        }

        if (! is_numeric($value)) {
            return "Config [{$key}] must be numeric.";
        }

        $value = (int) $value;

        if ($value < $min || $value > $max) {
            return "Config [{$key}] must be between {$min} and {$max}.";
        }

        return null;
    }
}
