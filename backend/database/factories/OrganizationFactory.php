<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        $company = fake()->unique()->company();

        return [
            'name' => $company,
            'domain' => fake()->unique()->domainName(),
            'settings' => [],
            'max_extensions' => fake()->numberBetween(1, 100),
            'max_concurrent_calls' => 0,
            'max_dids' => 0,
            'max_ring_groups' => 0,
            'is_active' => true,
            'status' => Organization::STATUS_ACTIVE,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function trial(): static
    {
        return $this->state(fn () => ['status' => Organization::STATUS_TRIAL]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => Organization::STATUS_SUSPENDED, 'is_active' => false]);
    }

    public function terminated(): static
    {
        return $this->state(fn () => ['status' => Organization::STATUS_TERMINATED, 'is_active' => false]);
    }
}
