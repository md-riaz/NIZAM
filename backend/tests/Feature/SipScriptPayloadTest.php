<?php

namespace Tests\Feature;

use Tests\TestCase;

class SipScriptPayloadTest extends TestCase
{
    public function test_common_helper_can_build_register_target_config(): void
    {
        require base_path('scripts/sip/common.php');

        $config = sip_test_build_target_config([
            'domain' => 'app.local',
            'internal_port' => '5060',
            'host_port' => '25060',
            'host_target' => '127.0.0.1',
            'docker_target' => 'freeswitch',
        ], 'docker');

        $this->assertSame('freeswitch', $config['host']);
        $this->assertSame(5060, $config['port']);
        $this->assertSame('app.local', $config['domain']);
    }

    public function test_register_script_can_build_initial_register_payload(): void
    {
        require base_path('scripts/sip/common.php');

        $payload = sip_test_build_register_request(
            extension: '1001',
            domain: 'app.local',
            localHost: 'app',
            localPort: 5062,
            callId: 'test-call-id',
            cseq: 1,
            branch: 'z9hG4bK-test'
        );

        $this->assertStringContainsString('REGISTER sip:app.local SIP/2.0', $payload);
        $this->assertStringContainsString('From: <sip:1001@app.local>;tag=tag1', $payload);
        $this->assertStringContainsString('Call-ID: test-call-id', $payload);
        $this->assertStringContainsString('CSeq: 1 REGISTER', $payload);
    }

    public function test_invite_script_can_build_initial_invite_payload(): void
    {
        require_once base_path('scripts/sip/common.php');

        $payload = sip_test_build_invite_request(
            fromExtension: '1001',
            toExtension: '1001',
            domain: 'app.local',
            localHost: 'app',
            localPort: 5062,
            callId: 'test-invite-id',
            cseq: 1,
            branch: 'z9hG4bK-inv'
        );

        $this->assertStringContainsString('INVITE sip:1001@app.local SIP/2.0', $payload);
        $this->assertStringContainsString('From: <sip:1001@app.local>;tag=invtag', $payload);
        $this->assertStringContainsString('To: <sip:1001@app.local>', $payload);
        $this->assertStringContainsString('Content-Type: application/sdp', $payload);
    }

    public function test_invite_script_can_build_ack_payload(): void
    {
        require_once base_path('scripts/sip/common.php');

        $payload = sip_test_build_ack_request(
            fromExtension: '1001',
            toExtension: '1002',
            domain: 'app.local',
            localHost: 'app',
            localPort: 5062,
            callId: 'test-invite-id',
            cseq: 1,
            branch: 'z9hG4bK-ack',
            toTag: 'totag123'
        );

        $this->assertStringContainsString('ACK sip:1002@app.local SIP/2.0', $payload);
        $this->assertStringContainsString('To: <sip:1002@app.local>;tag=totag123', $payload);
        $this->assertStringContainsString('CSeq: 1 ACK', $payload);
    }

    public function test_invite_request_custom_sdp_ip(): void
    {
        require_once base_path('scripts/sip/common.php');

        $payload = sip_test_build_invite_request(
            fromExtension: '1001',
            toExtension: '1002',
            domain: 'app.local',
            localHost: 'app',
            localPort: 5062,
            callId: 'test-invite-id',
            cseq: 1,
            branch: 'z9hG4bK-inv',
            sdpIp: '10.0.0.5'
        );

        $this->assertStringContainsString('c=IN IP4 10.0.0.5', $payload);
        $this->assertStringContainsString('o=NizamScript 53655765 2353687637 IN IP4 10.0.0.5', $payload);
    }

    public function test_invite_request_uses_explicit_authorization_header_when_provided(): void
    {
        require_once base_path('scripts/sip/common.php');

        $payload = sip_test_build_invite_request(
            fromExtension: '1001',
            toExtension: '1002',
            domain: 'app.local',
            localHost: 'app',
            localPort: 5062,
            callId: 'test-invite-id',
            cseq: 2,
            branch: 'z9hG4bK-inv-auth',
            authorization: 'Authorization: Digest username="1001", realm="app.local", nonce="abc", uri="sip:1002@app.local", response="xyz", algorithm=MD5'
        );

        $this->assertStringContainsString('Authorization: Digest username="1001"', $payload);
        $this->assertStringNotContainsString('Proxy-Authorization: Authorization:', $payload);
    }
}
