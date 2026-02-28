<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Extension;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Agent>
 */
class AgentFactory extends Factory
{
    protected $model = Agent::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'extension_id' => Extension::factory(),
            'name' => fake()->name(),
            'role' => Agent::ROLE_AGENT,
            'state' => Agent::STATE_OFFLINE,
            'pause_reason' => null,
            'state_changed_at' => now(),
            'is_active' => true,
        ];
    }

    public function available(): static
    {
        return $this->state(fn () => ['state' => Agent::STATE_AVAILABLE]);
    }

    public function busy(): static
    {
        return $this->state(fn () => ['state' => Agent::STATE_BUSY]);
    }

    public function paused(string $reason = Agent::PAUSE_BREAK): static
    {
        return $this->state(fn () => [
            'state' => Agent::STATE_PAUSED,
            'pause_reason' => $reason,
        ]);
    }

    public function supervisor(): static
    {
        return $this->state(fn () => ['role' => Agent::ROLE_SUPERVISOR]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
