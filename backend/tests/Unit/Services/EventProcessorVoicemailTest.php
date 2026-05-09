<?php

namespace Tests\Unit\Services;

use App\Models\Organization;
use App\Modules\ModuleRegistry;
use App\Services\EventProcessor;
use App\Services\WebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class EventProcessorVoicemailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);
    }

    public function test_processes_voicemail_received_through_module_hook_and_webhook(): void
    {
        $organization = Organization::factory()->create([
            'domain' => 'test.example.com',
            'is_active' => true,
        ]);

        $dispatcher = $this->createMock(WebhookDispatcher::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                $organization->id,
                'voicemail.received',
                $this->callback(function (array $payload): bool {
                    return ($payload['event_type'] ?? null) === 'voicemail.received'
                        && ($payload['metadata']['storage_disk'] ?? null) === 'local'
                        && ($payload['metadata']['storage_driver'] ?? null) === 'local-first'
                        && ($payload['metadata']['storage_reference'] ?? null) === ($payload['metadata']['storage_path'] ?? null);
                })
            );

        $registry = Mockery::mock(ModuleRegistry::class);
        $registry->shouldReceive('dispatchEvent')
            ->once()
            ->with(
                'voicemail.received',
                Mockery::on(function (array $payload) use ($organization): bool {
                    return ($payload['organization_id'] ?? null) === $organization->id
                        && ($payload['metadata']['user'] ?? null) === '1001'
                        && ($payload['metadata']['answered_target_type'] ?? null) === 'voicemail'
                        && ($payload['metadata']['voicemail_box'] ?? null) === '1001'
                        && ($payload['metadata']['voicemail_owner'] ?? null) === '1001';
                })
            );

        $processor = new EventProcessor($dispatcher, null, null, null, null, null, null, $registry);

        $processor->process([
            'Event-Name' => 'CUSTOM',
            'Event-Subclass' => 'vm::maintenance',
            'VM-Action' => 'leave-message',
            'VM-Domain' => 'test.example.com',
            'VM-User' => '1001',
            'VM-Caller-ID-Number' => '5551234567',
            'VM-Caller-ID-Name' => 'Voicemail Caller',
            'VM-Message-Len' => '37',
        ]);

        $this->assertDatabaseHas('call_events', [
            'organization_id' => $organization->id,
            'event_type' => 'voicemail.received',
            'call_uuid' => '1001',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
