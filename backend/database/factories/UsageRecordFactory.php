<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\UsageRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UsageRecord>
 */
class UsageRecordFactory extends Factory
{
    protected $model = UsageRecord::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'metric' => fake()->randomElement([
                UsageRecord::METRIC_CALL_MINUTES,
                UsageRecord::METRIC_CONCURRENT_CALL_PEAK,
                UsageRecord::METRIC_RECORDING_STORAGE,
                UsageRecord::METRIC_ACTIVE_DEVICES,
                UsageRecord::METRIC_ACTIVE_EXTENSIONS,
            ]),
            'value' => fake()->randomFloat(4, 0, 1000),
            'metadata' => [],
            'recorded_date' => fake()->date(),
        ];
    }
}
