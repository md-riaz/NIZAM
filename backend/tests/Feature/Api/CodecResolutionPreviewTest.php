<?php

namespace Tests\Feature\Api;

use App\Models\Bridge;
use App\Models\Gateway;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodecResolutionPreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'admin']);
    }

    public function test_preview_returns_effective_codecs_for_sip_endpoint(): void
    {
        $gateway = Gateway::factory()->create([
            'tenant_id' => $this->tenant->id,
            'preferred_codecs' => ['PCMU', 'PCMA'],
            'outbound_codecs' => ['PCMU', 'PCMA'],
        ]);
        $bridge = Bridge::factory()->create([
            'tenant_id' => $this->tenant->id,
            'gateway_id' => $gateway->id,
            'codec_policy' => 'default',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/tenants/{$this->tenant->id}/codec-resolution/preview", [
                'endpoint_type' => 'sip',
                'bridge_id' => $bridge->id,
                'offered_codecs' => ['PCMU', 'G722'],
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.endpoint_type', 'sip');
        $response->assertJsonPath('data.effective_codecs', ['PCMU', 'PCMA']);
        $response->assertJsonPath('data.transcoding_required', false);
    }

    public function test_preview_returns_opus_first_for_webrtc_endpoint(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/tenants/{$this->tenant->id}/codec-resolution/preview", [
                'endpoint_type' => 'webrtc',
                'offered_codecs' => ['OPUS', 'G722'],
            ]);

        $response->assertOk();
        $this->assertEquals('OPUS', $response->json('data.effective_codecs.0'));
    }

    public function test_preview_resolves_gateway_from_bridge_when_not_provided(): void
    {
        $gateway = Gateway::factory()->create([
            'tenant_id' => $this->tenant->id,
            'preferred_codecs' => ['G722'],
        ]);
        $bridge = Bridge::factory()->create([
            'tenant_id' => $this->tenant->id,
            'gateway_id' => $gateway->id,
            'codec_policy' => 'default',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/tenants/{$this->tenant->id}/codec-resolution/preview", [
                'endpoint_type' => 'sip',
                'bridge_id' => $bridge->id,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.gateway_id', $gateway->id);
        $response->assertJsonPath('data.effective_codecs', ['G722']);
    }

    public function test_preview_uses_exact_policy_codec_list(): void
    {
        $gateway = Gateway::factory()->create([
            'tenant_id' => $this->tenant->id,
            'outbound_codecs' => ['PCMU'],
        ]);
        $bridge = Bridge::factory()->create([
            'tenant_id' => $this->tenant->id,
            'gateway_id' => $gateway->id,
            'codec_policy' => 'exact',
            'codec_list' => ['G729'],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/tenants/{$this->tenant->id}/codec-resolution/preview", [
                'endpoint_type' => 'sip',
                'bridge_id' => $bridge->id,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.effective_codecs', ['G729']);
        $response->assertJsonPath('data.fs_variable_name', 'absolute_codec_string');
    }

    public function test_preview_returns_transcoding_required_when_no_shared_codec(): void
    {
        $gateway = Gateway::factory()->create([
            'tenant_id' => $this->tenant->id,
            'preferred_codecs' => ['G729'],
            'allow_transcoding' => true,
        ]);
        $bridge = Bridge::factory()->create([
            'tenant_id' => $this->tenant->id,
            'gateway_id' => $gateway->id,
            'codec_policy' => 'default',
            'transcode_policy' => 'allow',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/tenants/{$this->tenant->id}/codec-resolution/preview", [
                'endpoint_type' => 'sip',
                'bridge_id' => $bridge->id,
                'offered_codecs' => ['PCMU', 'G722'],
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.transcoding_required', true);
        $response->assertJsonPath('data.transcoding_allowed', true);
    }

    public function test_preview_rejects_invalid_endpoint_type(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/tenants/{$this->tenant->id}/codec-resolution/preview", [
                'endpoint_type' => 'invalid',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['endpoint_type']);
    }

    public function test_preview_rejects_invalid_offered_codec(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/tenants/{$this->tenant->id}/codec-resolution/preview", [
                'endpoint_type' => 'sip',
                'offered_codecs' => ['INVALID_CODEC'],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['offered_codecs.0']);
    }

    public function test_unauthenticated_requests_return_401(): void
    {
        $response = $this->postJson("/api/v1/tenants/{$this->tenant->id}/codec-resolution/preview", [
            'endpoint_type' => 'sip',
        ]);

        $response->assertStatus(401);
    }
}
