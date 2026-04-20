<?php

namespace Tests\Unit\Models;

use App\Models\Flow;
use App\Models\FlowVersion;
use App\Models\CallRoutingPolicy;
use App\Models\Organization;
use App\Models\Webhook;
use App\Models\WebhookDeliveryAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Spec3ModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_call_routing_policy_belongs_to_organization(): void
    {
        $organization = Organization::factory()->create();
        $policy = CallRoutingPolicy::factory()->create(['organization_id' => $organization->id]);

        $this->assertTrue($policy->organization->is($organization));
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

    public function test_flow_belongs_to_organization(): void
    {
        $organization = Organization::factory()->create();
        $flow = Flow::factory()->create(['organization_id' => $organization->id]);

        $this->assertTrue($flow->organization->is($organization));
    }

    public function test_flow_version_casts_definition_json_as_array(): void
    {
        $definition = [
            'nodes' => [
                ['id' => 'start', 'type' => 'play_prompt', 'data' => ['file' => 'welcome.wav'], 'next' => null],
            ]
        ];

        $flowVersion = FlowVersion::factory()->create(['definition_json' => $definition]);

        $this->assertIsArray($flowVersion->fresh()->definition_json);
        $this->assertEquals('play_prompt', $flowVersion->fresh()->definition_json['nodes'][0]['type']);
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

    public function test_organization_has_many_call_routing_policies(): void
    {
        $organization = Organization::factory()->create();
        CallRoutingPolicy::factory()->count(2)->create(['organization_id' => $organization->id]);

        $this->assertCount(2, $organization->callRoutingPolicies);
    }

    public function test_organization_has_many_flows(): void
    {
        $organization = Organization::factory()->create();
        Flow::factory()->count(2)->create(['organization_id' => $organization->id]);

        $this->assertCount(2, $organization->flows);
    }
}
