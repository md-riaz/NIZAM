<?php

namespace Tests\Feature\Api;

use App\Models\Did;
use App\Models\Extension;
use App\Models\Flow;
use App\Models\Organization;
use App\Models\User;
use App\Rules\DidDestination;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
     * The default state's type must be one the API accepts. It used to pick from
     * five types, three of which the endpoint rejects.
     *
     * Only the type is asserted: the default deliberately leaves destination_id a
     * bare uuid, so it is a valid row but not a valid API write. forExtension()
     * and forFlow() cover that, and the case below proves it.
     */
    public function test_factory_default_destination_type_is_one_the_api_accepts(): void
    {
        $this->assertContains(Did::factory()->make()->destination_type, DidDestination::TYPES);
    }

    /**
     * An explicit target must also decide the DID's organization.
     *
     * Passing a target set only destination_id, so the DID still took a fresh
     * organization from the base definition and pointed across organizations —
     * the exact row these states exist to prevent.
     */
    public function test_an_explicit_factory_target_also_sets_the_organization(): void
    {
        $extension = Extension::factory()->create(['organization_id' => $this->organization->id]);
        $flow = Flow::factory()->create(['organization_id' => $this->organization->id]);

        $withExtension = Did::factory()->forExtension($extension)->create();
        $withFlow = Did::factory()->forFlow($flow)->create();

        $this->assertSame($this->organization->id, $withExtension->organization_id);
        $this->assertSame($extension->id, $withExtension->destination_id);

        $this->assertSame($this->organization->id, $withFlow->organization_id);
        $this->assertSame($flow->id, $withFlow->destination_id);
    }

    /**
     * A DID built by the factory's target states must survive a round trip
     * through the endpoint, which is the point of having the states at all.
     */
    public function test_a_factory_built_did_can_be_saved_back_through_the_api(): void
    {
        $did = Did::factory()->forExtension()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->user, 'sanctum')
            ->putJson($this->url().'/'.$did->id, [
                'number' => $did->number,
                'destination_type' => $did->destination_type,
                'destination_id' => (string) $did->destination_id,
                'is_active' => true,
            ])
            ->assertOk();
    }

    /**
     * A non-scalar destination_type must not reach the rule's constructor.
     *
     * The rule is built while the rules array is assembled, before any of them
     * run, so an array value hit a `?string` parameter and raised a TypeError —
     * a 500 where the client should simply be told the type is invalid.
     */
    public function test_a_non_scalar_destination_type_is_a_validation_error_not_a_crash(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->url(), $this->payload([
                'destination_type' => ['extension'],
                'destination_id' => (string) fake()->uuid(),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['destination_type']);
    }

    public function test_a_non_scalar_destination_id_is_a_validation_error_not_a_crash(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->url(), $this->payload([
                'destination_type' => 'extension',
                'destination_id' => ['nope'],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['destination_id']);
    }

    /**
     * A malformed destination_id must not be handed to the database.
     *
     * Laravel does not stop at the first failing rule, so this value fails the
     * `uuid` rule and still reaches this one. Postgres types the primary key as
     * `uuid` and raises "invalid input syntax" when compared with a non-UUID,
     * turning invalid input into a 500. SQLite compares it as a string and would
     * never reveal that, so the guarantee asserted here is that the rule
     * short-circuits before querying at all.
     */
    public function test_a_malformed_destination_id_never_reaches_a_query(): void
    {
        $queried = false;
        DB::listen(function (QueryExecuted $query) use (&$queried) {
            if (str_contains($query->sql, 'extensions')) {
                $queried = true;
            }
        });

        $rule = new DidDestination($this->organization, 'extension');
        $failed = false;
        $rule->validate('destination_id', 'not-a-uuid', function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($queried, 'A malformed UUID was sent to the database.');
        $this->assertFalse($failed, 'The uuid rule owns this message; the destination rule should stay quiet.');
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
