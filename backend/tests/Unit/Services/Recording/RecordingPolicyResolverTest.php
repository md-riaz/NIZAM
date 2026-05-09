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
}
