<?php

namespace App\Services\Flow\Validation;

class NumberMatchNodeValidator extends AbstractNodeValidator
{
    public function validate(array $node): array
    {
        $errors = [];
        $mode = (string) data_get($node, 'config.mode', '');

        if (! in_array($mode, ['did', 'number_group'], true)) {
            $errors[] = 'Config [mode] must be one of did, number_group.';
        }

        $numbers = data_get($node, 'config.numbers', []);
        $groupId = data_get($node, 'config.group_id');

        if ($mode === 'did' && (! is_array($numbers) || $numbers === [])) {
            $errors[] = 'Number match node requires config.numbers for did mode.';
        }

        if ($mode === 'number_group' && ($groupId === null || $groupId === '')) {
            $errors[] = 'Number match node requires config.group_id for number_group mode.';
        }

        return $errors;
    }
}
