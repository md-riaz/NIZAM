<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\SipStatusController;
use App\Models\SipProfile;
use App\Models\User;
use App\Services\EslConnectionManager;
use App\Services\SipRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SipStatusControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_registrations_endpoint_parses_xml_and_includes_agent(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'organization_id' => null,
        ]);

        SipProfile::create([
            'name' => 'internal',
            'description' => 'Internal',
            'is_active' => true,
        ]);

        $xml = <<<'XML'
Content-Type: api/response

<profile>
  <registrations>
    <registration>
      <user>1001@app.local</user>
      <agent>MicroSIP/3.21.6</agent>
      <contact>&lt;sip:1001@192.168.0.69:49768;ob&gt;</contact>
      <host>1aad433bb9be</host>
      <network-ip>172.20.0.1</network-ip>
      <network-port>48116</network-port>
      <sip-auth-user>1001</sip-auth-user>
      <sip-auth-realm>app.local</sip-auth-realm>
      <status>Registered(UDP-NAT) expsecs(298)</status>
      <ping-time>0.00</ping-time>
    </registration>
  </registrations>
</profile>
XML;

        $esl = Mockery::mock(EslConnectionManager::class);
        $esl->shouldReceive('api')
            ->once()
            ->with('sofia xmlstatus profile internal reg')
            ->andReturn($xml);

        $this->app->instance(EslConnectionManager::class, $esl);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/sip-status/registrations');

        $response->assertOk()
            ->assertJsonPath('data.0.user', '1001@app.local')
            ->assertJsonPath('data.0.reg_user', '1001')
            ->assertJsonPath('data.0.realm', 'app.local')
            ->assertJsonPath('data.0.agent', 'MicroSIP/3.21.6')
            ->assertJsonPath('data.0.expires', 298)
            ->assertJsonPath('data.0.sip_profile_name', 'internal');
    }

    public function test_parse_profiles_keeps_real_profile_names(): void
    {
        $controller = new SipStatusController(
            Mockery::mock(EslConnectionManager::class),
            Mockery::mock(SipRegistrationService::class)
        );

        $raw = <<<'TEXT'
                     Name      Type                                      Data State
=================================================================================================
                 external profile sip:mod_sofia@172.20.0.8:5080 RUNNING (0)
                 internal profile sip:mod_sofia@172.20.0.8:5060 RUNNING (0)
=================================================================================================
2 profiles 0 aliases
TEXT;

        $method = new \ReflectionMethod($controller, 'parseProfiles');
        $method->setAccessible(true);
        $profiles = $method->invoke($controller, $raw);

        $this->assertSame('external', $profiles[0]['name']);
        $this->assertSame('internal', $profiles[1]['name']);
        $this->assertSame('RUNNING', $profiles[0]['status']);
    }
}
