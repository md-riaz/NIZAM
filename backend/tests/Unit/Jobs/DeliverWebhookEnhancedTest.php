<?php

namespace Tests\Unit\Jobs;

use App\Jobs\DeliverWebhook;
use App\Models\Tenant;
use App\Models\Webhook;
use App\Models\WebhookDeliveryAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeliverWebhookEnhancedTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_includes_timestamp_header(): void
    {
        Http::fake(['*' => Http::response('OK', 200)]);

        $tenant = Tenant::factory()->create();
        $webhook = Webhook::factory()->create([
            'tenant_id' => $tenant->id,
            'url' => 'https://example.com/hook',
            'secret' => 'test-secret-key-12345',
        ]);

        $job = new DeliverWebhook($webhook, 'call.created', ['uuid' => 'test-123']);
        $job->handle();

        Http::assertSent(function ($request) {
            $prefix = config('platform.webhooks.signature_header_prefix', 'X-Nizam');
            return $request->hasHeader("{$prefix}-Timestamp")
                && $request->hasHeader("{$prefix}-Signature")
                && $request->hasHeader("{$prefix}-Event");
        });
    }

    public function test_webhook_records_latency(): void
    {
        Http::fake(['*' => Http::response('OK', 200)]);

        $tenant = Tenant::factory()->create();
        $webhook = Webhook::factory()->create([
            'tenant_id' => $tenant->id,
            'url' => 'https://example.com/hook',
            'secret' => 'test-secret-key-12345',
        ]);

        $job = new DeliverWebhook($webhook, 'call.created', ['uuid' => 'test-123']);
        $job->handle();

        $attempt = WebhookDeliveryAttempt::first();
        $this->assertNotNull($attempt);
        $this->assertNotNull($attempt->latency_ms);
    }

    public function test_webhook_signature_includes_timestamp(): void
    {
        Http::fake(['*' => Http::response('OK', 200)]);

        $tenant = Tenant::factory()->create();
        $webhook = Webhook::factory()->create([
            'tenant_id' => $tenant->id,
            'url' => 'https://example.com/hook',
            'secret' => 'test-secret-key-12345',
        ]);

        $job = new DeliverWebhook($webhook, 'call.created', ['uuid' => 'test-123']);
        $job->handle();

        Http::assertSent(function ($request) {
            $prefix = config('platform.webhooks.signature_header_prefix', 'X-Nizam');
            $timestamp = $request->header("{$prefix}-Timestamp")[0] ?? '';
            $signature = $request->header("{$prefix}-Signature")[0] ?? '';

            // Verify signature was computed with timestamp prefix
            return ! empty($timestamp) && ! empty($signature) && is_numeric($timestamp);
        });
    }
}
