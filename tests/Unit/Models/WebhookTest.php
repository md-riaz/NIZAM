<?php

namespace Tests\Unit\Models;

use App\Models\Tenant;
use App\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_created_with_valid_attributes(): void
    {
        $tenant = Tenant::factory()->create();

        $webhook = Webhook::factory()->create([
            'tenant_id' => $tenant->id,
            'url' => 'https://example.com/webhook',
            'events' => ['call.started', 'call.hangup'],
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('webhooks', [
            'url' => 'https://example.com/webhook',
            'tenant_id' => $tenant->id,
        ]);
        $this->assertNotNull($webhook->id);
    }

    public function test_belongs_to_a_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $webhook = Webhook::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertInstanceOf(Tenant::class, $webhook->tenant);
        $this->assertEquals($tenant->id, $webhook->tenant->id);
    }

    public function test_secret_field_is_hidden(): void
    {
        $webhook = Webhook::factory()->create(['secret' => 'supersecretvalue']);

        $array = $webhook->toArray();
        $this->assertArrayNotHasKey('secret', $array);
    }

    public function test_events_is_cast_to_array(): void
    {
        $webhook = Webhook::factory()->create([
            'events' => ['call.started', 'call.hangup'],
        ]);

        $webhook->refresh();
        $this->assertIsArray($webhook->events);
        $this->assertEquals(['call.started', 'call.hangup'], $webhook->events);
    }
}
