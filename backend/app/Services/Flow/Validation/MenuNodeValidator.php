<?php

namespace App\Services\Flow\Validation;

class MenuNodeValidator extends AbstractNodeValidator
{
    public function validate(array $node): array
    {
        $errors = [];

        $prompt = data_get($node, 'config.prompt') ?? data_get($node, 'config.greeting');

        if ($prompt === null || $prompt === '') {
            $errors[] = 'Menu node requires a prompt.';
        }

        $digits = data_get($node, 'config.digits');
        $options = data_get($node, 'config.options');

        if (is_array($options) && $options !== []) {
            $digits = array_values(array_filter(array_map(
                static fn ($option) => isset($option['digit']) ? trim((string) $option['digit']) : null,
                $options,
            )));
        }

        if (! is_array($digits) || $digits === []) {
            $errors[] = 'Menu node requires at least one allowed digit.';
        }

        if ($error = $this->validateTimeout($node)) {
            $errors[] = $error;
        }

        return $errors;
    }
}
