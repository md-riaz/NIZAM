<?php

namespace Tests\Feature\Api;

use App\Models\Did;
use App\Models\Extension;
use App\Models\Flow;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A number routes to an extension or a flow, and the target has to be real.
 *
 * `destination_id` was only checked for UUID shape, so any well-formed UUID was
 * accepted — a number could be saved pointing at a record that did not exist, or
 * at a record of the wrong kind. Both leave the number answering to nothing.
 */
class DidDestinationValidationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role' => 'admin',
        ]);
    }

    public function test_can_point_a_number_at_an_extension_in_the_organization(): void
    {
        $extension = Extension::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->url(), $this->payload([
                'destination_type' => 'extension',
                'destination_id' => $extension->id,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.destination_type', 'extension');
    }

    public function test_can_point_a_number_at_a_flow_in_the_organization(): void
    {
        $flow = Flow::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->url(), $this->payload([
                'destination_type' => 'flow',
                'destination_id' => $flow->id,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.destination_type', 'flow');
    }

    public function test_rejects_a_destination_that_does_not_exist(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->url(), $this->payload([
                'destination_type' => 'extension',
                'destination_id' => (string) fake()->uuid(),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['destination_id']);
    }

    /**
     * The failure that motivated this rule: an extension id declared as a flow
     * (or the reverse) passed validation and compiled to no destination at all.
     */
    public function test_rejects_a_destination_of_the_wrong_type(): void
    {
        $extension = Extension::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->url(), $this->payload([
                'destination_type' => 'flow',
                'destination_id' => $extension->id,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['destination_id']);
    }

    public function test_rejects_a_destination_owned_by_another_organization(): void
    {
        $other = Organization::factory()->create();
        $foreign = Extension::factory()->create(['organization_id' => $other->id]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->url(), $this->payload([
                'destination_type' => 'extension',
                'destination_id' => $foreign->id,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['destination_id']);
    }

    public function test_updating_a_number_also_checks_the_destination(): void
    {
        $did = Did::factory()->forExtension()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->user, 'sanctum')
            ->putJson($this->url().'/'.$did->id, [
                'number' => $did->number,
                'destination_type' => 'extension',
                'destination_id' => (string) fake()->uuid(),
                'is_active' => true,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['destination_id']);
    }

    /**
     * Anything beyond extension and flow is rejected on the type itself, so the
     * destination rule never has to know about ring groups or queues.
     */
    public function test_rejects_destination_types_outside_extension_and_flow(): void
    {
        foreach (['ring_group', 'ivr', 'queue', 'voicemail', 'time_condition', 'bridge'] as $type) {
            $this->actingAs($this->user, 'sanctum')
                ->postJson($this->url(), $this->payload([
                    'number' => '+1555000'.fake()->numerify('####'),
                    'destination_type' => $type,
                    'destination_id' => (string) fake()->uuid(),
                ]))
                ->assertStatus(422)
                ->assertJsonValidationErrors(['destination_type']);
        }
    }

    /**
     * The factory's target states must land in the DID's own organization.
     *
     * A state closure only sees the factory's own definition, so building the
     * target from `$attributes['organization_id']` there put it in a different
     * organization — which made the update case above pass for the wrong reason.
     */
    public function test_factory_target_states_stay_in_the_same_organization(): void
    {
        $withExtension = Did::factory()->forExtension()->create(['organization_id' => $this->organization->id]);
        $withFlow = Did::factory()->forFlow()->create(['organization_id' => $this->organization->id]);

        $this->assertSame('extension', $withExtension->destination_type);
        $this->assertTrue(
            $this->organization->extensions()->whereKey($withExtension->destination_id)->exists(),
            'forExtension() built the extension outside the DID\'s organization.'
        );

        $this->assertSame('flow', $withFlow->destination_type);
        $this->assertTrue(
            $this->organization->flows()->whereKey($withFlow->destination_id)->exists(),
            'forFlow() built the flow outside the DID\'s organization.'
        );
    }

    /**
     * The default state must be savable through the API, which is the whole
     * point of narrowing it: it used to pick from five types, three of which the
     * endpoint rejects.
     */
    public function test_factory_default_destination_type_is_accepted_by_the_api(): void
    {
        $this->assertContains(Did::factory()->make()->destination_type, ['extension', 'flow']);
    }

    private function url(): string
    {
        return "/api/v1/organizations/{$this->organization->id}/dids";
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'number' => '+15550001234',
            'is_active' => true,
        ], $overrides);
    }
}
