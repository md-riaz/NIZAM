<?php

namespace Tests\Feature;

use Tests\TestCase;

class SipScriptRegisterConfigTest extends TestCase
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
}
