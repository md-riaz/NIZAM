<?php

namespace Tests\Unit\Services;

use App\Models\Bridge;
use App\Models\Gateway;
use App\Models\Organization;
use App\Services\Routing\CodecResolutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodecResolutionServiceTest extends TestCase
{
    use RefreshDatabase;

    private CodecResolutionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CodecResolutionService;
    }

    // -------------------------------------------------------------------------
    // Default policy
    // -------------------------------------------------------------------------

    public function test_default_policy_uses_gateway_preferred_codecs(): void
    {
        $organization = Organization::factory()->create();
        $gateway = Gateway::factory()->create([
            'organization_id' => $organization->id,
            'preferred_codecs' => ['PCMU', 'PCMA'],
            'outbound_codecs' => ['G722', 'PCMU'],
        ]);
        $bridge = Bridge::factory()->create([
            'organization_id' => $organization->id,
            'gateway_id' => $gateway->id,
            'codec_policy' => 'default',
        ]);

        $result = $this->service->resolve('sip', $bridge, $gateway);

        $this->assertEquals(['PCMU', 'PCMA'], $result['effective_codecs']);
        $this->assertEquals('codec_string', $result['fs_variable_name']);
        $this->assertEquals('PCMU,PCMA', $result['fs_variable_value']);
    }

    public function test_default_policy_falls_back_to_outbound_codecs_when_preferred_is_empty(): void
    {
        $organization = Organization::factory()->create();
        $gateway = Gateway::factory()->create([
            'organization_id' => $organization->id,
            'preferred_codecs' => [],
            'outbound_codecs' => ['G722', 'PCMU'],
        ]);
        $bridge = Bridge::factory()->create([
            'organization_id' => $organization->id,
            'gateway_id' => $gateway->id,
            'codec_policy' => 'default',
        ]);

        $result = $this->service->resolve('sip', $bridge, $gateway);

        $this->assertEquals(['G722', 'PCMU'], $result['effective_codecs']);
    }

    public function test_default_policy_for_webrtc_uses_webrtc_defaults_when_no_gateway(): void
    {
        $result = $this->service->resolve('webrtc', null, null);

        $this->assertEquals(CodecResolutionService::WEBRTC_DEFAULT_CODECS, $result['effective_codecs']);
        // With no bridge and no gateway, codec_string is still set for the outbound leg
        $this->assertEquals('codec_string', $result['fs_variable_name']);
    }

    // -------------------------------------------------------------------------
    // Restricted policy
    // -------------------------------------------------------------------------

    public function test_restricted_policy_intersects_bridge_list_with_gateway_outbound(): void
    {
        $organization = Organization::factory()->create();
        $gateway = Gateway::factory()->create([
            'organization_id' => $organization->id,
            'outbound_codecs' => ['PCMU', 'PCMA'],
        ]);
        $bridge = Bridge::factory()->create([
            'organization_id' => $organization->id,
            'gateway_id' => $gateway->id,
            'codec_policy' => 'restricted',
            'codec_list' => ['PCMU', 'G722'],
        ]);

        $result = $this->service->resolve('sip', $bridge, $gateway);

        $this->assertEquals(['PCMU'], $result['effective_codecs']);
        $this->assertEquals('absolute_codec_string', $result['fs_variable_name']);
    }

    public function test_restricted_policy_warns_when_no_shared_codec(): void
    {
        $organization = Organization::factory()->create();
        $gateway = Gateway::factory()->create([
            'organization_id' => $organization->id,
            'outbound_codecs' => ['G729'],
        ]);
        $bridge = Bridge::factory()->create([
            'organization_id' => $organization->id,
            'gateway_id' => $gateway->id,
            'codec_policy' => 'restricted',
            'codec_list' => ['PCMU', 'G722'],
        ]);

        $result = $this->service->resolve('sip', $bridge, $gateway);

        $this->assertEmpty($result['effective_codecs']);
        $this->assertNotEmpty($result['warnings']);
    }

    // -------------------------------------------------------------------------
    // Preferred policy
    // -------------------------------------------------------------------------

    public function test_preferred_policy_uses_bridge_order_within_gateway_codecs(): void
    {
        $organization = Organization::factory()->create();
        $gateway = Gateway::factory()->create([
            'organization_id' => $organization->id,
            'outbound_codecs' => ['PCMU', 'PCMA', 'G722'],
        ]);
        $bridge = Bridge::factory()->create([
            'organization_id' => $organization->id,
            'gateway_id' => $gateway->id,
            'codec_policy' => 'preferred',
            'codec_list' => ['G722', 'PCMU'],
        ]);

        $result = $this->service->resolve('sip', $bridge, $gateway);

        // G722 and PCMU are in gateway's outbound_codecs; G722 first per bridge preference
        $this->assertEquals(['G722', 'PCMU'], $result['effective_codecs']);
        $this->assertEquals('codec_string', $result['fs_variable_name']);
    }

    // -------------------------------------------------------------------------
    // Exact policy
    // -------------------------------------------------------------------------

    public function test_exact_policy_uses_bridge_codec_list_without_gateway_filter(): void
    {
        $organization = Organization::factory()->create();
        $gateway = Gateway::factory()->create([
            'organization_id' => $organization->id,
            'outbound_codecs' => ['PCMU'],
        ]);
        $bridge = Bridge::factory()->create([
            'organization_id' => $organization->id,
            'gateway_id' => $gateway->id,
            'codec_policy' => 'exact',
            'codec_list' => ['G729'],
        ]);

        $result = $this->service->resolve('sip', $bridge, $gateway);

        $this->assertEquals(['G729'], $result['effective_codecs']);
        $this->assertEquals('absolute_codec_string', $result['fs_variable_name']);
        $this->assertEquals('G729', $result['fs_variable_value']);
    }

    // -------------------------------------------------------------------------
    // Inherit policy
    // -------------------------------------------------------------------------

    public function test_inherit_policy_sets_inherit_codec_flag_and_no_fs_variable(): void
    {
        $organization = Organization::factory()->create();
        $gateway = Gateway::factory()->create(['organization_id' => $organization->id]);
        $bridge = Bridge::factory()->create([
            'organization_id' => $organization->id,
            'gateway_id' => $gateway->id,
            'codec_policy' => 'inherit',
        ]);

        $result = $this->service->resolve('sip', $bridge, $gateway, ['PCMU', 'G722']);

        $this->assertTrue($result['inherit_codec']);
        $this->assertNull($result['fs_variable_name']);
        $this->assertNull($result['fs_variable_value']);
    }

    // -------------------------------------------------------------------------
    // Transcoding policy
    // -------------------------------------------------------------------------

    public function test_transcoding_required_when_no_shared_codec(): void
    {
        $organization = Organization::factory()->create();
        $gateway = Gateway::factory()->create([
            'organization_id' => $organization->id,
            'preferred_codecs' => ['G729'],
            'allow_transcoding' => true,
        ]);
        $bridge = Bridge::factory()->create([
            'organization_id' => $organization->id,
            'gateway_id' => $gateway->id,
            'codec_policy' => 'default',
            'transcode_policy' => 'allow',
        ]);

        $result = $this->service->resolve('sip', $bridge, $gateway, ['PCMU']);

        $this->assertTrue($result['transcoding_required']);
        $this->assertTrue($result['transcoding_allowed']);
    }

    public function test_transcode_policy_none_disables_transcoding_regardless_of_gateway(): void
    {
        $organization = Organization::factory()->create();
        $gateway = Gateway::factory()->create([
            'organization_id' => $organization->id,
            'preferred_codecs' => ['G729'],
            'allow_transcoding' => true,
        ]);
        $bridge = Bridge::factory()->create([
            'organization_id' => $organization->id,
            'gateway_id' => $gateway->id,
            'codec_policy' => 'default',
            'transcode_policy' => 'none',
        ]);

        $result = $this->service->resolve('sip', $bridge, $gateway, ['PCMU']);

        $this->assertFalse($result['transcoding_allowed']);
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_web_only_transcode_policy_allows_transcoding_for_webrtc(): void
    {
        $organization = Organization::factory()->create();
        $gateway = Gateway::factory()->create([
            'organization_id' => $organization->id,
            'preferred_codecs' => ['PCMU'],
            'allow_transcoding' => false,
        ]);
        $bridge = Bridge::factory()->create([
            'organization_id' => $organization->id,
            'gateway_id' => $gateway->id,
            'codec_policy' => 'default',
            'transcode_policy' => 'web_only',
        ]);

        $webrtcResult = $this->service->resolve('webrtc', $bridge, $gateway, ['OPUS']);
        $sipResult = $this->service->resolve('sip', $bridge, $gateway, ['G722']);

        $this->assertTrue($webrtcResult['transcoding_allowed']);
        $this->assertFalse($sipResult['transcoding_allowed']);
    }

    // -------------------------------------------------------------------------
    // WebRTC defaults
    // -------------------------------------------------------------------------

    public function test_webrtc_endpoint_type_uses_opus_first_default(): void
    {
        $result = $this->service->resolve('webrtc', null, null, []);

        $this->assertContains('OPUS', $result['effective_codecs']);
        $this->assertEquals('OPUS', $result['effective_codecs'][0]);
    }

    public function test_sip_endpoint_does_not_include_opus_in_defaults(): void
    {
        $result = $this->service->resolve('sip', null, null, []);

        $this->assertNotContains('OPUS', $result['effective_codecs']);
    }

    // -------------------------------------------------------------------------
    // No transcoding needed when shared codec exists
    // -------------------------------------------------------------------------

    public function test_no_transcoding_required_when_shared_codec_exists(): void
    {
        $organization = Organization::factory()->create();
        $gateway = Gateway::factory()->create([
            'organization_id' => $organization->id,
            'preferred_codecs' => ['PCMU', 'PCMA'],
        ]);
        $bridge = Bridge::factory()->create([
            'organization_id' => $organization->id,
            'gateway_id' => $gateway->id,
            'codec_policy' => 'default',
        ]);

        $result = $this->service->resolve('sip', $bridge, $gateway, ['PCMU', 'G722']);

        $this->assertFalse($result['transcoding_required']);
    }
}
