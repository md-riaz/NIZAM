<?php

namespace Tests\Unit\Models;

use App\Models\Organization;
use App\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_created_with_valid_attributes(): void
    {
        $organization = Organization::factory()->create();

        $webhook = Webhook::factory()->create([
            'organization_id' => $organization->id,
            'url' => 'https://example.com/webhook',
            'events' => ['call.created', 'call.hangup'],
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('webhooks', [
            'url' => 'https://example.com/webhook',
            'organization_id' => $organization->id,
        ]);
        $this->assertNotNull($webhook->id);
    }

    public function test_belongs_to_a_organization(): void
    {
        $organization = Organization::factory()->create();
        $webhook = Webhook::factory()->create(['organization_id' => $organization->id]);

        $this->assertInstanceOf(Organization::class, $webhook->organization);
        $this->assertEquals($organization->id, $webhook->organization->id);
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
            'events' => ['call.created', 'call.hangup'],
        ]);

        $webhook->refresh();
        $this->assertIsArray($webhook->events);
        $this->assertEquals(['call.created', 'call.hangup'], $webhook->events);
    }
}
