<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\WebRtcTlsSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FreeswitchXmlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    }

    public function test_returns_xml_for_directory_section_request(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'slug' => 'test-tenant',
            'is_active' => true,
        ]);

        $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
            'is_active' => true,
        ]);

        $response = $this->post('/freeswitch/xml-curl', [
            'section' => 'directory',
            'domain' => 'test.example.com',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
        $this->assertStringContainsString('<section name="directory">', $response->getContent());
        $this->assertStringContainsString('id="1001"', $response->getContent());
    }

    public function test_returns_xml_for_dialplan_section_request(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'slug' => 'test-tenant',
            'is_active' => true,
        ]);

        $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
            'is_active' => true,
        ]);

        $response = $this->post('/freeswitch/xml-curl', [
            'section' => 'dialplan',
            'domain' => 'test.example.com',
            'Caller-Destination-Number' => '1001',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
        $this->assertStringContainsString('<section name="dialplan">', $response->getContent());
        $this->assertStringContainsString('nizam_delivery_target_type=extension', $response->getContent());
        $this->assertStringContainsString('call_delivery_entrypoint XML test.example.com', $response->getContent());
    }

    public function test_returns_not_found_for_unknown_section(): void
    {
        $response = $this->post('/freeswitch/xml-curl', [
            'section' => 'configuration',
            'domain' => 'test.example.com',
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('<result status="not found"/>', $response->getContent());
    }

    public function test_returns_valid_empty_xml_when_no_tenant_matches_domain(): void
    {
        $response = $this->post('/freeswitch/xml-curl', [
            'section' => 'directory',
            'domain' => 'nonexistent.example.com',
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('<section name="directory"></section>', $response->getContent());
    }

    public function test_returns_internal_configuration_with_webrtc_transport_using_active_tls_mode(): void
    {
        WebRtcTlsSetting::query()->create([
            'webrtc_enabled' => true,
            'active_mode' => 'self_signed',
            'trusted_ca_enabled' => true,
            'trusted_ca_cert_dir' => '/secure/certs/trusted',
            'self_signed_enabled' => true,
            'self_signed_cert_dir' => '/secure/certs/dev',
        ]);

        $response = $this->post('/freeswitch/xml-curl', [
            'section' => 'configuration',
            'key_name' => 'sofia.conf',
            'profile' => 'internal',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
        $this->assertStringContainsString('<profile name="internal">', $response->getContent());
        $this->assertStringContainsString('name="tls-cert-dir" value="/secure/certs/dev"', $response->getContent());
        $this->assertStringContainsString('name="wss-binding" value=":7443"', $response->getContent());
        $this->assertStringContainsString('name="ws-binding" value=":5066"', $response->getContent());
        $this->assertStringContainsString('name="dtls-srtp" value="true"', $response->getContent());
    }
}
