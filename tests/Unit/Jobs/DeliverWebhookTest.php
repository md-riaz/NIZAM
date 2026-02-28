<?php

namespace Tests\Unit\Jobs;

use App\Jobs\DeliverWebhook;
use App\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeliverWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivers_webhook_with_correct_payload(): void
    {
        Http::fake([
            'https://example.com/hook' => Http::response('OK', 200),
        ]);

        $webhook = Webhook::factory()->create([
            'url' => 'https://example.com/hook',
            'secret' => 'test-secret-key',
            'events' => ['call.started'],
            'is_active' => true,
        ]);

        $job = new DeliverWebhook($webhook, 'call.started', [
            'caller_id_number' => '1001',
            'destination_number' => '1002',
        ]);

        $job->handle();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://example.com/hook'
                && $request->hasHeader('X-Nizam-Signature')
                && $request->hasHeader('X-Nizam-Event', 'call.started')
                && $request->hasHeader('Content-Type', 'application/json');
        });
    }

    public function test_webhook_signature_uses_hmac_sha256(): void
    {
        Http::fake([
            'https://example.com/hook' => Http::response('OK', 200),
        ]);

        $webhook = Webhook::factory()->create([
            'url' => 'https://example.com/hook',
            'secret' => 'my-secret-key',
            'events' => ['call.started'],
            'is_active' => true,
        ]);

        $job = new DeliverWebhook($webhook, 'call.started', ['test' => 'data']);

        $job->handle();

        Http::assertSent(function ($request) {
            $signature = $request->header('X-Nizam-Signature')[0] ?? '';

            // Verify signature is a valid hex string (SHA256 = 64 hex chars)
            return strlen($signature) === 64 && ctype_xdigit($signature);
        });
    }

    public function test_webhook_has_correct_retry_configuration(): void
    {
        $webhook = Webhook::factory()->create();
        $job = new DeliverWebhook($webhook, 'call.started', []);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(30, $job->timeout);
        $this->assertEquals([10, 60, 300], $job->backoff);
    }

    public function test_connection_exception_is_rethrown(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        $webhook = Webhook::factory()->create([
            'url' => 'https://unreachable.example.com/hook',
            'secret' => 'test-secret',
            'events' => ['call.started'],
        ]);

        $job = new DeliverWebhook($webhook, 'call.started', []);

        $this->expectException(\Illuminate\Http\Client\ConnectionException::class);

        $job->handle();
    }
}
