<?php

namespace Tests\Unit\Services;

use App\Services\DialplanCompiler;
use PHPUnit\Framework\TestCase;

class InferEndpointTypeTest extends TestCase
{
    public function test_returns_sip_for_empty_payload(): void
    {
        $this->assertSame('sip', DialplanCompiler::inferEndpointType([]));
    }

    public function test_returns_sip_for_udp_transport(): void
    {
        $this->assertSame('sip', DialplanCompiler::inferEndpointType([
            'variable_sip_via_protocol' => 'udp',
            'variable_sip_transport' => 'udp',
        ]));
    }

    public function test_returns_sip_for_tcp_transport(): void
    {
        $this->assertSame('sip', DialplanCompiler::inferEndpointType([
            'variable_sip_via_protocol' => 'tcp',
        ]));
    }

    public function test_returns_sip_for_tls_transport(): void
    {
        $this->assertSame('sip', DialplanCompiler::inferEndpointType([
            'variable_sip_via_protocol' => 'tls',
        ]));
    }

    public function test_returns_webrtc_for_wss_via_protocol(): void
    {
        $this->assertSame('webrtc', DialplanCompiler::inferEndpointType([
            'variable_sip_via_protocol' => 'wss',
        ]));
    }

    public function test_returns_webrtc_for_wss_transport(): void
    {
        $this->assertSame('webrtc', DialplanCompiler::inferEndpointType([
            'variable_sip_transport' => 'wss',
        ]));
    }

    public function test_returns_webrtc_case_insensitive(): void
    {
        $this->assertSame('webrtc', DialplanCompiler::inferEndpointType([
            'variable_sip_via_protocol' => 'WSS',
        ]));
    }

    public function test_returns_webrtc_when_both_fields_present(): void
    {
        $this->assertSame('webrtc', DialplanCompiler::inferEndpointType([
            'variable_sip_via_protocol' => 'wss',
            'variable_sip_transport' => 'wss',
        ]));
    }

    public function test_returns_webrtc_even_when_only_transport_is_wss(): void
    {
        // Edge case: via_protocol is something else but transport is wss
        $this->assertSame('webrtc', DialplanCompiler::inferEndpointType([
            'variable_sip_via_protocol' => 'tls',
            'variable_sip_transport' => 'wss',
        ]));
    }
}
