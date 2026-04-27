<?php

namespace App\Services\Flow\Validation;

class CallerMatchNodeValidator extends AbstractNodeValidator
{
    public function validate(array $node): array
    {
        $errors = [];
        $mode = (string) data_get($node, 'config.mode', '');

        if (! in_array($mode, ['anonymous', 'exact', 'prefix', 'vip_list'], true)) {
            $errors[] = 'Config [mode] must be one of anonymous, exact, prefix, vip_list.';
        }

        $numbers = data_get($node, 'config.numbers', []);
        $listId = data_get($node, 'config.list_id');

        if (in_array($mode, ['exact', 'prefix'], true) && (! is_array($numbers) || $numbers === [])) {
            $errors[] = 'Caller match node requires config.numbers for exact and prefix modes.';
        }

        if ($mode === 'vip_list' && ($listId === null || $listId === '')) {
            $errors[] = 'Caller match node requires config.list_id for vip_list mode.';
        }

        return $errors;
    }
}
