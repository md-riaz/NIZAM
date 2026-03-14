<?php

namespace App\Domain\Flow\Compile;

/**
 * Specification for a node type in the compiler.
 */
class NodeSpec
{
    public function __construct(
        public string $type,
        public string $irType,
        public array $transitions,
        public bool $terminal,
        public bool $requiresLua,
    ) {}

    /**
     * Check if a transition result is valid for this node type.
     */
    public function isValidTransition(string $result): bool
    {
        return in_array($result, $this->transitions, true);
    }

    /**
     * Get all valid transition results.
     */
    public function getValidTransitions(): array
    {
        return $this->transitions;
    }
}
