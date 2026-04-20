<?php

namespace Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WebhookEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_secret_is_encrypted_at_rest(): void
    {
        $organization = \App\Models\Organization::factory()->create();

        $webhook = $organization->webhooks()->create([
            'url' => 'https://example.com/webhook',
            'events' => ['call.created'],
            'secret' => 'my-secret-key-123',
            'is_active' => true,
        ]);

        // Model should return decrypted value
        $this->assertEquals('my-secret-key-123', $webhook->secret);

        // Raw DB value should NOT be plaintext
        $rawValue = DB::table('webhooks')
            ->where('id', $webhook->id)
            ->value('secret');
        $this->assertNotEquals('my-secret-key-123', $rawValue);
    }

    public function test_webhook_secret_is_hidden_from_serialization(): void
    {
        $organization = \App\Models\Organization::factory()->create();

        $webhook = $organization->webhooks()->create([
            'url' => 'https://example.com/webhook',
            'events' => ['call.created'],
            'secret' => 'hidden-secret',
            'is_active' => true,
        ]);

        $array = $webhook->toArray();
        $this->assertArrayNotHasKey('secret', $array);
    }
}
