<?php

namespace App\Jobs;

use App\Models\Webhook;
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
        $body = json_encode([
            'event' => $this->eventType,
            'timestamp' => now()->toIso8601String(),
            'data' => $this->payload,
        ]);

        $signature = hash_hmac('sha256', $body, $this->webhook->secret);

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Nizam-Signature' => $signature,
                    'X-Nizam-Event' => $this->eventType,
                ])
                ->withBody($body, 'application/json')
                ->post($this->webhook->url);

            if ($response->failed()) {
                Log::warning('Webhook delivery failed', [
                    'webhook_id' => $this->webhook->id,
                    'url' => $this->webhook->url,
                    'event' => $this->eventType,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Webhook connection error', [
                'webhook_id' => $this->webhook->id,
                'url' => $this->webhook->url,
                'event' => $this->eventType,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
