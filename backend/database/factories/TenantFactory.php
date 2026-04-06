<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $company = fake()->unique()->company();

        return [
            'name' => $company,
            'domain' => fake()->unique()->domainName(),
            'slug' => Str::slug($company).'-'.fake()->unique()->randomNumber(4),
            'settings' => [],
            'max_extensions' => fake()->numberBetween(1, 100),
            'max_concurrent_calls' => 0,
            'max_dids' => 0,
            'max_ring_groups' => 0,
            'is_active' => true,
            'status' => Tenant::STATUS_ACTIVE,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function trial(): static
    {
        return $this->state(fn () => ['status' => Tenant::STATUS_TRIAL]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => Tenant::STATUS_SUSPENDED, 'is_active' => false]);
    }

    public function terminated(): static
    {
        return $this->state(fn () => ['status' => Tenant::STATUS_TERMINATED, 'is_active' => false]);
    }
}
