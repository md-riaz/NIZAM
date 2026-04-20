<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDeliveryAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookDeliveryStatsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role' => 'admin',
        ]);
    }

    public function test_can_get_delivery_stats(): void
    {
        $webhook = Webhook::factory()->create(['organization_id' => $this->organization->id]);

        WebhookDeliveryAttempt::factory()->count(3)->create([
            'webhook_id' => $webhook->id,
            'success' => true,
            'latency_ms' => 100,
        ]);

        WebhookDeliveryAttempt::factory()->count(2)->create([
            'webhook_id' => $webhook->id,
            'success' => false,
            'error_message' => 'Connection timeout',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/webhooks/{$webhook->id}/delivery-stats");

        $response->assertStatus(200);
        $response->assertJsonPath('data.total_attempts', 5);
        $response->assertJsonPath('data.successful', 3);
        $response->assertJsonPath('data.failed', 2);
        $this->assertEquals(60, $response->json('data.success_rate'));
        $this->assertEquals(100, $response->json('data.avg_latency_ms'));
    }

    public function test_delivery_stats_returns_zero_for_no_attempts(): void
    {
        $webhook = Webhook::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/webhooks/{$webhook->id}/delivery-stats");

        $response->assertStatus(200);
        $response->assertJsonPath('data.total_attempts', 0);
        $response->assertJsonPath('data.success_rate', 0);
    }

    public function test_delivery_stats_enforces_organization_ownership(): void
    {
        $otherOrganization = Organization::factory()->create();
        $webhook = Webhook::factory()->create(['organization_id' => $otherOrganization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/webhooks/{$webhook->id}/delivery-stats");

        $response->assertStatus(404);
    }
}
