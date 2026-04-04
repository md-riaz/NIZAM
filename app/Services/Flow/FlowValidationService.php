<?php

namespace App\Services\Flow;

use App\Domain\Flow\Compile\NodeSpecRegistry;
use App\Services\Flow\Validation\NodeValidator;
use InvalidArgumentException;

class FlowValidationService
{
    public function __construct(
        protected NodeSpecRegistry $nodeSpecRegistry,
    ) {}

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
        $validatorClass = $this->nodeSpecRegistry->validatorFor($type);

        if ($validatorClass) {
            return app($validatorClass);
        }

        if ($this->nodeSpecRegistry->has($type)) {
            return new class implements NodeValidator {
                public function validate(array $node): array
                {
                    return [];
                }
            };
        }

        throw new InvalidArgumentException("Unsupported node type [{$type}] for validation.");
    }
}
