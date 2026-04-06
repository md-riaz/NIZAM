<?php

namespace App\Domain\Flow\Compile;

use App\Services\Flow\Validation\MenuNodeValidator;
use App\Services\Flow\Validation\RingTeamNodeValidator;
use App\Services\Flow\Validation\ScheduleCheckNodeValidator;
use App\Services\Flow\Validation\VoicemailNodeValidator;

/**
 * Node specification registry.
 *
 * Defines the compile contract for each node type:
 * - What IR instructions it produces
 * - Allowed outgoing transitions
 * - Whether it requires Lua helper
 * - Whether it is terminal
 *
 * Used by both compiler and validator for consistent behavior.
 */
class NodeSpecRegistry
{
    protected array $specs = [];

    public function __construct()
    {
        $this->registerBuiltinSpecs();
    }

    protected function registerBuiltinSpecs(): void
    {
        $this->register('start', new NodeSpec(
            type: 'start',
            irType: 'AnswerAndTransfer',
            transitions: ['next'],
            terminal: false,
            requiresLua: false,
        ));

        $this->register('schedule_check', new NodeSpec(
            type: 'schedule_check',
            irType: 'CheckSchedule',
            transitions: ['open', 'closed', 'break'],
            terminal: false,
            requiresLua: false,
            aliases: ['business_hours'],
            validator: ScheduleCheckNodeValidator::class,
        ));

        $this->register('menu', new NodeSpec(
            type: 'menu',
            irType: 'CollectDigits',
            transitions: ['digit_1', 'digit_2', 'digit_3', 'digit_4', 'digit_5', 'digit_6', 'digit_7', 'digit_8', 'digit_9', 'digit_0', 'timeout', 'invalid'],
            terminal: false,
            requiresLua: false,
            validator: MenuNodeValidator::class,
        ));

        $this->register('ring_team', new NodeSpec(
            type: 'ring_team',
            irType: 'BridgeTeam',
            transitions: ['answered', 'timeout', 'no_answer'],
            terminal: false,
            requiresLua: true,
            validator: RingTeamNodeValidator::class,
        ));

        $this->register('voicemail', new NodeSpec(
            type: 'voicemail',
            irType: 'Voicemail',
            transitions: ['completed', 'skipped'],
            terminal: false,
            requiresLua: false,
            validator: VoicemailNodeValidator::class,
        ));

        $this->register('hangup', new NodeSpec(
            type: 'hangup',
            irType: 'Hangup',
            transitions: [],
            terminal: true,
            requiresLua: false,
            aliases: ['end'],
        ));
    }

    public function register(string $type, NodeSpec $spec): void
    {
        $this->specs[$type] = $spec;
    }

    public function canonicalType(string $type): ?string
    {
        if (isset($this->specs[$type])) {
            return $type;
        }

        foreach ($this->specs as $canonicalType => $spec) {
            if (in_array($type, $spec->aliases, true)) {
                return $canonicalType;
            }
        }

        return null;
    }

    public function get(string $type): ?NodeSpec
    {
        $canonicalType = $this->canonicalType($type);

        return $canonicalType ? ($this->specs[$canonicalType] ?? null) : null;
    }

    public function has(string $type): bool
    {
        return $this->canonicalType($type) !== null;
    }

    public function validatorFor(string $type): ?string
    {
        return $this->get($type)?->validator;
    }

    public function all(): array
    {
        return $this->specs;
    }

    /**
     * Get all IR types supported.
     */
    public function getIrTypes(): array
    {
        return array_map(fn($spec) => $spec->irType, $this->specs);
    }
}
