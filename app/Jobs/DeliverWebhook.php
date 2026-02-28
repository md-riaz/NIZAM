<?php

namespace App\Jobs;

use App\Models\Webhook;
use App\Models\WebhookDeliveryAttempt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public Webhook $webhook,
        public string $eventType,
        public array $payload,
    ) {}

    public function handle(): void
    {
        $timestamp = now()->getTimestamp();

        $body = json_encode([
            'event' => $this->eventType,
            'timestamp' => now()->toIso8601String(),
            'data' => $this->payload,
        ]);

        $signaturePayload = $timestamp.'.'.$body;
        $signature = hash_hmac('sha256', $signaturePayload, $this->webhook->secret);

        try {
            $startTime = microtime(true);

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Nizam-Signature' => $signature,
                    'X-Nizam-Timestamp' => (string) $timestamp,
                    'X-Nizam-Event' => $this->eventType,
                ])
                ->withBody($body, 'application/json')
                ->post($this->webhook->url);

            $latencyMs = round((microtime(true) - $startTime) * 1000, 2);

            WebhookDeliveryAttempt::create([
                'webhook_id' => $this->webhook->id,
                'event_type' => $this->eventType,
                'payload' => $this->payload,
                'response_status' => $response->status(),
                'response_body' => substr((string) $response->body(), 0, 1000),
                'attempt' => $this->attempts(),
                'success' => $response->successful(),
                'latency_ms' => $latencyMs,
                'delivered_at' => now(),
            ]);

            if ($response->failed()) {
                Log::warning('Webhook delivery failed', [
                    'webhook_id' => $this->webhook->id,
                    'url' => $this->webhook->url,
                    'event' => $this->eventType,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            WebhookDeliveryAttempt::create([
                'webhook_id' => $this->webhook->id,
                'event_type' => $this->eventType,
                'payload' => $this->payload,
                'attempt' => $this->attempts(),
                'success' => false,
                'error_message' => $e->getMessage(),
                'delivered_at' => now(),
            ]);

            Log::error('Webhook connection error', [
                'webhook_id' => $this->webhook->id,
                'url' => $this->webhook->url,
                'event' => $this->eventType,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure after all retries exhausted (dead-letter).
     */
    public function failed(?\Throwable $exception): void
    {
        WebhookDeliveryAttempt::create([
            'webhook_id' => $this->webhook->id,
            'event_type' => $this->eventType,
            'payload' => $this->payload,
            'attempt' => $this->attempts(),
            'success' => false,
            'error_message' => 'Dead letter: '.(($exception) ? $exception->getMessage() : 'Max retries exhausted'),
            'delivered_at' => now(),
        ]);

        Log::error('Webhook delivery dead-lettered', [
            'webhook_id' => $this->webhook->id,
            'url' => $this->webhook->url,
            'event' => $this->eventType,
            'error' => $exception?->getMessage(),
        ]);
    }
}
