<?php

namespace Tests\Unit\Services;

use App\Services\EslConnectionManager;
use App\Services\SipRegistrationService;
use Mockery;
use Tests\TestCase;

class SipRegistrationServiceTest extends TestCase
{
    public function test_parse_xml_registrations_derives_fusionpbx_style_fields(): void
    {
        $service = new SipRegistrationService(Mockery::mock(EslConnectionManager::class));

        $xml = <<<'XML'
Content-Type: api/response

<profile>
  <registrations>
    <registration>
      <call-id>abc123@192.168.0.69</call-id>
      <user>1001@app.local</user>
      <contact>&quot;&quot; &lt;sip:1001@192.168.0.69:49768;ob&gt;</contact>
      <agent>MicroSIP/3.21.6</agent>
      <status>Registered(UDP)(unknown) exp(2026-04-11 10:31:20) expsecs(298)</status>
      <ping-status>Reachable</ping-status>
      <ping-time>0.00</ping-time>
      <host>1aad433bb9be</host>
      <network-ip>172.20.0.1</network-ip>
      <network-port>34241</network-port>
      <sip-auth-user>1001</sip-auth-user>
      <sip-auth-realm>app.local</sip-auth-realm>
      <mwi-account>1001@app.local</mwi-account>
    </registration>
  </registrations>
</profile>
XML;

        $registrations = $service->parseXmlRegistrations($xml, 'internal');

        $this->assertCount(1, $registrations);
        $this->assertSame('1001', $registrations[0]['reg_user']);
        $this->assertSame('app.local', $registrations[0]['realm']);
        $this->assertSame('MicroSIP/3.21.6', $registrations[0]['agent']);
        $this->assertSame('192.168.0.69', $registrations[0]['lan_ip']);
        $this->assertSame(298, $registrations[0]['expires']);
        $this->assertSame('internal', $registrations[0]['sip_profile_name']);
        $this->assertStringNotContainsString('expsecs', $registrations[0]['status']);
    }
}
