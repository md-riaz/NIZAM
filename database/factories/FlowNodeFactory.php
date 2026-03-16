<?php

namespace Database\Factories;

use App\Models\FlowNode;
use App\Models\FlowVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

class FlowNodeFactory extends Factory
{
    protected $model = FlowNode::class;

    public function definition(): array
    {
        return [
            'flow_version_id' => FlowVersion::factory(),
            'name' => $this->faker->word,
            'type' => 'start',
            'config_json' => [],
        ];
    }
}
