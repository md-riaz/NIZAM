<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDeliveryAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookDeliveryAttemptApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_can_list_delivery_attempts_for_a_webhook(): void
    {
        $webhook = Webhook::factory()->create(['tenant_id' => $this->tenant->id]);
        WebhookDeliveryAttempt::factory()->count(3)->create(['webhook_id' => $webhook->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/webhooks/{$webhook->id}/delivery-attempts");

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_delivery_attempts_include_expected_fields(): void
    {
        $webhook = Webhook::factory()->create(['tenant_id' => $this->tenant->id]);
        WebhookDeliveryAttempt::factory()->create([
            'webhook_id' => $webhook->id,
            'event_type' => 'call.started',
            'response_status' => 200,
            'success' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/webhooks/{$webhook->id}/delivery-attempts");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'event_type' => 'call.started',
            'response_status' => 200,
            'success' => true,
        ]);
    }

    public function test_cannot_view_delivery_attempts_of_other_tenants_webhook(): void
    {
        $otherTenant = Tenant::factory()->create();
        $webhook = Webhook::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/webhooks/{$webhook->id}/delivery-attempts");

        $response->assertStatus(404);
    }
}
