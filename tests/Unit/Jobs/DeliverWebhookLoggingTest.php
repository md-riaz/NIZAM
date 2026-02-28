<?php

namespace Tests\Unit\Jobs;

use App\Jobs\DeliverWebhook;
use App\Models\Webhook;
use App\Models\WebhookDeliveryAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeliverWebhookLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_delivery_is_logged(): void
    {
        Http::fake([
            '*' => Http::response('OK', 200),
        ]);

        $webhook = Webhook::factory()->create();

        $job = new DeliverWebhook($webhook, 'call.started', ['call_uuid' => 'test-uuid']);
        $job->handle();

        $this->assertDatabaseHas('webhook_delivery_attempts', [
            'webhook_id' => $webhook->id,
            'event_type' => 'call.started',
            'response_status' => 200,
            'success' => true,
        ]);
    }

    public function test_failed_delivery_is_logged(): void
    {
        Http::fake([
            '*' => Http::response('Server Error', 500),
        ]);

        $webhook = Webhook::factory()->create();

        $job = new DeliverWebhook($webhook, 'call.hangup', ['call_uuid' => 'test-uuid']);
        $job->handle();

        $this->assertDatabaseHas('webhook_delivery_attempts', [
            'webhook_id' => $webhook->id,
            'event_type' => 'call.hangup',
            'response_status' => 500,
            'success' => false,
        ]);
    }

    public function test_connection_error_is_logged(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        $webhook = Webhook::factory()->create();

        $job = new DeliverWebhook($webhook, 'call.missed', ['call_uuid' => 'test-uuid']);

        try {
            $job->handle();
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Expected
        }

        $this->assertDatabaseHas('webhook_delivery_attempts', [
            'webhook_id' => $webhook->id,
            'event_type' => 'call.missed',
            'success' => false,
        ]);

        $attempt = WebhookDeliveryAttempt::first();
        $this->assertNotNull($attempt->error_message);
        $this->assertStringContainsString('Connection refused', $attempt->error_message);
    }
}
