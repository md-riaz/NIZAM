<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\EndpointBinding;
use App\Models\Extension;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EndpointBinding>
 */
class EndpointBindingFactory extends Factory
{
    protected $model = EndpointBinding::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'extension_id' => Extension::factory()->state(function (array $attributes): array {
                return ['organization_id' => $attributes['organization_id']];
            }),
            'agent_id' => null,
            'type' => EndpointBinding::TYPE_MOBILE_APP,
            'device_uuid' => fake()->uuid(),
            'push_token' => fake()->sha256(),
            'voip_push_token' => fake()->optional()->sha256(),
            'platform' => EndpointBinding::PLATFORM_IOS,
            'app_version' => fake()->semver(),
            'is_push_capable' => true,
            'is_enabled' => true,
            'rings_immediately_when_online' => true,
            'allow_late_join_after_push' => true,
            'forward_number' => null,
            'forward_requires_confirm' => true,
            'last_seen_at' => now(),
            'last_registered_at' => now(),
            'metadata' => ['source' => 'factory'],
        ];
    }

    public function forAgent(Agent $agent): static
    {
        return $this->state(fn () => [
            'organization_id' => $agent->organization_id,
            'agent_id' => $agent->id,
            'extension_id' => $agent->extension_id,
        ]);
    }

    public function forExtension(Extension $extension): static
    {
        return $this->state(fn () => [
            'organization_id' => $extension->organization_id,
            'extension_id' => $extension->id,
        ]);
    }

    public function pstnForward(): static
    {
        return $this->state(fn () => [
            'type' => EndpointBinding::TYPE_PSTN_FORWARD,
            'is_push_capable' => false,
            'rings_immediately_when_online' => false,
            'allow_late_join_after_push' => false,
            'forward_number' => fake()->e164PhoneNumber(),
            'push_token' => null,
            'voip_push_token' => null,
            'platform' => EndpointBinding::PLATFORM_UNKNOWN,
        ]);
    }
}
