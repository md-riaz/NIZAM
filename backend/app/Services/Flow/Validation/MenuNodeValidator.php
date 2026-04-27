<?php

namespace App\Services\Flow\Validation;

class MenuNodeValidator extends AbstractNodeValidator
{
    public function validate(array $node): array
    {
        $errors = [];

        $prompt = data_get($node, 'config.prompt') ?? data_get($node, 'config.greeting');
        $promptMediaId = data_get($node, 'config.media_id') ?? data_get($node, 'config.prompt_media_id');

        if (($prompt === null || $prompt === '') && ($promptMediaId === null || $promptMediaId === '')) {
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

        $destinationType = data_get($node, 'config.destination_type');
        $destinationValue = data_get($node, 'config.destination_value');

        if (($destinationType === null || $destinationType === '') !== ($destinationValue === null || $destinationValue === '')) {
            $errors[] = 'Menu node destination_type and destination_value must be provided together.';
        }

        return $errors;
    }
}
