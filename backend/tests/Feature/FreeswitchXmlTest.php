<?php

namespace Tests\Feature;

use App\Models\SipProfile;
use App\Models\Tenant;
use Database\Seeders\SipProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

use function PHPUnit\Framework\assertNotNull;

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

    public function test_compiler_outputs_internal_profile_with_enabled_webrtc_settings(): void
    {
        $this->seed(SipProfileSeeder::class);

        $profile = SipProfile::query()->where('name', 'internal')->first();
        assertNotNull($profile);

        $profile->settings()->updateOrCreate(['name' => 'ws-binding'], ['value' => ':5066', 'is_enabled' => true]);
        $profile->settings()->updateOrCreate(['name' => 'wss-binding'], ['value' => ':7443', 'is_enabled' => true]);
        $profile->settings()->updateOrCreate(['name' => 'tls-cert-dir'], ['value' => '/secure/certs/dev', 'is_enabled' => true]);
        $profile->settings()->updateOrCreate(['name' => 'dtls-srtp'], ['value' => 'true', 'is_enabled' => true]);

        app(\App\Services\SipProfileCompiler::class)->compileAllToDisk();

        $compiledXml = file_get_contents(storage_path('app/freeswitch/sip_profiles/internal.xml'));

        $this->assertIsString($compiledXml);
        $this->assertStringContainsString('<profile name="internal">', $compiledXml);
        $this->assertStringContainsString('name="tls-cert-dir" value="/secure/certs/dev"', $compiledXml);
        $this->assertStringContainsString('name="wss-binding" value=":7443"', $compiledXml);
        $this->assertStringContainsString('name="ws-binding" value=":5066"', $compiledXml);
        $this->assertStringContainsString('name="dtls-srtp" value="true"', $compiledXml);
    }
}
