<?php

namespace Tests\Unit\Services;

use App\Models\CallSession;
use App\Models\Did;
use App\Models\Extension;
use App\Models\Gateway;
use App\Models\Organization;
use App\Services\Call\OutboundOriginateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class OutboundOriginateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_internal_dialplan_originate_command_with_default_outbound_did(): void
    {
        $organization = Organization::factory()->create(['domain' => 'acme.test']);
        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'extension' => '1001',
            'first_name' => 'John',
            'effective_caller_id_name' => 'John Doe',
        ]);
        $did = Did::factory()->create([
            'organization_id' => $organization->id,
            'gateway_id' => null,
            'number' => '+15551234567',
            'normalized_number' => '+15551234567',
        ]);
        $extension->allowedOutboundDids()->attach($did->id);
        $extension->update(['default_outbound_did_id' => $did->id]);

        $service = app(OutboundOriginateService::class);
        $command = $service->buildCommand($organization, $extension->fresh(), '2001');

        $this->assertStringContainsString('origination_caller_id_name=John Doe', $command);
        $this->assertStringContainsString('origination_caller_id_number=+15551234567', $command);
        $this->assertStringContainsString('user/1001@acme.test 2001 XML acme.test', $command);
        $this->assertStringContainsString('origination_uuid=', $command);
    }

    public function test_builds_gateway_bridge_originate_command_when_did_gateway_is_allowed(): void
    {
        $organization = Organization::factory()->create(['domain' => 'acme.test']);
        $gateway = Gateway::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'extension' => '1001',
            'first_name' => 'John',
            'effective_caller_id_name' => 'John Doe',
            'default_outbound_gateway_id' => $gateway->id,
        ]);
        $did = Did::factory()->create([
            'organization_id' => $organization->id,
            'gateway_id' => $gateway->id,
            'number' => '+15551234567',
            'normalized_number' => '+15551234567',
        ]);
        $extension->allowedOutboundDids()->attach($did->id);
        $extension->allowedOutboundGateways()->attach($gateway->id);
        $extension->update(['default_outbound_did_id' => $did->id]);

        $service = app(OutboundOriginateService::class);
        $command = $service->buildCommand($organization, $extension->fresh(), '+15557654321');

        $this->assertStringContainsString('origination_caller_id_name=John Doe', $command);
        $this->assertStringContainsString('origination_caller_id_number=+15551234567', $command);
        $this->assertStringContainsString(
            sprintf('user/1001@acme.test &bridge(sofia/gateway/v_%s/+15557654321)', $gateway->id),
            $command
        );
        $this->assertStringContainsString('origination_uuid=', $command);
    }

    public function test_rejects_requested_gateway_not_allowed_for_extension(): void
    {
        $organization = Organization::factory()->create(['domain' => 'acme.test']);
        $allowedGateway = Gateway::factory()->create(['organization_id' => $organization->id]);
        $blockedGateway = Gateway::factory()->create(['organization_id' => $organization->id]);
        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'extension' => '1001',
            'first_name' => 'John',
            'default_outbound_gateway_id' => $allowedGateway->id,
        ]);
        $did = Did::factory()->create([
            'organization_id' => $organization->id,
            'gateway_id' => null,
            'number' => '+15551234567',
            'normalized_number' => '+15551234567',
        ]);
        $extension->allowedOutboundDids()->attach($did->id);
        $extension->allowedOutboundGateways()->attach($allowedGateway->id);
        $extension->update(['default_outbound_did_id' => $did->id]);

        $service = app(OutboundOriginateService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The requested outbound gateway is not allowed for this extension.');

        $service->buildCommand($organization, $extension->fresh(), '+15557654321', gatewayId: $blockedGateway->id);
    }

    public function test_builds_internal_dialplan_originate_command_with_first_allowed_did_when_no_default_exists(): void
    {
        $organization = Organization::factory()->create(['domain' => 'acme.test']);
        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'extension' => '1001',
            'first_name' => 'John',
            'effective_caller_id_name' => 'John Doe',
        ]);
        $firstDid = Did::factory()->create([
            'organization_id' => $organization->id,
            'gateway_id' => null,
            'number' => '+15551234566',
            'normalized_number' => '+15551234566',
        ]);
        $secondDid = Did::factory()->create([
            'organization_id' => $organization->id,
            'gateway_id' => null,
            'number' => '+15551234567',
            'normalized_number' => '+15551234567',
        ]);
        $extension->allowedOutboundDids()->attach([$secondDid->id, $firstDid->id]);

        $service = app(OutboundOriginateService::class);
        $command = $service->buildCommand($organization, $extension->fresh(), '2001');

        $this->assertStringContainsString('origination_caller_id_name=John Doe', $command);
        $this->assertStringContainsString('origination_caller_id_number=+15551234566', $command);
        $this->assertStringContainsString('user/1001@acme.test 2001 XML acme.test', $command);
        $this->assertStringContainsString('origination_uuid=', $command);
    }

    public function test_rejects_originate_when_extension_has_no_allowed_outbound_did(): void
    {
        $organization = Organization::factory()->create(['domain' => 'acme.test']);
        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'extension' => '1001',
            'first_name' => 'John',
        ]);

        $service = app(OutboundOriginateService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This extension does not have an allowed outbound DID.');

        $service->buildCommand($organization, $extension->fresh(), '2001');
    }

    public function test_build_command_stages_outbound_recording_context_on_call_session(): void
    {
        $organization = Organization::factory()->create([
            'domain' => 'acme.test',
            'recording_policy' => 'incoming',
        ]);
        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'extension' => '1001',
            'first_name' => 'John',
            'effective_caller_id_name' => 'John Doe',
            'recording_policy' => 'outgoing',
        ]);
        $did = Did::factory()->create([
            'organization_id' => $organization->id,
            'gateway_id' => null,
            'number' => '+15551234567',
            'normalized_number' => '+15551234567',
            'recording_policy' => 'all',
        ]);
        $extension->allowedOutboundDids()->attach($did->id);
        $extension->update(['default_outbound_did_id' => $did->id]);

        $command = app(OutboundOriginateService::class)->buildCommand($organization, $extension->fresh(), '2001');

        $this->assertStringContainsString('origination_uuid=', $command);

        preg_match('/origination_uuid=([^,}]+)/', $command, $matches);
        $this->assertNotEmpty($matches[1] ?? null);

        $session = CallSession::query()->where('call_uuid', $matches[1])->first();

        $this->assertNotNull($session);
        $this->assertSame($organization->id, $session->organization_id);
        $this->assertSame($did->id, $session->did_id);
        $this->assertSame('outbound', data_get($session->variables, 'recording_context.direction'));
        $this->assertSame($organization->id, data_get($session->variables, 'recording_context.organization_id'));
        $this->assertSame('incoming', data_get($session->variables, 'recording_context.organization_policy'));
        $this->assertSame($did->id, data_get($session->variables, 'recording_context.did_id'));
        $this->assertSame('all', data_get($session->variables, 'recording_context.did_policy'));
        $this->assertSame($extension->id, data_get($session->variables, 'recording_context.owner_extension_id'));
        $this->assertSame('outgoing', data_get($session->variables, 'recording_context.extension_policy'));
        $this->assertNull(data_get($session->variables, 'recording_context.answered_target_type'));
    }
}
