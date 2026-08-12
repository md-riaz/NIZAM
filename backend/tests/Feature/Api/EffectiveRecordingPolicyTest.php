<?php

namespace Tests\Feature\Api;

use App\Models\Did;
use App\Models\Extension;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EffectiveRecordingPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create(['recording_policy' => 'off']);
        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role' => 'admin',
        ]);
    }

    public function test_can_resolve_organization_scope(): void
    {
        $this->organization->update(['recording_policy' => 'all']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/recording-policy/effective");

        $response->assertOk();
        $response->assertJsonPath('data.scope', 'organization');
        $response->assertJsonPath('data.inbound.resolved_mode', 'all');
        $response->assertJsonPath('data.inbound.should_record', true);
        $response->assertJsonPath('data.inbound.winning_scope', 'organization');
        $response->assertJsonPath('data.outbound.resolved_mode', 'all');
        $response->assertJsonPath('data.outbound.should_record', true);
        $response->assertJsonPath('data.outbound.winning_scope', 'organization');
    }

    public function test_organization_scope_with_inherit_resolves_to_nothing(): void
    {
        // "inherit" is not offered in the organization form anymore, but the
        // backend still accepts it for compatibility. At organization scope
        // there is nothing above it to inherit from, so it silently resolves
        // to "no policy" (not recording) rather than "all"/"off".
        $this->organization->update(['recording_policy' => 'inherit']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/recording-policy/effective");

        $response->assertOk();
        $response->assertJsonPath('data.inbound.should_record', false);
        $response->assertJsonPath('data.inbound.winning_scope', null);
    }

    public function test_can_resolve_did_scope_inheriting_from_organization(): void
    {
        $this->organization->update(['recording_policy' => 'incoming']);
        $did = Did::factory()->create([
            'organization_id' => $this->organization->id,
            'recording_policy' => 'inherit',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/dids/{$did->id}/recording-policy/effective");

        $response->assertOk();
        $response->assertJsonPath('data.scope', 'did');
        $response->assertJsonPath('data.inbound.resolved_mode', 'incoming');
        $response->assertJsonPath('data.inbound.should_record', true);
        $response->assertJsonPath('data.inbound.winning_scope', 'organization');
        // "incoming" does not match the outbound direction, so it does not record,
        // but the resolved mode/winning scope are still surfaced.
        $response->assertJsonPath('data.outbound.resolved_mode', 'incoming');
        $response->assertJsonPath('data.outbound.should_record', false);
        $response->assertJsonPath('data.outbound.winning_scope', 'organization');
    }

    public function test_did_off_wins_over_organization_all(): void
    {
        $this->organization->update(['recording_policy' => 'all']);
        $did = Did::factory()->create([
            'organization_id' => $this->organization->id,
            'recording_policy' => 'off',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/dids/{$did->id}/recording-policy/effective");

        $response->assertOk();
        $response->assertJsonPath('data.inbound.resolved_mode', 'off');
        $response->assertJsonPath('data.inbound.should_record', false);
        $response->assertJsonPath('data.inbound.winning_scope', 'did');
        $response->assertJsonPath('data.outbound.resolved_mode', 'off');
        $response->assertJsonPath('data.outbound.should_record', false);
        $response->assertJsonPath('data.outbound.winning_scope', 'did');
    }

    public function test_can_resolve_extension_scope_inheriting_from_default_outbound_did(): void
    {
        $this->organization->update(['recording_policy' => 'off']);
        $did = Did::factory()->create([
            'organization_id' => $this->organization->id,
            'recording_policy' => 'all',
        ]);
        $extension = Extension::factory()->create([
            'organization_id' => $this->organization->id,
            'recording_policy' => 'inherit',
            'default_outbound_did_id' => $did->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/extensions/{$extension->id}/recording-policy/effective");

        $response->assertOk();
        $response->assertJsonPath('data.scope', 'extension');
        $response->assertJsonPath('data.inbound.resolved_mode', 'all');
        $response->assertJsonPath('data.inbound.should_record', true);
        $response->assertJsonPath('data.inbound.winning_scope', 'did');
        $response->assertJsonPath('data.outbound.resolved_mode', 'all');
        $response->assertJsonPath('data.outbound.should_record', true);
        $response->assertJsonPath('data.outbound.winning_scope', 'did');
    }

    public function test_extension_off_wins_over_organization_all(): void
    {
        $this->organization->update(['recording_policy' => 'all']);
        $extension = Extension::factory()->create([
            'organization_id' => $this->organization->id,
            'recording_policy' => 'off',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/extensions/{$extension->id}/recording-policy/effective");

        $response->assertOk();
        $response->assertJsonPath('data.inbound.resolved_mode', 'off');
        $response->assertJsonPath('data.inbound.should_record', false);
        $response->assertJsonPath('data.inbound.winning_scope', 'extension');
        $response->assertJsonPath('data.outbound.resolved_mode', 'off');
        $response->assertJsonPath('data.outbound.should_record', false);
        $response->assertJsonPath('data.outbound.winning_scope', 'extension');
    }

    public function test_extension_without_default_outbound_did_falls_back_to_organization(): void
    {
        $this->organization->update(['recording_policy' => 'outgoing']);
        $extension = Extension::factory()->create([
            'organization_id' => $this->organization->id,
            'recording_policy' => 'inherit',
            'default_outbound_did_id' => null,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/extensions/{$extension->id}/recording-policy/effective");

        $response->assertOk();
        $response->assertJsonPath('data.outbound.resolved_mode', 'outgoing');
        $response->assertJsonPath('data.outbound.should_record', true);
        $response->assertJsonPath('data.outbound.winning_scope', 'organization');
    }

    public function test_did_from_another_organization_returns_404_for_superadmin(): void
    {
        $otherOrganization = Organization::factory()->create();
        $did = Did::factory()->create(['organization_id' => $otherOrganization->id]);
        $superadmin = User::factory()->create(['role' => 'superadmin', 'organization_id' => null]);

        $response = $this->actingAs($superadmin, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/dids/{$did->id}/recording-policy/effective");

        $response->assertStatus(404);
    }

    public function test_extension_from_another_organization_returns_404_for_superadmin(): void
    {
        $otherOrganization = Organization::factory()->create();
        $extension = Extension::factory()->create(['organization_id' => $otherOrganization->id]);
        $superadmin = User::factory()->create(['role' => 'superadmin', 'organization_id' => null]);

        $response = $this->actingAs($superadmin, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/extensions/{$extension->id}/recording-policy/effective");

        $response->assertStatus(404);
    }

    public function test_unauthorized_user_is_denied_for_organization_scope(): void
    {
        $agent = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role' => 'agent',
        ]);

        $response = $this->actingAs($agent, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/recording-policy/effective");

        $response->assertStatus(403);
    }

    public function test_unauthorized_user_is_denied_for_did_scope(): void
    {
        $did = Did::factory()->create(['organization_id' => $this->organization->id]);
        $agent = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role' => 'agent',
        ]);

        $response = $this->actingAs($agent, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/dids/{$did->id}/recording-policy/effective");

        $response->assertStatus(403);
    }

    public function test_unauthorized_user_is_denied_for_extension_scope(): void
    {
        $extension = Extension::factory()->create(['organization_id' => $this->organization->id]);
        $agent = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role' => 'agent',
        ]);

        $response = $this->actingAs($agent, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/extensions/{$extension->id}/recording-policy/effective");

        $response->assertStatus(403);
    }
}
