<?php

namespace App\Services\Flow\Validation;

class EndCallNodeValidator extends AbstractNodeValidator
{
    public function validate(array $node): array
    {
        $errors = [];
        $cause = data_get($node, 'config.hangup_cause', data_get($node, 'config.cause'));

        if ($cause !== null && ! is_string($cause)) {
            $errors[] = 'Config [hangup_cause] must be a string.';
        }

        return $errors;
    }
}
