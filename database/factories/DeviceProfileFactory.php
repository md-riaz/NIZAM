<?php

namespace Database\Factories;

use App\Models\DeviceProfile;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeviceProfile>
 */
class DeviceProfileFactory extends Factory
{
    protected $model = DeviceProfile::class;

    public function definition(): array
    {
        $vendors = [
            'polycom' => ['VVX 150', 'VVX 250', 'VVX 450', 'Trio 8500'],
            'yealink' => ['T54W', 'T57W', 'T46U', 'T43U'],
            'grandstream' => ['GXP2170', 'GRP2614', 'GRP2616'],
            'cisco' => ['SPA504G', 'SPA525G', '8841', '7841'],
        ];

        $vendor = fake()->randomElement(array_keys($vendors));
        $model = fake()->randomElement($vendors[$vendor]);
        $location = fake()->randomElement([
            'Lobby', 'Conference Room', 'Reception', 'Office',
            'Break Room', 'Warehouse', 'Lab',
        ]);

        return [
            'tenant_id' => Tenant::factory(),
            'name' => ucfirst($vendor) . ' ' . $model . ' - ' . $location,
            'vendor' => $vendor,
            'mac_address' => fake()->unique()->macAddress(),
            'template' => fake()->optional(0.4)->text(200),
            'extension_id' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
