<?php

namespace Tests\Unit\Services;

use App\Jobs\DeliverWebhook;
use App\Models\Tenant;
use App\Models\Webhook;
use App\Services\WebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookDispatcherTest extends TestCase
{
    use RefreshDatabase;

    private WebhookDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = new WebhookDispatcher();
    }

    public function test_dispatches_jobs_for_matching_webhooks(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create();
        Webhook::factory()->create([
            'tenant_id' => $tenant->id,
            'events' => ['call.started', 'call.hangup'],
            'is_active' => true,
        ]);

        $this->dispatcher->dispatch($tenant->id, 'call.started', ['uuid' => 'test-123']);

        Queue::assertPushed(DeliverWebhook::class);
    }

    public function test_does_not_dispatch_for_inactive_webhooks(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create();
        Webhook::factory()->create([
            'tenant_id' => $tenant->id,
            'events' => ['call.started'],
            'is_active' => false,
        ]);

        $this->dispatcher->dispatch($tenant->id, 'call.started', ['uuid' => 'test-123']);

        Queue::assertNotPushed(DeliverWebhook::class);
    }

    public function test_does_not_dispatch_for_non_matching_event_types(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create();
        Webhook::factory()->create([
            'tenant_id' => $tenant->id,
            'events' => ['call.hangup'],
            'is_active' => true,
        ]);

        $this->dispatcher->dispatch($tenant->id, 'call.started', ['uuid' => 'test-123']);

        Queue::assertNotPushed(DeliverWebhook::class);
    }
}
