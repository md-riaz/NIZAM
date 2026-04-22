<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookEventExpansionTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(Organization $organization): User
    {
        return User::factory()->create(['role' => 'admin', 'organization_id' => $organization->id]);
    }

    public function test_webhook_can_subscribe_to_extension_events(): void
    {
        $organization = Organization::factory()->create();
        $user = $this->adminUser($organization);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/organizations/{$organization->id}/webhooks", [
                'url' => 'https://example.com/webhook',
                'events' => ['extension.created', 'extension.updated', 'extension.deleted'],
            ]);

        $response->assertStatus(201);
    }

    public function test_webhook_can_subscribe_to_did_events(): void
    {
        $organization = Organization::factory()->create();
        $user = $this->adminUser($organization);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/organizations/{$organization->id}/webhooks", [
                'url' => 'https://example.com/webhook',
                'events' => ['did.created', 'did.updated', 'did.deleted'],
            ]);

        $response->assertStatus(201);
    }

    public function test_webhook_can_subscribe_to_recording_and_organization_events(): void
    {
        $organization = Organization::factory()->create();
        $user = $this->adminUser($organization);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/organizations/{$organization->id}/webhooks", [
                'url' => 'https://example.com/webhook',
                'events' => ['recording.created', 'organization.updated', 'call.bridged'],
            ]);

        $response->assertStatus(201);
    }

    public function test_webhook_rejects_invalid_event_types(): void
    {
        $organization = Organization::factory()->create();
        $user = $this->adminUser($organization);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/organizations/{$organization->id}/webhooks", [
                'url' => 'https://example.com/webhook',
                'events' => ['invalid.event'],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['events.0']);
    }

    public function test_extension_crud_dispatches_webhooks(): void
    {
        Queue::fake();

        $organization = Organization::factory()->create();
        $user = $this->adminUser($organization);

        // Create a webhook subscribed to extension events
        $organization->webhooks()->create([
            'url' => 'https://example.com/webhook',
            'events' => ['extension.created', 'extension.updated', 'extension.deleted'],
            'secret' => 'test-secret-1234567890',
            'is_active' => true,
        ]);

        // Create extension
        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/organizations/{$organization->id}/extensions", [
                'extension' => '1001',
                'password' => 'secret123456',
                'first_name' => 'Test',
                'last_name' => 'User',
            ]);

        $response->assertStatus(201);
        Queue::assertPushed(\App\Jobs\DeliverWebhook::class);
    }

    public function test_did_crud_dispatches_webhooks(): void
    {
        Queue::fake();

        $organization = Organization::factory()->create();
        $user = $this->adminUser($organization);

        $extension = $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);

        // Create a webhook subscribed to DID events
        $organization->webhooks()->create([
            'url' => 'https://example.com/webhook',
            'events' => ['did.created', 'did.updated', 'did.deleted'],
            'secret' => 'test-secret-1234567890',
            'is_active' => true,
        ]);

        // Create DID
        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/organizations/{$organization->id}/dids", [
                'number' => '+15551234567',
                'destination_type' => 'extension',
                'destination_id' => $extension->id,
            ]);

        $response->assertStatus(201);
        Queue::assertPushed(\App\Jobs\DeliverWebhook::class);
    }
}
