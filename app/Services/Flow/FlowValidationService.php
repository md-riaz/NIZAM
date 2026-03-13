<?php

namespace App\Services\Flow;

use App\Services\Flow\Validation\MenuNodeValidator;
use App\Services\Flow\Validation\NodeValidator;
use App\Services\Flow\Validation\RingTeamNodeValidator;
use App\Services\Flow\Validation\ScheduleCheckNodeValidator;
use App\Services\Flow\Validation\VoicemailNodeValidator;
use InvalidArgumentException;

class FlowValidationService
{
    public function validateDefinition(array $nodes): array
    {
        $errors = [];

        foreach ($nodes as $index => $node) {
            $type = (string) ($node['type'] ?? '');

            if ($type === '') {
                $errors["nodes.{$index}"][] = 'Node type is required.';
                continue;
            }

            try {
                $validator = $this->validatorFor($type);
            } catch (InvalidArgumentException $e) {
                $errors["nodes.{$index}"][] = $e->getMessage();
                continue;
            }

            $nodeErrors = $validator->validate($node);

            if ($nodeErrors !== []) {
                $errors["nodes.{$index}"] = $nodeErrors;
            }
        }

        return $errors;
    }

    protected function validatorFor(string $type): NodeValidator
    {
        return match ($type) {
            'menu' => app(MenuNodeValidator::class),
            'ring_team' => app(RingTeamNodeValidator::class),
            'schedule_check', 'business_hours' => app(ScheduleCheckNodeValidator::class),
            'voicemail' => app(VoicemailNodeValidator::class),
            'start', 'hangup', 'end' => new class implements NodeValidator {
                public function validate(array $node): array
                {
                    return [];
                }
            },
            default => throw new InvalidArgumentException("Unsupported node type [{$type}] for validation."),
        };
    }
}
