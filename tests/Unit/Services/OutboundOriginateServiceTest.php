<?php

namespace Tests\Unit\Services;

use App\Models\Extension;
use App\Models\Gateway;
use App\Models\Tenant;
use App\Services\Call\OutboundOriginateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutboundOriginateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_internal_dialplan_originate_command_without_gateway(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'acme.test']);
        $extension = Extension::factory()->create([
            'tenant_id' => $tenant->id,
            'extension' => '1001',
            'directory_first_name' => 'John',
            'effective_caller_id_name' => 'John Doe',
            'effective_caller_id_number' => '8801555123456',
        ]);

        $service = new OutboundOriginateService;
        $command = $service->buildCommand($tenant, $extension, '2001');

        $this->assertSame(
            'originate {origination_caller_id_name=John Doe,origination_caller_id_number=8801555123456}user/1001@acme.test 2001 XML acme.test',
            $command
        );
    }

    public function test_builds_gateway_bridge_originate_command_when_gateway_is_provided(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'acme.test']);
        $extension = Extension::factory()->create([
            'tenant_id' => $tenant->id,
            'extension' => '1001',
            'directory_first_name' => 'John',
            'effective_caller_id_name' => 'John Doe',
            'effective_caller_id_number' => '8801555123456',
        ]);
        $gateway = Gateway::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $service = new OutboundOriginateService;
        $command = $service->buildCommand($tenant, $extension, '+15551234567', gateway: $gateway);

        $this->assertSame(
            sprintf(
                'originate {origination_caller_id_name=John Doe,origination_caller_id_number=8801555123456}user/1001@acme.test &bridge(sofia/gateway/v_%s/+15551234567)',
                $gateway->id,
            ),
            $command
        );
    }
}
