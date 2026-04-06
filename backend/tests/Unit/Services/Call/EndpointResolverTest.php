<?php

namespace Tests\Unit\Services\Call;

use App\Models\Agent;
use App\Models\EndpointBinding;
use App\Models\Extension;
use App\Models\Tenant;
use App\Services\Call\DeliveryTarget;
use App\Services\Call\DeliveryTargetSet;
use App\Services\Call\EndpointResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndpointResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);
    }

    public function test_extension_targets_expand_to_enabled_runtime_endpoint_candidates(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'acme.test']);
        $extension = Extension::factory()->create([
            'tenant_id' => $tenant->id,
            'extension' => '1001',
            'is_active' => true,
        ]);
        $agent = Agent::factory()->available()->create([
            'tenant_id' => $tenant->id,
            'extension_id' => $extension->id,
            'is_active' => true,
        ]);

        $deskPhone = EndpointBinding::factory()->forExtension($extension)->create([
            'type' => EndpointBinding::TYPE_DESK_PHONE,
            'is_push_capable' => false,
            'push_token' => null,
            'voip_push_token' => null,
            'allow_late_join_after_push' => false,
        ]);
        $mobile = EndpointBinding::factory()->forExtension($extension)->create([
            'type' => EndpointBinding::TYPE_MOBILE_APP,
            'allow_late_join_after_push' => true,
            'is_push_capable' => true,
            'push_token' => 'push-token',
        ]);
        $softphone = EndpointBinding::factory()->forAgent($agent)->create([
            'type' => EndpointBinding::TYPE_SOFTPHONE,
            'is_push_capable' => false,
            'push_token' => null,
            'voip_push_token' => null,
            'allow_late_join_after_push' => false,
        ]);
        EndpointBinding::factory()->forExtension($extension)->create([
            'type' => EndpointBinding::TYPE_PSTN_FORWARD,
            'is_enabled' => false,
            'forward_number' => '+15551234567',
        ]);

        $targetSet = new DeliveryTargetSet([
            new DeliveryTarget(
                type: 'extension',
                id: $extension->id,
                sourcePath: [
                    ['type' => 'ring_group', 'id' => 'rg-1', 'priority' => 7],
                ],
                metadata: ['priority' => 7],
            ),
        ]);

        $resolved = app(EndpointResolver::class)->resolve($targetSet);

        $this->assertCount(3, $resolved->candidates);
        $this->assertSame(
            [$deskPhone->id, $softphone->id, $mobile->id],
            array_map(fn ($candidate) => $candidate->endpointBindingId, $resolved->candidates)
        );
        $this->assertSame('extension', $resolved->candidates[0]->ownerType);
        $this->assertSame($extension->id, $resolved->candidates[0]->ownerId);
        $this->assertSame('sip:1001@acme.test', $resolved->candidates[0]->sipAor);
        $this->assertTrue($resolved->candidates[2]->pushCapable);
        $this->assertTrue($resolved->candidates[2]->allowLateJoinAfterPush);
        $this->assertSame(7, $resolved->candidates[1]->priority);
        $this->assertSame('ring_group', $resolved->candidates[1]->sourcePath[0]['type']);
    }

    public function test_agent_targets_include_agent_and_extension_bound_forward_candidates(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'queue.test']);
        $extension = Extension::factory()->create([
            'tenant_id' => $tenant->id,
            'extension' => '2002',
            'is_active' => true,
        ]);
        $agent = Agent::factory()->available()->create([
            'tenant_id' => $tenant->id,
            'extension_id' => $extension->id,
            'is_active' => true,
        ]);

        $agentEndpoint = EndpointBinding::factory()->forAgent($agent)->create([
            'type' => EndpointBinding::TYPE_AGENT_ENDPOINT,
            'is_push_capable' => false,
            'push_token' => null,
            'voip_push_token' => null,
        ]);
        $forward = EndpointBinding::factory()->forExtension($extension)->pstnForward()->create([
            'forward_number' => '+15557654321',
            'forward_requires_confirm' => true,
        ]);

        $targetSet = new DeliveryTargetSet([
            new DeliveryTarget(
                type: 'agent',
                id: $agent->id,
                sourcePath: [
                    ['type' => 'queue', 'id' => 'queue-1', 'queue_position' => 2],
                ],
            ),
        ]);

        $resolved = app(EndpointResolver::class)->resolve($targetSet);

        $this->assertCount(2, $resolved->candidates);
        $this->assertSame([$agentEndpoint->id, $forward->id], array_map(fn ($candidate) => $candidate->endpointBindingId, $resolved->candidates));
        $this->assertSame('agent', $resolved->candidates[0]->ownerType);
        $this->assertSame($agent->id, $resolved->candidates[0]->ownerId);
        $this->assertSame('sip:2002@queue.test', $resolved->candidates[0]->sipAor);
        $this->assertNull($resolved->candidates[1]->sipAor);
        $this->assertSame('+15557654321', $resolved->candidates[1]->forwardNumber);
        $this->assertTrue($resolved->candidates[1]->forwardRequiresConfirm);
        $this->assertSame(0, $resolved->candidates[1]->priority);
    }

    public function test_endpoint_candidates_do_not_use_device_profiles_as_runtime_source(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'devices.test']);
        $extension = Extension::factory()->create([
            'tenant_id' => $tenant->id,
            'extension' => '3003',
            'is_active' => true,
        ]);
        $extension->deviceProfiles()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Desk Provisioning',
            'vendor' => 'Yealink',
            'model' => 'T46U',
            'provisioning_method' => 'manual',
            'settings' => ['line' => 'ignored'],
            'is_active' => true,
        ]);

        $targetSet = new DeliveryTargetSet([
            new DeliveryTarget(type: 'extension', id: $extension->id),
        ]);

        $resolved = app(EndpointResolver::class)->resolve($targetSet);

        $this->assertTrue($resolved->isEmpty());
        $this->assertSame(0, $resolved->metadata['resolved_candidate_count']);
    }

    public function test_endpoint_candidates_exclude_runtime_invalid_push_bindings(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'invalid.test']);
        $extension = Extension::factory()->create([
            'tenant_id' => $tenant->id,
            'extension' => '4004',
            'is_active' => true,
        ]);

        EndpointBinding::factory()->forExtension($extension)->create([
            'type' => EndpointBinding::TYPE_MOBILE_APP,
            'is_push_capable' => true,
            'push_token' => null,
            'voip_push_token' => null,
            'is_enabled' => true,
        ]);
        $validDeskPhone = EndpointBinding::factory()->forExtension($extension)->create([
            'type' => EndpointBinding::TYPE_DESK_PHONE,
            'is_push_capable' => false,
            'push_token' => null,
            'voip_push_token' => null,
        ]);

        $targetSet = new DeliveryTargetSet([
            new DeliveryTarget(type: 'extension', id: $extension->id),
        ]);

        $resolved = app(EndpointResolver::class)->resolve($targetSet);

        $this->assertCount(1, $resolved->candidates);
        $this->assertSame($validDeskPhone->id, $resolved->candidates[0]->endpointBindingId);
        $this->assertSame(1, $resolved->metadata['resolved_candidate_count']);
    }
}
