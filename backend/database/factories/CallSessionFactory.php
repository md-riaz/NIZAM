<?php

namespace Database\Factories;

use App\Models\CallSession;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CallSession>
 */
class CallSessionFactory extends Factory
{
    protected $model = CallSession::class;

    public function definition(): array
    {
        return [
            'call_uuid' => fake()->uuid(),
            'tenant_id' => Tenant::factory(),
            'did_id' => null,
            'flow_version_id' => null,
            'current_node_id' => null,
            'state' => 'initiated',
            'variables' => ['source' => 'factory'],
            'lock_version' => 0,
            'started_at' => now(),
            'ended_at' => null,
        ];
    }
}
