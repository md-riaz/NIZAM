<?php

namespace Database\Factories;

use App\Models\Flow;
use App\Models\FlowVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

class FlowVersionFactory extends Factory
{
    protected $model = FlowVersion::class;

    public function definition(): array
    {
        return [
            'flow_id' => Flow::factory(),
            'version_number' => 1,
            'definition_checksum' => $this->faker->sha256,
            'status' => 'draft',
            'is_published' => false,
            'runtime_mode' => 'compiled',
            'definition_json' => [],
        ];
    }
}
