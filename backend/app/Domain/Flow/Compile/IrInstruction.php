<?php

namespace App\Domain\Flow\Compile;

/**
 * Intermediate Representation (IR) for compiled flow instructions.
 *
 * This is NOT the runtime. It is the compiler's structured output
 * before XML/Lua generation. Allows validation, testing, and swapping
 * XML strategies without rewriting graph translation.
 */
class IrInstruction
{
    public function __construct(
        public string $type,
        public array $params = [],
        public ?string $label = null,
        public array $transitions = [],
    ) {}

    /**
     * Create a new IR instruction.
     */
    public static function make(string $type, array $params = []): self
    {
        return new self($type, $params);
    }

    /**
     * Add a transition (branch) from this instruction.
     */
    public function withTransition(string $result, string $targetLabel): self
    {
        $this->transitions[$result] = $targetLabel;
        return $this;
    }

    /**
     * Set a label for this instruction (for branch targets).
     */
    public function withLabel(string $label): self
    {
        $this->label = $label;
        return $this;
    }
}
