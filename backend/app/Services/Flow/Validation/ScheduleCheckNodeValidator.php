<?php

namespace App\Services\Flow\Validation;

class ScheduleCheckNodeValidator extends AbstractNodeValidator
{
    public function validate(array $node): array
    {
        $errors = [];

        $scheduleMode = (string) data_get($node, 'config.schedule_mode', '');

        if ($scheduleMode === 'organization_default') {
            return $errors;
        }

        if ($scheduleMode === 'custom' || $scheduleMode === '') {
            if ($error = $this->requireConfig($node, 'schedule_id', 'Schedule check node requires a schedule_id.')) {
                $errors[] = $error;
            }
        }

        if ($scheduleMode !== '' && ! in_array($scheduleMode, ['organization_default', 'custom'], true)) {
            $errors[] = 'Config [schedule_mode] must be one of organization_default, custom.';
        }

        return $errors;
    }
}
