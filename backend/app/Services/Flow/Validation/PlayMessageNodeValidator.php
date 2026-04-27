<?php

namespace App\Services\Flow\Validation;

class PlayMessageNodeValidator extends AbstractNodeValidator
{
    public function validate(array $node): array
    {
        $errors = [];
        $prompt = data_get($node, 'config.prompt', data_get($node, 'config.message'));
        $mediaId = data_get($node, 'config.media_id');

        if (($prompt === null || $prompt === '') && ($mediaId === null || $mediaId === '')) {
            $errors[] = 'Play message node requires a prompt.';
        }

        $destinationType = data_get($node, 'config.destination_type');
        $destinationValue = data_get($node, 'config.destination_value');

        if (($destinationType === null || $destinationType === '') !== ($destinationValue === null || $destinationValue === '')) {
            $errors[] = 'Play message node destination_type and destination_value must be provided together.';
        }

        return $errors;
    }
}
