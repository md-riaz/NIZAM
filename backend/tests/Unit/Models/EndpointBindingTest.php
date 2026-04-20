<?php

namespace Tests\Unit\Models;

use App\Models\Agent;
use App\Models\EndpointBinding;
use App\Models\Extension;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndpointBindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoint_binding_casts_runtime_delivery_fields(): void
    {
        $binding = EndpointBinding::factory()->create([
            'is_push_capable' => true,
            'is_enabled' => false,
            'rings_immediately_when_online' => true,
            'allow_late_join_after_push' => true,
            'forward_requires_confirm' => false,
            'metadata' => ['push' => 'token'],
        ]);

        $binding->refresh();

        $this->assertTrue($binding->is_push_capable);
        $this->assertFalse($binding->is_enabled);
        $this->assertTrue($binding->rings_immediately_when_online);
        $this->assertTrue($binding->allow_late_join_after_push);
        $this->assertFalse($binding->forward_requires_confirm);
        $this->assertSame(['push' => 'token'], $binding->metadata);
    }

    public function test_endpoint_binding_relates_to_organization_extension_and_agent(): void
    {
        $organization = Organization::factory()->create();
        $extension = Extension::factory()->create(['organization_id' => $organization->id]);
        $agent = Agent::factory()->create([
            'organization_id' => $organization->id,
            'extension_id' => $extension->id,
        ]);

        $binding = EndpointBinding::factory()->create([
            'organization_id' => $organization->id,
            'extension_id' => $extension->id,
            'agent_id' => $agent->id,
        ]);

        $this->assertTrue($binding->organization->is($organization));
        $this->assertTrue($binding->extension->is($extension));
        $this->assertTrue($binding->agent->is($agent));
        $this->assertTrue($organization->endpointBindings()->first()->is($binding));
        $this->assertTrue($extension->endpointBindings()->first()->is($binding));
        $this->assertTrue($agent->endpointBindings()->first()->is($binding));
    }

    public function test_push_capable_endpoint_requires_token_material_for_runtime_validity(): void
    {
        $binding = EndpointBinding::factory()->create([
            'push_token' => null,
            'voip_push_token' => null,
            'metadata' => ['push_enabled' => true],
            'is_push_capable' => true,
        ]);

        $this->assertFalse($binding->hasPushTokenMaterial());
        $this->assertFalse($binding->isRuntimeConfigurationValid());
        $this->assertFalse($binding->isEligibleForOrchestration());
        $this->assertSame([
            'Push-capable endpoints require push token material.',
        ], $binding->runtimeConfigurationErrors());
    }

    public function test_pstn_forward_endpoint_requires_forward_number_for_runtime_validity(): void
    {
        $binding = EndpointBinding::factory()->pstnForward()->create([
            'forward_number' => null,
        ]);

        $this->assertFalse($binding->isRuntimeConfigurationValid());
        $this->assertFalse($binding->isEligibleForOrchestration());
        $this->assertContains('PSTN forward endpoints require a forward number.', $binding->runtimeConfigurationErrors());
    }

    public function test_disabled_endpoint_is_not_eligible_for_orchestration_even_when_runtime_configuration_is_valid(): void
    {
        $binding = EndpointBinding::factory()->create([
            'is_enabled' => false,
            'push_token' => 'push-token',
            'metadata' => ['push_enabled' => true],
        ]);

        $this->assertTrue($binding->isRuntimeConfigurationValid());
        $this->assertFalse($binding->isEligibleForOrchestration());
    }
}
