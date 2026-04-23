<?php

namespace Tests\Unit\Services;

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

        $this->assertSame(
            'originate {origination_caller_id_name=John Doe,origination_caller_id_number=+15551234567}user/1001@acme.test 2001 XML acme.test',
            $command
        );
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

        $this->assertSame(
            sprintf(
                'originate {origination_caller_id_name=John Doe,origination_caller_id_number=+15551234567}user/1001@acme.test &bridge(sofia/gateway/v_%s/+15557654321)',
                $gateway->id,
            ),
            $command
        );
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
}
