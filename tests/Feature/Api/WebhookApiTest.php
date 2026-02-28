<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookApiTest extends TestCase
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

    public function test_can_list_webhooks_for_a_tenant(): void
    {
        Webhook::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/webhooks");

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_a_webhook(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/tenants/{$this->tenant->id}/webhooks", [
                'url' => 'https://example.com/webhook',
                'events' => ['call.started', 'call.hangup'],
                'description' => 'Test webhook',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('secret', fn ($val) => strlen($val) >= 16);
        $this->assertDatabaseHas('webhooks', [
            'tenant_id' => $this->tenant->id,
            'url' => 'https://example.com/webhook',
        ]);
    }

    public function test_can_show_a_webhook(): void
    {
        $webhook = Webhook::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/webhooks/{$webhook->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['url' => $webhook->url]);
    }

    public function test_can_update_a_webhook(): void
    {
        $webhook = Webhook::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/tenants/{$this->tenant->id}/webhooks/{$webhook->id}", [
                'url' => 'https://updated.example.com/hook',
                'events' => ['call.answered'],
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('webhooks', [
            'id' => $webhook->id,
            'url' => 'https://updated.example.com/hook',
        ]);
    }

    public function test_can_delete_a_webhook(): void
    {
        $webhook = Webhook::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/tenants/{$this->tenant->id}/webhooks/{$webhook->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('webhooks', ['id' => $webhook->id]);
    }

    public function test_validates_required_fields_on_create(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/tenants/{$this->tenant->id}/webhooks", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['url', 'events']);
    }
}
