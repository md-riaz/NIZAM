<?php

namespace App\Services\Flow\Validation;

class VoicemailNodeValidator extends AbstractNodeValidator
{
    public function validate(array $node): array
    {
        $errors = [];

        if ($error = $this->requireConfig($node, 'mailbox', 'Voicemail node requires a mailbox.')) {
            $errors[] = $error;
        }

        return $errors;
    }
}
