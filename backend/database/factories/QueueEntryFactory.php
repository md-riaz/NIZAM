<?php

namespace Database\Factories;

use App\Models\Queue;
use App\Models\QueueEntry;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QueueEntry>
 */
class QueueEntryFactory extends Factory
{
    protected $model = QueueEntry::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'queue_id' => Queue::factory(),
            'call_uuid' => (string) Str::uuid(),
            'caller_id_number' => fake()->e164PhoneNumber(),
            'caller_id_name' => fake()->name(),
            'status' => QueueEntry::STATUS_WAITING,
            'agent_id' => null,
            'join_time' => now(),
            'answer_time' => null,
            'abandon_time' => null,
            'wait_duration' => null,
            'abandon_reason' => null,
        ];
    }

    public function answered(): static
    {
        return $this->state(fn () => [
            'status' => QueueEntry::STATUS_ANSWERED,
            'answer_time' => now(),
            'wait_duration' => fake()->numberBetween(5, 120),
        ]);
    }

    public function abandoned(): static
    {
        return $this->state(fn () => [
            'status' => QueueEntry::STATUS_ABANDONED,
            'abandon_time' => now(),
            'wait_duration' => fake()->numberBetween(10, 300),
            'abandon_reason' => 'caller_hangup',
        ]);
    }
}
