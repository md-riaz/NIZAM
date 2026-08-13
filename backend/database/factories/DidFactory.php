<?php

namespace Database\Factories;

use App\Models\Did;
use App\Models\Extension;
use App\Models\Flow;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Did>
 */
class DidFactory extends Factory
{
    protected $model = Did::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'number' => '+1'.fake()->numerify('##########'),
            'description' => fake()->optional(0.7)->sentence(),
            // A number only ever routes to an extension or a flow. This used to
            // pick from five types, most of which the API rejects, so roughly
            // three in five factory DIDs could not be saved back through it.
            'destination_type' => 'extension',
            'destination_id' => fake()->uuid(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * Point the number at a real extension in its own organization.
     *
     * Use this when the DID is written back through the API, which checks that
     * the destination exists.
     */
    public function forExtension(?Extension $extension = null): static
    {
        return $this->state(fn () => [
            'destination_type' => 'extension',
            // Deferred to a closure so it resolves against the final attributes.
            // A state closure only sees this factory's own definition, so any
            // organization_id passed to create() would not be visible yet and the
            // extension would be built in the wrong organization.
            'destination_id' => $extension?->id ?? fn (array $attributes) => Extension::factory()->create([
                'organization_id' => $attributes['organization_id'],
            ])->id,
        ]);
    }

    /**
     * Point the number at a real flow in its own organization.
     */
    public function forFlow(?Flow $flow = null): static
    {
        return $this->state(fn () => [
            'destination_type' => 'flow',
            'destination_id' => $flow?->id ?? fn (array $attributes) => Flow::factory()->create([
                'organization_id' => $attributes['organization_id'],
            ])->id,
        ]);
    }
}
