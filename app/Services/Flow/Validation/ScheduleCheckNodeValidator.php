<?php

namespace App\Services\Flow\Validation;

class ScheduleCheckNodeValidator extends AbstractNodeValidator
{
    public function validate(array $node): array
    {
        $errors = [];

        if ($error = $this->requireConfig($node, 'schedule_id', 'Schedule check node requires a schedule_id.')) {
            $errors[] = $error;
        }

        return $errors;
    }
}
