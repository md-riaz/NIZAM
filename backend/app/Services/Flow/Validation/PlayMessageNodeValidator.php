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

        return $errors;
    }
}
