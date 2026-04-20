<?php

namespace Tests\Unit\Services;

use App\Jobs\DeliverWebhook;
use App\Models\Organization;
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
        $this->dispatcher = new WebhookDispatcher;
    }

    public function test_dispatches_jobs_for_matching_webhooks(): void
    {
        Queue::fake();

        $organization = Organization::factory()->create();
        Webhook::factory()->create([
            'organization_id' => $organization->id,
            'events' => ['call.created', 'call.hangup'],
            'is_active' => true,
        ]);

        $this->dispatcher->dispatch($organization->id, 'call.created', ['uuid' => 'test-123']);

        Queue::assertPushed(DeliverWebhook::class);
    }

    public function test_does_not_dispatch_for_inactive_webhooks(): void
    {
        Queue::fake();

        $organization = Organization::factory()->create();
        Webhook::factory()->create([
            'organization_id' => $organization->id,
            'events' => ['call.created'],
            'is_active' => false,
        ]);

        $this->dispatcher->dispatch($organization->id, 'call.created', ['uuid' => 'test-123']);

        Queue::assertNotPushed(DeliverWebhook::class);
    }

    public function test_does_not_dispatch_for_non_matching_event_types(): void
    {
        Queue::fake();

        $organization = Organization::factory()->create();
        Webhook::factory()->create([
            'organization_id' => $organization->id,
            'events' => ['call.hangup'],
            'is_active' => true,
        ]);

        $this->dispatcher->dispatch($organization->id, 'call.created', ['uuid' => 'test-123']);

        Queue::assertNotPushed(DeliverWebhook::class);
    }
}
