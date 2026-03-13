<?php

namespace App\Services\Flow\Validation;

interface NodeValidator
{
    public function validate(array $node): array;
}
