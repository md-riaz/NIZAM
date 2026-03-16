<?php

namespace Database\Factories;

use App\Models\FlowEdge;
use App\Models\FlowVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

class FlowEdgeFactory extends Factory
{
    protected $model = FlowEdge::class;

    public function definition(): array
    {
        return [
            'flow_version_id' => FlowVersion::factory(),
            'source_node_id' => $this->faker->uuid,
            'target_node_id' => $this->faker->uuid,
            'condition' => 'default',
        ];
    }
}
