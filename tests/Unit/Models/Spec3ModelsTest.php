<?php

namespace Tests\Unit\Models;

use App\Models\CallFlow;
use App\Models\CallRoutingPolicy;
use App\Models\Tenant;
use App\Models\Webhook;
use App\Models\WebhookDeliveryAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Spec3ModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_call_routing_policy_belongs_to_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $policy = CallRoutingPolicy::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertTrue($policy->tenant->is($tenant));
    }

    public function test_call_routing_policy_casts_conditions_as_array(): void
    {
        $conditions = [
            ['type' => 'time_of_day', 'params' => ['start' => '09:00', 'end' => '17:00']],
        ];

        $policy = CallRoutingPolicy::factory()->create(['conditions' => $conditions]);

        $this->assertIsArray($policy->fresh()->conditions);
        $this->assertEquals('time_of_day', $policy->fresh()->conditions[0]['type']);
    }

    public function test_call_flow_belongs_to_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $flow = CallFlow::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertTrue($flow->tenant->is($tenant));
    }

    public function test_call_flow_casts_nodes_as_array(): void
    {
        $nodes = [
            ['id' => 'start', 'type' => 'play_prompt', 'data' => ['file' => 'welcome.wav'], 'next' => null],
        ];

        $flow = CallFlow::factory()->create(['nodes' => $nodes]);

        $this->assertIsArray($flow->fresh()->nodes);
        $this->assertEquals('play_prompt', $flow->fresh()->nodes[0]['type']);
    }

    public function test_webhook_delivery_attempt_belongs_to_webhook(): void
    {
        $webhook = Webhook::factory()->create();
        $attempt = WebhookDeliveryAttempt::factory()->create(['webhook_id' => $webhook->id]);

        $this->assertTrue($attempt->webhook->is($webhook));
    }

    public function test_webhook_has_many_delivery_attempts(): void
    {
        $webhook = Webhook::factory()->create();
        WebhookDeliveryAttempt::factory()->count(3)->create(['webhook_id' => $webhook->id]);

        $this->assertCount(3, $webhook->deliveryAttempts);
    }

    public function test_tenant_has_many_call_routing_policies(): void
    {
        $tenant = Tenant::factory()->create();
        CallRoutingPolicy::factory()->count(2)->create(['tenant_id' => $tenant->id]);

        $this->assertCount(2, $tenant->callRoutingPolicies);
    }

    public function test_tenant_has_many_call_flows(): void
    {
        $tenant = Tenant::factory()->create();
        CallFlow::factory()->count(2)->create(['tenant_id' => $tenant->id]);

        $this->assertCount(2, $tenant->callFlows);
    }
}
