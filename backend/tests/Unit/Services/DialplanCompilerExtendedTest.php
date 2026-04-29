<?php

namespace Tests\Unit\Services;

use App\Models\EndpointBinding;
use App\Models\Queue;
use App\Models\Organization;
use Illuminate\Support\Str;
use App\Services\DialplanCompiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DialplanCompilerExtendedTest extends TestCase
{
    use RefreshDatabase;

    private DialplanCompiler $compiler;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);

        $this->compiler = new DialplanCompiler(
            app(\App\Services\Routing\NumberRoutingService::class),
            app(\App\Services\Routing\GatewayResolutionService::class),
            app(\App\Services\Routing\BridgeCompiler::class),
            app(\App\Services\Routing\RoutingGraphCompiler::class),
        );
    }

    public function test_compile_did_routing_to_extension(): void
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'domain' => 'test.example.com',
            'is_active' => true,
        ]);

        $extension = $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'is_active' => true,
        ]);

        $did = $organization->dids()->create([
            'number' => '+15551234567',
            'destination_type' => 'extension',
            'destination_id' => $extension->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan('test.example.com', '+15551234567');

        $this->assertStringContainsString('section name="dialplan"', $xml);
        $this->assertStringContainsString('nizam_delivery_target_type=extension', $xml);
        $this->assertStringContainsString('nizam_delivery_target_id='.$extension->id, $xml);
        $this->assertStringContainsString('application="transfer" data="call_delivery_entrypoint XML test.example.com"', $xml);
        $this->assertStringNotContainsString('user/1001@test.example.com', $xml);
    }

    public function test_compile_did_routing_to_ring_group_simultaneous(): void
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'domain' => 'test.example.com',
            'is_active' => true,
        ]);

        $ext1 = $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'is_active' => true,
        ]);

        $ext2 = $organization->extensions()->create([
            'extension' => '1002',
            'password' => 'secret1234',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'is_active' => true,
        ]);

        $ringGroup = $organization->ringGroups()->create([
            'name' => 'Sales',
            'strategy' => 'simultaneous',
            'ring_timeout' => 30,
            'members' => [$ext1->id, $ext2->id],
            'is_active' => true,
        ]);

        $did = $organization->dids()->create([
            'number' => '+15551234567',
            'destination_type' => 'ring_group',
            'destination_id' => $ringGroup->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan('test.example.com', '+15551234567');

        $this->assertStringContainsString('call_timeout=30', $xml);
        $this->assertStringContainsString('nizam_delivery_target_type=ring_group', $xml);
        $this->assertStringContainsString('nizam_delivery_target_id='.$ringGroup->id, $xml);
        $this->assertStringContainsString('application="transfer" data="call_delivery_entrypoint XML test.example.com"', $xml);
        $this->assertStringNotContainsString('user/1001@test.example.com', $xml);
        $this->assertStringNotContainsString('user/1002@test.example.com', $xml);
    }

    public function test_compile_did_routing_to_ring_group_sequential(): void
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'domain' => 'test.example.com',
            'is_active' => true,
        ]);

        $ext1 = $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'is_active' => true,
        ]);

        $ringGroup = $organization->ringGroups()->create([
            'name' => 'Support',
            'strategy' => 'sequential',
            'ring_timeout' => 20,
            'members' => [$ext1->id],
            'is_active' => true,
        ]);

        $did = $organization->dids()->create([
            'number' => '+15559876543',
            'destination_type' => 'ring_group',
            'destination_id' => $ringGroup->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan('test.example.com', '+15559876543');

        $this->assertStringContainsString('call_timeout=20', $xml);
        $this->assertStringContainsString('nizam_delivery_target_type=ring_group', $xml);
        $this->assertStringContainsString('nizam_delivery_target_id='.$ringGroup->id, $xml);
        $this->assertStringContainsString('application="transfer" data="call_delivery_entrypoint XML test.example.com"', $xml);
        $this->assertStringNotContainsString('user/1001@test.example.com', $xml);
    }

    public function test_compile_did_routing_to_extension_with_follow_me_sets_pstn_bridge_metadata(): void
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'domain' => 'test.example.com',
            'is_active' => true,
        ]);

        $extension = $organization->extensions()->create([
            'extension' => '1003',
            'password' => 'secret1234',
            'first_name' => 'Follow',
            'last_name' => 'Me',
            'is_active' => true,
            'follow_me_enabled' => true,
            'follow_me_destination' => '+15557654321',
        ]);

        EndpointBinding::query()->create([
            'organization_id' => $organization->id,
            'extension_id' => $extension->id,
            'type' => EndpointBinding::TYPE_PSTN_FORWARD,
            'device_uuid' => 'follow-me-'.$extension->id,
            'platform' => EndpointBinding::PLATFORM_UNKNOWN,
            'is_push_capable' => false,
            'is_enabled' => true,
            'rings_immediately_when_online' => false,
            'allow_late_join_after_push' => false,
            'forward_number' => '+15557654321',
            'forward_requires_confirm' => true,
        ]);

        $did = $organization->dids()->create([
            'number' => '+15550001003',
            'destination_type' => 'extension',
            'destination_id' => $extension->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan('test.example.com', '+15550001003');

        $this->assertStringContainsString('call_timeout=25', $xml);
        $this->assertStringContainsString('delivery_pstn_delay_seconds=25', $xml);
        $this->assertStringContainsString('nizam_delivery_target_type=extension', $xml);
        $this->assertStringContainsString('nizam_delivery_target_id='.$extension->id, $xml);
        $this->assertStringContainsString('application="transfer" data="call_delivery_entrypoint XML test.example.com"', $xml);
    }

    public function test_compile_did_routing_to_extension_with_dnd_returns_busy(): void
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'domain' => 'test.example.com',
            'is_active' => true,
        ]);

        $extension = $organization->extensions()->create([
            'extension' => '1004',
            'password' => 'secret1234',
            'first_name' => 'Do',
            'last_name' => 'NotDisturb',
            'is_active' => true,
            'dnd_enabled' => true,
        ]);

        $organization->dids()->create([
            'number' => '+15550001004',
            'destination_type' => 'extension',
            'destination_id' => $extension->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan('test.example.com', '+15550001004');

        $this->assertStringContainsString('application="respond" data="486"', $xml);
        $this->assertStringNotContainsString('call_delivery_entrypoint XML test.example.com', $xml);
    }

    public function test_compile_did_routing_to_queue_uses_shared_delivery_entrypoint(): void
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'domain' => 'test.example.com',
            'is_active' => true,
        ]);

        $extension = $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'is_active' => true,
        ]);

        $agent = $organization->agents()->create([
            'extension_id' => $extension->id,
            'name' => 'Queue Agent',
            'status' => 'available',
            'is_active' => true,
        ]);

        $queue = Queue::factory()->create([
            'organization_id' => $organization->id,
            'strategy' => Queue::STRATEGY_RING_ALL,
            'is_active' => true,
        ]);
        $queue->members()->attach($agent->id, ['id' => Str::uuid(), 'priority' => 1]);

        $did = $organization->dids()->create([
            'number' => '+15551111111',
            'destination_type' => 'queue',
            'destination_id' => $queue->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan('test.example.com', '+15551111111');

        $this->assertStringContainsString('nizam_delivery_target_type=queue', $xml);
        $this->assertStringContainsString('nizam_delivery_target_id='.$queue->id, $xml);
        $this->assertStringContainsString('application="transfer" data="call_delivery_entrypoint XML test.example.com"', $xml);
    }

    public function test_compile_did_routing_to_agent_uses_shared_delivery_entrypoint(): void
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'domain' => 'test.example.com',
            'is_active' => true,
        ]);

        $extension = $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'is_active' => true,
        ]);

        $agent = $organization->agents()->create([
            'extension_id' => $extension->id,
            'name' => 'Direct Agent',
            'status' => 'available',
            'is_active' => true,
        ]);

        $did = $organization->dids()->create([
            'number' => '+15552221111',
            'destination_type' => 'agent',
            'destination_id' => $agent->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan('test.example.com', '+15552221111');

        $this->assertStringContainsString('nizam_delivery_target_type=agent', $xml);
        $this->assertStringContainsString('nizam_delivery_target_id='.$agent->id, $xml);
        $this->assertStringContainsString('application="transfer" data="call_delivery_entrypoint XML test.example.com"', $xml);
    }

    public function test_compile_did_routing_to_ivr(): void
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'domain' => 'test.example.com',
            'is_active' => true,
        ]);

        $ivr = $organization->ivrs()->create([
            'name' => 'Main Menu',
            'timeout' => 5,
            'max_failures' => 3,
            'options' => [],
            'is_active' => true,
        ]);

        $did = $organization->dids()->create([
            'number' => '+15551111111',
            'destination_type' => 'ivr',
            'destination_id' => $ivr->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan('test.example.com', '+15551111111');

        $this->assertStringContainsString('ivr', $xml);
        $this->assertStringContainsString('Main Menu', $xml);
    }

    public function test_compile_did_routing_to_voicemail(): void
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'domain' => 'test.example.com',
            'is_active' => true,
        ]);

        $extension = $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'is_active' => true,
        ]);

        $did = $organization->dids()->create([
            'number' => '+15552222222',
            'destination_type' => 'voicemail',
            'destination_id' => $extension->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan('test.example.com', '+15552222222');

        $this->assertStringContainsString('voicemail', $xml);
        $this->assertStringContainsString('test.example.com', $xml);
        $this->assertStringContainsString('1001', $xml);
    }

    public function test_compile_did_routing_to_time_condition(): void
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'domain' => 'test.example.com',
            'is_active' => true,
        ]);

        $extension = $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'is_active' => true,
        ]);

        $timeCondition = $organization->timeConditions()->create([
            'name' => 'Business Hours',
            'conditions' => [
                ['wday' => 'mon-fri', 'time_from' => '09:00', 'time_to' => '17:00'],
            ],
            'match_destination_type' => 'extension',
            'match_destination_id' => $extension->id,
            'no_match_destination_type' => 'voicemail',
            'no_match_destination_id' => $extension->id,
            'is_active' => true,
        ]);

        $did = $organization->dids()->create([
            'number' => '+15553333333',
            'destination_type' => 'time_condition',
            'destination_id' => $timeCondition->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan('test.example.com', '+15553333333');

        $this->assertStringContainsString('section name="dialplan"', $xml);
        $this->assertStringContainsString('nizam_delivery_target_type=extension', $xml);
        $this->assertStringContainsString('nizam_delivery_target_id='.$extension->id, $xml);
        $this->assertStringContainsString('application="transfer" data="call_delivery_entrypoint XML test.example.com"', $xml);
    }

    public function test_compile_dialplan_includes_convenience_service_routes(): void
    {
        $organization = Organization::create([
            'name' => 'Convenience Organization',
            'domain' => 'pbx.example.com',
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

        $xml = $this->compiler->compileDialplan($organization->domain, '*98');

        $this->assertStringContainsString('extension name="voicemail-main"', $xml);
        $this->assertStringContainsString('destination_number" expression="^\*98$"', $xml);
        $this->assertStringContainsString('voicemail" data="check default pbx.example.com 3000"', $xml);
        $this->assertStringContainsString('destination_number" expression="^\*99$"', $xml);
        $this->assertStringContainsString('voicemail" data="default pbx.example.com 3000"', $xml);
        $this->assertStringContainsString('destination_number" expression="^\*78$"', $xml);
        $this->assertStringContainsString('nizam_dnd_enabled=true', $xml);
        $this->assertStringContainsString('destination_number" expression="^\*79$"', $xml);
        $this->assertStringContainsString('nizam_dnd_enabled=false', $xml);
        $this->assertStringContainsString('destination_number" expression="^\*72$"', $xml);
        $this->assertStringContainsString('destination_number" expression="^\*72(.+)$"', $xml);
        $this->assertStringContainsString('/usr/local/freeswitch/scripts/custom/_call_forward.lua activate', $xml);
        $this->assertStringContainsString('destination_number" expression="^\*73$"', $xml);
        $this->assertStringContainsString('/usr/local/freeswitch/scripts/custom/_call_forward.lua disable', $xml);
        $this->assertStringContainsString('destination_number" expression="^\*74$"', $xml);
        $this->assertStringContainsString('/usr/local/freeswitch/scripts/custom/_call_forward.lua restore', $xml);
        $this->assertStringContainsString('destination_number" expression="^\*69$"', $xml);
        $this->assertStringContainsString('Call return starter route requested', $xml);
        $this->assertStringContainsString('destination_number" expression="^0$"', $xml);
        $this->assertStringContainsString('transfer" data="2000 XML pbx.example.com"', $xml);
    }

    public function test_compile_convenience_routes_fall_back_to_primary_extension_targets(): void
    {
        $organization = Organization::create([
            'name' => 'Fallback Convenience Organization',
            'domain' => 'fallback.example.com',
            'is_active' => true,
        ]);

        $organization->extensions()->create([
            'extension' => '4100',
            'password' => 'secret1234',
            'first_name' => 'Fallback',
            'last_name' => 'Primary',
            'is_active' => true,
            'is_primary' => true,
        ]);

        $xml = $this->compiler->compileDialplan($organization->domain, '0');

        $this->assertStringContainsString('transfer" data="4100 XML fallback.example.com"', $xml);
        $this->assertStringNotContainsString('voicemail" data="check default fallback.example.com 4100"', $xml);
    }

    public function test_compile_convenience_routes_ignore_invalid_configured_voicemail_target(): void
    {
        $organization = Organization::create([
            'name' => 'Invalid Voicemail Target Organization',
            'domain' => 'invalid-voicemail.example.com',
            'is_active' => true,
            'settings' => [
                'business_phone' => [
                    'voicemail' => ['main_extension' => '9999'],
                    'operator' => ['extension' => '4100'],
                ],
            ],
        ]);

        $organization->extensions()->create([
            'extension' => '4100',
            'password' => 'secret1234',
            'first_name' => 'Fallback',
            'last_name' => 'Operator',
            'is_active' => true,
            'is_primary' => true,
        ]);

        $xml = $this->compiler->compileDialplan($organization->domain, '*98');

        $this->assertStringContainsString('extension name="voicemail-main"', $xml);
        $this->assertStringContainsString('respond" data="404"', $xml);
        $this->assertStringNotContainsString('voicemail" data="check default invalid-voicemail.example.com 4100"', $xml);
    }

    public function test_compile_convenience_routes_require_active_operator_target_for_operator_shortcut(): void
    {
        $organization = Organization::create([
            'name' => 'Invalid Operator Target Organization',
            'domain' => 'invalid-operator.example.com',
            'is_active' => true,
            'settings' => [
                'business_phone' => [
                    'operator' => ['extension' => '9999'],
                ],
            ],
        ]);

        $xml = $this->compiler->compileDialplan($organization->domain, '0');

        $this->assertStringContainsString('destination_number" expression="^0$"', $xml);
        $this->assertStringContainsString('respond" data="404"', $xml);
        $this->assertStringNotContainsString('transfer" data="9999 XML invalid-operator.example.com"', $xml);
    }

    public function test_compile_convenience_routes_make_starter_routes_explicitly_unconfigured(): void
    {
        $organization = Organization::create([
            'name' => 'Starter Routes Organization',
            'domain' => 'starter-routes.example.com',
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan($organization->domain, '*69');

        $this->assertStringContainsString('Call return starter route requested by ${caller_id_number}; call return is not configured yet', $xml);
        $this->assertStringContainsString('respond" data="404"', $xml);
        $this->assertStringContainsString('destination_number" expression="^\*69$"', $xml);
        $this->assertStringContainsString('nizam_convenience_route=call_return', $xml);
        $this->assertStringNotContainsString('nizam_convenience_route=unknown', $xml);
    }

    public function test_compile_convenience_routes_include_directed_pickup_group_pickup_and_parking(): void
    {
        $organization = Organization::create([
            'name' => 'Feature Codes Organization',
            'domain' => 'features.example.com',
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan($organization->domain, '**1001');

        $this->assertStringContainsString('extension name="pickup-direct"', $xml);
        $this->assertStringContainsString('destination_number" expression="^\*\*(.+)$"', $xml);
        $this->assertStringContainsString('application="lua" data="/usr/local/freeswitch/scripts/custom/_directed_pickup.lua $1"', $xml);
        $this->assertStringContainsString('extension name="pickup-group"', $xml);
        $this->assertStringContainsString('destination_number" expression="^\*8$"', $xml);
        $this->assertStringContainsString('application="lua" data="/usr/local/freeswitch/scripts/custom/_group_pickup.lua inbound"', $xml);
        $this->assertStringContainsString('extension name="intercom-prefix"', $xml);
        $this->assertStringContainsString('destination_number" expression="^\*8(\d{2,7})$"', $xml);
        $this->assertStringContainsString('application="set" data="nizam_auto_answer_enabled=true"', $xml);
        $this->assertStringContainsString('application="set" data="nizam_auto_answer_call_info=answer-after=0"', $xml);
        $this->assertStringContainsString('application="set" data="nizam_auto_answer_alert_info=intercom"', $xml);
        $this->assertStringContainsString('application="export" data="sip_auto_answer=true"', $xml);
        $this->assertStringContainsString('application="export" data="sip_h_Call-Info=answer-after=0"', $xml);
        $this->assertStringContainsString('application="transfer" data="call_delivery_entrypoint XML features.example.com"', $xml);
        $this->assertStringContainsString('extension name="paging-prefix"', $xml);
        $this->assertStringContainsString('destination_number" expression="^\*80(\d{2,7})$"', $xml);
        $this->assertStringContainsString('application="set" data="nizam_paging_target_extension=$1"', $xml);
        $this->assertStringContainsString('application="transfer" data="call_delivery_entrypoint XML features.example.com"', $xml);
        $this->assertStringContainsString('extension name="park-auto"', $xml);
        $this->assertStringContainsString('destination_number" expression="^(park\\+)?\*5900$"', $xml);
        $this->assertStringContainsString('application="set" data="nizam_parking_lot=park"', $xml);
        $this->assertStringContainsString('application="lua" data="/usr/local/freeswitch/scripts/custom/_valet_park.lua park *5900 5901 5999"', $xml);
        $this->assertStringContainsString('extension name="park-auto-orbit"', $xml);
        $this->assertStringContainsString('destination_number" expression="^(?:park\\+)?(59(0[1-9]|[1-9][0-9]))$"', $xml);
        $this->assertStringContainsString('application="valet_park" data="*5900@${context} $1"', $xml);
    }

    public function test_compile_directory_with_voicemail_settings(): void
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
            'voicemail_enabled' => true,
            'voicemail_pin' => '1234',
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDirectory('test.example.com');

        $this->assertStringContainsString('vm-password', $xml);
        $this->assertStringContainsString('vm-enabled', $xml);
        $this->assertStringContainsString('1234', $xml);
    }

    public function test_compile_directory_with_caller_id(): void
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
            'effective_caller_id_name' => 'John Doe',
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDirectory('test.example.com');

        $this->assertStringContainsString('effective_caller_id_name', $xml);
        $this->assertStringContainsString('John Doe', $xml);
        $this->assertStringContainsString('effective_caller_id_number', $xml);
        $this->assertStringContainsString('1001', $xml);
        $this->assertStringNotContainsString('outbound_caller_id_name', $xml);
        $this->assertStringNotContainsString('outbound_caller_id_number', $xml);
    }

    public function test_inactive_organization_returns_empty_directory(): void
    {
        Organization::create([
            'name' => 'Inactive Organization',
            'domain' => 'inactive.example.com',
            'is_active' => false,
        ]);

        $xml = $this->compiler->compileDirectory('inactive.example.com');

        $this->assertStringContainsString('section name="directory"', $xml);
        $this->assertStringNotContainsString('<user', $xml);
    }

    public function test_inactive_extension_excluded_from_directory(): void
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'domain' => 'test.example.com',
            'is_active' => true,
        ]);

        $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'first_name' => 'Active',
            'last_name' => 'User',
            'is_active' => true,
        ]);

        $organization->extensions()->create([
            'extension' => '1002',
            'password' => 'secret1234',
            'first_name' => 'Inactive',
            'last_name' => 'User',
            'is_active' => false,
        ]);

        $xml = $this->compiler->compileDirectory('test.example.com');

        $this->assertStringContainsString('id="1001"', $xml);
        $this->assertStringNotContainsString('id="1002"', $xml);
    }

    public function test_inactive_did_not_routed(): void
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'domain' => 'test.example.com',
            'is_active' => true,
        ]);

        $extension = $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'is_active' => true,
        ]);

        $organization->dids()->create([
            'number' => '+15559999999',
            'destination_type' => 'extension',
            'destination_id' => $extension->id,
            'is_active' => false,
        ]);

        $xml = $this->compiler->compileDialplan('test.example.com', '+15559999999');

        $this->assertStringContainsString('section name="dialplan"', $xml);
        $this->assertStringNotContainsString('user/1001', $xml);
    }
}
