<?php

namespace App\Services\Flow\Validation;

class RingTeamNodeValidator extends AbstractNodeValidator
{
    public function validate(array $node): array
    {
        $errors = [];

        if ($error = $this->requireConfig($node, 'team_id', 'Ring team node requires a team_id.')) {
            $errors[] = $error;
        }

        if ($error = $this->validateTimeout($node)) {
            $errors[] = $error;
        }

        return $errors;
    }
}
