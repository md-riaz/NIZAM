<?php

namespace Tests\Unit\Services\Recording;

use App\Services\Recording\RecordingPolicyResolver;
use Tests\TestCase;

class RecordingPolicyResolverTest extends TestCase
{
    public function test_inbound_extension_answer_prefers_extension_over_did_over_organization(): void
    {
        $resolved = app(RecordingPolicyResolver::class)->resolve([
            'direction' => 'inbound',
            'organization_policy' => 'off',
            'did_policy' => 'incoming',
            'extension_policy' => 'all',
            'answered_target_type' => 'extension',
        ]);

        $this->assertTrue($resolved['should_record']);
        $this->assertSame('all', $resolved['resolved_mode']);
        $this->assertSame('extension', $resolved['winning_scope']);
        $this->assertSame(['extension:all'], $resolved['resolution_chain']);
    }

    public function test_inbound_queue_answer_uses_did_then_organization(): void
    {
        $resolved = app(RecordingPolicyResolver::class)->resolve([
            'direction' => 'inbound',
            'organization_policy' => 'incoming',
            'did_policy' => 'inherit',
            'extension_policy' => null,
            'answered_target_type' => 'queue',
        ]);

        $this->assertTrue($resolved['should_record']);
        $this->assertSame('incoming', $resolved['resolved_mode']);
        $this->assertSame('organization', $resolved['winning_scope']);
        $this->assertSame(['did:inherit', 'organization:incoming'], $resolved['resolution_chain']);
    }

    public function test_outbound_extension_answer_uses_extension_then_organization(): void
    {
        $resolved = app(RecordingPolicyResolver::class)->resolve([
            'direction' => 'outbound',
            'organization_policy' => 'off',
            'extension_policy' => 'outgoing',
            'did_policy' => 'all',
            'answered_target_type' => 'extension',
        ]);

        $this->assertTrue($resolved['should_record']);
        $this->assertSame('outgoing', $resolved['resolved_mode']);
        $this->assertSame('extension', $resolved['winning_scope']);
        $this->assertSame(['extension:outgoing'], $resolved['resolution_chain']);
    }

    public function test_inbound_off_policy_returns_non_recording_decision(): void
    {
        $resolved = app(RecordingPolicyResolver::class)->resolve([
            'direction' => 'inbound',
            'organization_policy' => 'off',
            'did_policy' => 'inherit',
            'extension_policy' => 'inherit',
            'answered_target_type' => 'extension',
        ]);

        $this->assertFalse($resolved['should_record']);
        $this->assertSame('off', $resolved['resolved_mode']);
        $this->assertSame('organization', $resolved['winning_scope']);
        $this->assertSame(['extension:inherit', 'did:inherit', 'organization:off'], $resolved['resolution_chain']);
    }

    public function test_direction_specific_modes_skip_when_direction_does_not_match(): void
    {
        $resolved = app(RecordingPolicyResolver::class)->resolve([
            'direction' => 'outbound',
            'organization_policy' => 'incoming',
            'did_policy' => 'inherit',
            'extension_policy' => null,
            'answered_target_type' => 'queue',
        ]);

        $this->assertFalse($resolved['should_record']);
        $this->assertSame('incoming', $resolved['resolved_mode']);
        $this->assertSame('organization', $resolved['winning_scope']);
        $this->assertSame('organization policy does not match outbound direction', $resolved['reason']);
    }

    public function test_invalid_or_missing_values_fall_back_to_inherit_until_scope_wins(): void
    {
        $resolved = app(RecordingPolicyResolver::class)->resolve([
            'direction' => 'inbound',
            'organization_policy' => 'all',
            'did_policy' => 'bogus',
            'extension_policy' => null,
            'answered_target_type' => 'queue',
        ]);

        $this->assertTrue($resolved['should_record']);
        $this->assertSame('all', $resolved['resolved_mode']);
        $this->assertSame('organization', $resolved['winning_scope']);
        $this->assertSame(['did:inherit', 'organization:all'], $resolved['resolution_chain']);
    }

    /**
     * A narrow scope set for the other direction must not answer for the call.
     *
     * This is the case the resolver got wrong. An extension recording incoming
     * calls says nothing about its outbound ones — but the walk stopped there
     * and reported "no", so the number's and the organization's policies were
     * never consulted, and an organization that records everything recorded
     * nothing for that extension's outbound calls.
     *
     * FusionPBX cannot express this failure at all: its per-extension and
     * per-number rules live in different dialplan contexts and neither can
     * suppress the other.
     */
    public function test_a_scope_set_for_the_other_direction_does_not_answer_for_the_call(): void
    {
        $resolved = app(RecordingPolicyResolver::class)->resolve([
            'direction' => 'outbound',
            'extension_policy' => 'incoming',
            'did_policy' => 'inherit',
            'organization_policy' => 'all',
            'answered_target_type' => 'extension',
        ]);

        $this->assertTrue($resolved['should_record'], 'The organization asked for this call and was never consulted.');
        $this->assertSame('organization', $resolved['winning_scope']);
        $this->assertSame(['extension:incoming', 'did:inherit', 'organization:all'], $resolved['resolution_chain']);
    }

    /**
     * The same, one scope further in: a number set for the other direction does
     * not suppress the organization either.
     */
    public function test_a_number_set_for_the_other_direction_does_not_suppress_the_organization(): void
    {
        $resolved = app(RecordingPolicyResolver::class)->resolve([
            'direction' => 'inbound',
            'did_policy' => 'outgoing',
            'organization_policy' => 'all',
            'answered_target_type' => 'queue',
        ]);

        $this->assertTrue($resolved['should_record']);
        $this->assertSame('organization', $resolved['winning_scope']);
    }

    /**
     * An explicit `off` is still an opt-out, and still wins over anything
     * broader. That is the one thing `off` is for — `inherit` already means
     * "no choice here".
     */
    public function test_an_explicit_off_still_wins_over_a_broader_scope(): void
    {
        $resolved = app(RecordingPolicyResolver::class)->resolve([
            'direction' => 'outbound',
            'extension_policy' => 'off',
            'did_policy' => 'all',
            'organization_policy' => 'all',
            'answered_target_type' => 'extension',
        ]);

        $this->assertFalse($resolved['should_record']);
        $this->assertSame('extension', $resolved['winning_scope']);
    }
}
