<?php

namespace App\Services\Flow\Validation;

class MenuNodeValidator extends AbstractNodeValidator
{
    public function validate(array $node): array
    {
        $errors = [];

        if ($error = $this->requireConfig($node, 'prompt', 'Menu node requires a prompt.')) {
            $errors[] = $error;
        }

        $digits = data_get($node, 'config.digits');

        if (! is_array($digits) || $digits === []) {
            $errors[] = 'Menu node requires at least one allowed digit.';
        }

        if ($error = $this->validateTimeout($node)) {
            $errors[] = $error;
        }

        return $errors;
    }
}
