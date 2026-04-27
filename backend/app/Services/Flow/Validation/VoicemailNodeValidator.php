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

        $destinationType = data_get($node, 'config.destination_type');
        $destinationValue = data_get($node, 'config.destination_value');

        if (($destinationType === null || $destinationType === '') !== ($destinationValue === null || $destinationValue === '')) {
            $errors[] = 'Voicemail node destination_type and destination_value must be provided together.';
        }

        return $errors;
    }
}
