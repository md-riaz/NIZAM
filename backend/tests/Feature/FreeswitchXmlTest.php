<?php

namespace Tests\Feature;

use App\Models\CallSession;
use App\Models\Did;
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

    public function test_returns_filtered_directory_entry_for_specific_user_lookup(): void
    {
        $tenant = Tenant::create([
            'name' => 'Lookup Tenant',
            'domain' => 'lookup.example.com',
            'slug' => 'lookup-tenant',
            'is_active' => true,
        ]);

        $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
            'is_active' => true,
        ]);

        $tenant->extensions()->create([
            'extension' => '1002',
            'password' => 'secret5678',
            'directory_first_name' => 'Jane',
            'directory_last_name' => 'Smith',
            'is_active' => true,
        ]);

        $response = $this->post('/freeswitch/xml-curl', [
            'section' => 'directory',
            'domain' => 'lookup.example.com',
            'user' => '1001',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
        $this->assertStringContainsString('id="1001"', $response->getContent());
        $this->assertStringNotContainsString('id="1002"', $response->getContent());
    }

    public function test_returns_filtered_directory_entry_when_mod_xml_curl_uses_id_parameter(): void
    {
        $tenant = Tenant::create([
            'name' => 'ID Lookup Tenant',
            'domain' => 'idlookup.example.com',
            'slug' => 'idlookup-tenant',
            'is_active' => true,
        ]);

        $tenant->extensions()->create([
            'extension' => '2001',
            'password' => 'secret1234',
            'directory_first_name' => 'Alice',
            'directory_last_name' => 'Jones',
            'is_active' => true,
        ]);

        $tenant->extensions()->create([
            'extension' => '2002',
            'password' => 'secret5678',
            'directory_first_name' => 'Bob',
            'directory_last_name' => 'Brown',
            'is_active' => true,
        ]);

        $response = $this->post('/freeswitch/xml-curl', [
            'section' => 'directory',
            'domain' => 'idlookup.example.com',
            'id' => '2002',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
        $this->assertStringContainsString('id="2002"', $response->getContent());
        $this->assertStringNotContainsString('id="2001"', $response->getContent());
    }

    public function test_returns_empty_users_when_specific_directory_user_is_missing(): void
    {
        $tenant = Tenant::create([
            'name' => 'Missing Lookup Tenant',
            'domain' => 'missinglookup.example.com',
            'slug' => 'missinglookup-tenant',
            'is_active' => true,
        ]);

        $tenant->extensions()->create([
            'extension' => '3001',
            'password' => 'secret1234',
            'directory_first_name' => 'Chris',
            'directory_last_name' => 'Green',
            'is_active' => true,
        ]);

        $response = $this->post('/freeswitch/xml-curl', [
            'section' => 'directory',
            'domain' => 'missinglookup.example.com',
            'user' => '3999',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
        $this->assertStringContainsString('<users>', $response->getContent());
        $this->assertStringNotContainsString('id="3001"', $response->getContent());
    }

    public function test_dialplan_routes_self_call_without_delivery_orchestrator(): void
    {
        $tenant = Tenant::create([
            'name' => 'Self Call Tenant',
            'domain' => 'selfcall.example.com',
            'slug' => 'selfcall-tenant',
            'is_active' => true,
        ]);

        $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'Self',
            'directory_last_name' => 'Caller',
            'is_active' => true,
        ]);

        $response = $this->post('/freeswitch/xml-curl', [
            'section' => 'dialplan',
            'domain' => 'selfcall.example.com',
            'Caller-Destination-Number' => '1001',
            'Caller-Caller-ID-Number' => '1001',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
        $this->assertStringContainsString('<section name="dialplan">', $response->getContent());
        $this->assertStringContainsString('application="bridge" data="user/1001@selfcall.example.com"', $response->getContent());
        $this->assertStringNotContainsString('call_delivery_entrypoint XML selfcall.example.com', $response->getContent());
    }

    public function test_dialplan_keeps_orchestrator_for_non_self_extension_calls(): void
    {
        $tenant = Tenant::create([
            'name' => 'Internal Call Tenant',
            'domain' => 'internalcall.example.com',
            'slug' => 'internalcall-tenant',
            'is_active' => true,
        ]);

        $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'Caller',
            'directory_last_name' => 'One',
            'is_active' => true,
        ]);

        $tenant->extensions()->create([
            'extension' => '1002',
            'password' => 'secret5678',
            'directory_first_name' => 'Callee',
            'directory_last_name' => 'Two',
            'is_active' => true,
        ]);

        $response = $this->post('/freeswitch/xml-curl', [
            'section' => 'dialplan',
            'domain' => 'internalcall.example.com',
            'Caller-Destination-Number' => '1002',
            'Caller-Caller-ID-Number' => '1001',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
        $this->assertStringContainsString('nizam_delivery_target_type=extension', $response->getContent());
        $this->assertStringContainsString('call_delivery_entrypoint XML internalcall.example.com', $response->getContent());
    }

    public function test_internal_profile_does_not_emit_gateway_include_directive(): void
    {
        $this->seed(SipProfileSeeder::class);

        app(\App\Services\SipProfileCompiler::class)->compileAllToDisk();

        $compiledXml = file_get_contents(storage_path('app/freeswitch/sip_profiles/internal.xml'));

        $this->assertIsString($compiledXml);
        $this->assertStringNotContainsString('internal/*.xml', $compiledXml);
        $this->assertStringNotContainsString('<X-PRE-PROCESS cmd="include" data="internal/*.xml"/>', $compiledXml);
    }

    public function test_external_profile_emits_gateway_include_directive(): void
    {
        $this->seed(SipProfileSeeder::class);

        app(\App\Services\SipProfileCompiler::class)->compileAllToDisk();

        $compiledXml = file_get_contents(storage_path('app/freeswitch/sip_profiles/external.xml'));

        $this->assertIsString($compiledXml);
        $this->assertStringContainsString('<X-PRE-PROCESS cmd="include" data="external/*.xml"/>', $compiledXml);
    }

    public function test_internal_profile_seeder_enables_aggressive_nat_detection(): void
    {
        $this->seed(SipProfileSeeder::class);

        $profile = SipProfile::query()->where('name', 'internal')->first();
        assertNotNull($profile);

        $setting = $profile->settings()->where('name', 'aggressive-nat-detection')->first();

        $this->assertNotNull($setting);
        $this->assertSame('true', $setting->value);
        $this->assertTrue((bool) $setting->is_enabled);
    }

    public function test_internal_profile_dial_string_targets_internal_profile_contacts_only(): void
    {
        $tenant = Tenant::create([
            'name' => 'Dial String Tenant',
            'domain' => 'dialstring.example.com',
            'slug' => 'dialstring-tenant',
            'is_active' => true,
        ]);

        $tenant->extensions()->create([
            'extension' => '4001',
            'password' => 'secret1234',
            'directory_first_name' => 'Dial',
            'directory_last_name' => 'String',
            'is_active' => true,
        ]);

        $response = $this->post('/freeswitch/xml-curl', [
            'section' => 'directory',
            'domain' => 'dialstring.example.com',
            'user' => '4001',
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('${sofia_contact(internal/${dialed_user}@${dialed_domain})}', $response->getContent());
        $this->assertStringNotContainsString('${sofia_contact(*/${dialed_user}@${dialed_domain})}', $response->getContent());
    }

    public function test_non_self_extension_dialplan_response_uses_orchestrator(): void
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

    public function test_dialplan_persists_webrtc_endpoint_type_on_call_session(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'slug' => 'test-tenant-endpoint',
            'is_active' => true,
        ]);

        $did = Did::factory()->create([
            'tenant_id' => $tenant->id,
            'number' => '+15559990001',
            'destination_type' => 'extension',
            'destination_id' => $tenant->extensions()->create([
                'extension' => '2001',
                'password' => 'pass1234',
                'directory_first_name' => 'Jane',
                'directory_last_name' => 'Doe',
                'is_active' => true,
            ])->id,
            'is_active' => true,
        ]);

        $callUuid = 'test-uuid-webrtc-'.uniqid();

        $this->post('/freeswitch/xml-curl', [
            'section' => 'dialplan',
            'domain' => 'test.example.com',
            'Caller-Destination-Number' => '+15559990001',
            'Unique-ID' => $callUuid,
            'variable_sip_via_protocol' => 'wss',
        ]);

        $session = CallSession::where('call_uuid', $callUuid)->first();
        $this->assertNotNull($session, 'CallSession should be created');
        $this->assertSame('webrtc', $session->variables['endpoint_type'] ?? null);
    }

    public function test_dialplan_persists_sip_endpoint_type_on_call_session(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test2.example.com',
            'slug' => 'test-tenant-sip',
            'is_active' => true,
        ]);

        $did = Did::factory()->create([
            'tenant_id' => $tenant->id,
            'number' => '+15559990002',
            'destination_type' => 'extension',
            'destination_id' => $tenant->extensions()->create([
                'extension' => '2002',
                'password' => 'pass1234',
                'directory_first_name' => 'Bob',
                'directory_last_name' => 'Smith',
                'is_active' => true,
            ])->id,
            'is_active' => true,
        ]);

        $callUuid = 'test-uuid-sip-'.uniqid();

        $this->post('/freeswitch/xml-curl', [
            'section' => 'dialplan',
            'domain' => 'test2.example.com',
            'Caller-Destination-Number' => '+15559990002',
            'Unique-ID' => $callUuid,
            'variable_sip_via_protocol' => 'udp',
        ]);

        $session = CallSession::where('call_uuid', $callUuid)->first();
        $this->assertNotNull($session, 'CallSession should be created');
        $this->assertSame('sip', $session->variables['endpoint_type'] ?? null);
    }
}
