<?php

namespace Tests\Feature;

use App\Models\CallSession;
use App\Models\Did;
use App\Models\SipProfile;
use App\Models\Organization;
use App\Models\OrganizationDialplanManifest;
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
        $organization = Organization::create([
            'name' => 'Test Organization',
            'domain' => 'test.example.com',
            'is_active' => true,
        ]);

        $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
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
        $organization = Organization::create([
            'name' => 'Lookup Organization',
            'domain' => 'lookup.example.com',
            'is_active' => true,
        ]);

        $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'is_active' => true,
        ]);

        $organization->extensions()->create([
            'extension' => '1002',
            'password' => 'secret5678',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
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
        $organization = Organization::create([
            'name' => 'ID Lookup Organization',
            'domain' => 'idlookup.example.com',
            'is_active' => true,
        ]);

        $organization->extensions()->create([
            'extension' => '2001',
            'password' => 'secret1234',
            'first_name' => 'Alice',
            'last_name' => 'Jones',
            'is_active' => true,
        ]);

        $organization->extensions()->create([
            'extension' => '2002',
            'password' => 'secret5678',
            'first_name' => 'Bob',
            'last_name' => 'Brown',
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
        $organization = Organization::create([
            'name' => 'Missing Lookup Organization',
            'domain' => 'missinglookup.example.com',
            'is_active' => true,
        ]);

        $organization->extensions()->create([
            'extension' => '3001',
            'password' => 'secret1234',
            'first_name' => 'Chris',
            'last_name' => 'Green',
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
        $organization = Organization::create([
            'name' => 'Self Call Organization',
            'domain' => 'selfcall.example.com',
            'is_active' => true,
        ]);

        $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'first_name' => 'Self',
            'last_name' => 'Caller',
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
        $this->assertStringNotContainsString('call_delivery_entrypoint XML selfcall.example.com', $response->getContent());
    }

    public function test_dialplan_routes_self_call_to_voicemail_check(): void
    {
        $organization = Organization::create([
            'name' => 'Self Call Parity Organization',
            'domain' => 'parity.example.com',
            'is_active' => true,
        ]);

        $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'first_name' => 'Self',
            'last_name' => 'Caller',
            'is_active' => true,
        ]);

        $response = $this->post('/freeswitch/xml-curl', [
            'section' => 'dialplan',
            'domain' => 'parity.example.com',
            'Caller-Destination-Number' => '1001',
            'Caller-Caller-ID-Number' => '1001',
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('application="answer"', $response->getContent());
        $this->assertStringContainsString('application="voicemail" data="check default parity.example.com 1001"', $response->getContent());
    }

    public function test_dialplan_keeps_orchestrator_for_non_self_extension_calls(): void
    {
        $organization = Organization::create([
            'name' => 'Internal Call Organization',
            'domain' => 'internalcall.example.com',
            'is_active' => true,
        ]);

        $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'first_name' => 'Caller',
            'last_name' => 'One',
            'is_active' => true,
        ]);

        $organization->extensions()->create([
            'extension' => '1002',
            'password' => 'secret5678',
            'first_name' => 'Callee',
            'last_name' => 'Two',
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

    public function test_dialplan_returns_directed_pickup_xml(): void
    {
        Organization::create([
            'name' => 'Pickup Organization',
            'domain' => 'pickup.example.com',
            'is_active' => true,
        ]);

        $response = $this->post('/freeswitch/xml-curl', [
            'section' => 'dialplan',
            'domain' => 'pickup.example.com',
            'Caller-Destination-Number' => '**1002',
            'Caller-Caller-ID-Number' => '1001',
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('application="lua" data="/usr/local/freeswitch/scripts/custom/_directed_pickup.lua $1"', $response->getContent());
    }

    public function test_dialplan_returns_parking_xml_for_auto_and_orbit_pickup(): void
    {
        Organization::create([
            'name' => 'Parking Organization',
            'domain' => 'parking.example.com',
            'is_active' => true,
        ]);

        $autoResponse = $this->post('/freeswitch/xml-curl', [
            'section' => 'dialplan',
            'domain' => 'parking.example.com',
            'Caller-Destination-Number' => '*5900',
            'Caller-Caller-ID-Number' => '1001',
        ]);

        $autoResponse->assertStatus(200);
        $this->assertStringContainsString('application="set" data="nizam_parking_lot=park"', $autoResponse->getContent());
        $this->assertStringContainsString('application="lua" data="/usr/local/freeswitch/scripts/custom/_valet_park.lua park *5900 5901 5999"', $autoResponse->getContent());

        $orbitResponse = $this->post('/freeswitch/xml-curl', [
            'section' => 'dialplan',
            'domain' => 'parking.example.com',
            'Caller-Destination-Number' => '5901',
            'Caller-Caller-ID-Number' => '1001',
        ]);

        $orbitResponse->assertStatus(200);
        $this->assertStringContainsString('application="valet_park" data="*5900@${context} $1"', $orbitResponse->getContent());
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
        $organization = Organization::create([
            'name' => 'Dial String Organization',
            'domain' => 'dialstring.example.com',
            'is_active' => true,
        ]);

        $organization->extensions()->create([
            'extension' => '4001',
            'password' => 'secret1234',
            'first_name' => 'Dial',
            'last_name' => 'String',
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
        $organization = Organization::create([
            'name' => 'Test Organization',
            'domain' => 'test.example.com',
            'is_active' => true,
        ]);

        $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
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

    public function test_xml_curl_returns_convenience_service_route_dialplan(): void
    {
        $organization = Organization::create([
            'name' => 'Convenience XML Organization',
            'domain' => 'xml-convenience.example.com',
            'is_active' => true,
            'settings' => [
                'business_phone' => [
                    'operator' => ['extension' => '2000'],
                    'voicemail' => ['main_extension' => '3000'],
                ],
            ],
        ]);

        $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'first_name' => 'Primary',
            'last_name' => 'User',
            'is_active' => true,
            'is_primary' => true,
        ]);

        $organization->extensions()->create([
            'extension' => '2000',
            'password' => 'secret1234',
            'first_name' => 'Operator',
            'last_name' => 'User',
            'is_active' => true,
        ]);

        $organization->extensions()->create([
            'extension' => '3000',
            'password' => 'secret1234',
            'first_name' => 'Voicemail',
            'last_name' => 'User',
            'is_active' => true,
        ]);

        $response = $this->post('/freeswitch/xml-curl', [
            'section' => 'dialplan',
            'domain' => 'xml-convenience.example.com',
            'Caller-Destination-Number' => '*98',
            'Caller-Caller-ID-Number' => '1001',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
        $this->assertStringContainsString('extension name="voicemail-main"', $response->getContent());
        $this->assertStringContainsString('voicemail" data="check default xml-convenience.example.com 3000"', $response->getContent());
        $this->assertStringContainsString('destination_number" expression="^\*69$"', $response->getContent());
        $this->assertStringContainsString('transfer" data="2000 XML xml-convenience.example.com"', $response->getContent());
    }

    public function test_xml_curl_returns_intercom_and_paging_convenience_dialplan(): void
    {
        $organization = Organization::create([
            'name' => 'Intercom XML Organization',
            'domain' => 'xml-intercom.example.com',
            'is_active' => true,
        ]);

        $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'first_name' => 'Primary',
            'last_name' => 'User',
            'is_active' => true,
            'is_primary' => true,
        ]);

        $response = $this->post('/freeswitch/xml-curl', [
            'section' => 'dialplan',
            'domain' => 'xml-intercom.example.com',
            'Caller-Destination-Number' => '*81001',
            'Caller-Caller-ID-Number' => '2000',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
        $this->assertStringContainsString('extension name="intercom-prefix"', $response->getContent());
        $this->assertStringContainsString('application="set" data="nizam_auto_answer_enabled=true"', $response->getContent());
        $this->assertStringContainsString('application="export" data="sip_auto_answer=true"', $response->getContent());
        $this->assertStringContainsString('application="export" data="sip_h_Call-Info=answer-after=0"', $response->getContent());
        $this->assertStringContainsString('application="transfer" data="call_delivery_entrypoint XML xml-intercom.example.com"', $response->getContent());
        $this->assertStringContainsString('destination_number" expression="^\*80(\d{2,7})$"', $response->getContent());
    }

    public function test_manifest_builder_persists_convenience_routes_in_active_manifest(): void
    {
        $organization = Organization::create([
            'name' => 'Manifest Convenience Organization',
            'domain' => 'manifest-convenience.example.com',
            'is_active' => true,
            'settings' => [
                'business_phone' => [
                    'operator' => ['extension' => '6100'],
                ],
            ],
        ]);

        $organization->extensions()->create([
            'extension' => '6100',
            'password' => 'secret1234',
            'first_name' => 'Front',
            'last_name' => 'Desk',
            'is_active' => true,
            'is_primary' => true,
        ]);

        app(\App\Services\OrganizationManifestBuilder::class)->buildAndActivate($organization->fresh());

        $manifest = OrganizationDialplanManifest::query()
            ->where('organization_id', $organization->id)
            ->where('manifest_type', 'inbound_routing')
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($manifest);
        $this->assertStringContainsString('extension name="voicemail-main"', $manifest->content);
        $this->assertStringContainsString('destination_number" expression="^\*78$"', $manifest->content);
        $this->assertStringContainsString('destination_number" expression="^\*79$"', $manifest->content);
        $this->assertStringContainsString('transfer" data="6100 XML manifest-convenience.example.com"', $manifest->content);
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

    public function test_returns_valid_empty_xml_when_no_organization_matches_domain(): void
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
        $organization = Organization::create([
            'name' => 'Test Organization',
            'domain' => 'test.example.com',
            'is_active' => true,
        ]);

        $did = Did::factory()->create([
            'organization_id' => $organization->id,
            'number' => '+15559990001',
            'destination_type' => 'extension',
            'destination_id' => $organization->extensions()->create([
                'extension' => '2001',
                'password' => 'pass1234',
                'first_name' => 'Jane',
                'last_name' => 'Doe',
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
        $organization = Organization::create([
            'name' => 'Test Organization',
            'domain' => 'test2.example.com',
            'is_active' => true,
        ]);

        $did = Did::factory()->create([
            'organization_id' => $organization->id,
            'number' => '+15559990002',
            'destination_type' => 'extension',
            'destination_id' => $organization->extensions()->create([
                'extension' => '2002',
                'password' => 'pass1234',
                'first_name' => 'Bob',
                'last_name' => 'Smith',
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
