<?php

namespace Tests\Unit\Services;

use App\Events\CallDetailRecordCreated;
use App\Events\CallEvent;
use App\Listeners\ArchiveCallRecording;
use App\Models\CallDetailRecord;
use App\Models\Extension;
use App\Models\Recording;
use App\Models\Organization;
use App\Modules\Media\MediaArchiveModule;
use App\Modules\ModuleRegistry;
use App\Services\EventProcessor;
use App\Services\Storage\LocalFileSystemDriver;
use App\Services\WebhookDispatcher;
use Mockery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventProcessorTest extends TestCase
{
    use RefreshDatabase;

    private EventProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);

        $dispatcher = $this->createMock(WebhookDispatcher::class);
        $this->processor = new EventProcessor($dispatcher);
    }

    private function createOrganizationWithExtension(): array
    {
        $organization = Organization::factory()->create([
            'domain' => 'test.example.com',
            'is_active' => true,
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'extension' => '1001',
            'is_active' => true,
        ]);

        return [$organization, $extension];
    }

    public function test_processes_channel_hangup_complete_and_creates_cdr(): void
    {
        [$organization, $extension] = $this->createOrganizationWithExtension();
        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_HANGUP_COMPLETE',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'test-uuid-123',
            'Caller-Caller-ID-Name' => 'John Doe',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
            'Hangup-Cause' => 'NORMAL_CLEARING',
            'variable_duration' => '60',
            'variable_billsec' => '55',
            'variable_start_stamp' => '2024-01-01 10:00:00',
            'variable_answer_stamp' => '2024-01-01 10:00:05',
            'variable_end_stamp' => '2024-01-01 10:01:00',
            'Caller-Context' => 'default',
        ];

        $this->processor->process($event);

        $this->assertDatabaseHas('call_detail_records', [
            'uuid' => 'test-uuid-123',
            'organization_id' => $organization->id,
            'caller_id_number' => '1001',
            'destination_number' => '1002',
            'hangup_cause' => 'NORMAL_CLEARING',
        ]);
    }

    public function test_dispatches_call_event_on_channel_create(): void
    {
        [$organization, $extension] = $this->createOrganizationWithExtension();
        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_CREATE',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'test-uuid-456',
            'Caller-Caller-ID-Name' => 'Jane Doe',
            'Caller-Caller-ID-Number' => '1002',
            'Caller-Destination-Number' => '1001',
            'Call-Direction' => 'inbound',
        ];

        $this->processor->process($event);

        Event::assertDispatched(CallEvent::class, function (CallEvent $e) use ($organization) {
            return $e->organizationId === $organization->id && $e->eventType === 'started';
        });
    }

    public function test_dispatches_call_event_on_channel_answer(): void
    {
        [$organization, $extension] = $this->createOrganizationWithExtension();
        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_ANSWER',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'test-uuid-789',
            'Caller-Caller-ID-Name' => 'Jane Doe',
            'Caller-Caller-ID-Number' => '1002',
            'Caller-Destination-Number' => '1001',
            'Call-Direction' => 'inbound',
        ];

        $this->processor->process($event);

        Event::assertDispatched(CallEvent::class, function (CallEvent $e) use ($organization) {
            return $e->organizationId === $organization->id && $e->eventType === 'answered';
        });
    }

    public function test_call_end_archive_listener_archives_recording_from_created_cdr(): void
    {
        Storage::fake('recordings');

        $organization = Organization::factory()->create();
        $sourcePath = storage_path('framework/testing/archive-listener/call-end-archive.wav');
        @mkdir(dirname($sourcePath), 0777, true);
        file_put_contents($sourcePath, 'listener-audio');

        $cdr = CallDetailRecord::factory()->create([
            'organization_id' => $organization->id,
            'uuid' => 'call-end-archive',
            'recording_path' => $sourcePath,
            'direction' => 'inbound',
            'caller_id_number' => '1001',
            'destination_number' => '1002',
            'duration' => 33,
        ]);

        $registry = new ModuleRegistry;
        $module = new MediaArchiveModule(new LocalFileSystemDriver(Storage::disk('recordings')));
        $registry->register($module);

        $listener = new ArchiveCallRecording($registry);
        $listener->handle(new CallDetailRecordCreated($cdr));

        $this->assertDatabaseHas('recordings', [
            'organization_id' => $organization->id,
            'call_uuid' => 'call-end-archive',
            'storage_driver' => 'local',
        ]);

        $recording = Recording::query()->where('call_uuid', 'call-end-archive')->firstOrFail();
        Storage::disk('recordings')->assertExists($recording->file_path);
        $this->assertFalse(is_file($sourcePath));
    }

    public function test_dispatches_missed_call_webhook_on_no_answer(): void
    {
        [$organization, $extension] = $this->createOrganizationWithExtension();
        Event::fake([CallEvent::class]);

        $dispatcher = $this->createMock(WebhookDispatcher::class);
        $dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($organizationId, $eventType, $payload) use ($organization) {
                $this->assertEquals($organization->id, $organizationId);
                $this->assertContains($eventType, ['call.hangup', 'call.missed']);
            });

        $processor = new EventProcessor($dispatcher);

        $event = [
            'Event-Name' => 'CHANNEL_HANGUP_COMPLETE',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'test-uuid-missed',
            'Caller-Caller-ID-Name' => 'Missed Call',
            'Caller-Caller-ID-Number' => '5551234567',
            'Caller-Destination-Number' => '1001',
            'Call-Direction' => 'inbound',
            'Hangup-Cause' => 'NO_ANSWER',
            'variable_duration' => '30',
            'variable_billsec' => '0',
        ];

        $processor->process($event);
    }

    public function test_ignores_events_without_organization_domain(): void
    {
        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_CREATE',
            'Unique-ID' => 'test-uuid-unknown',
            'Caller-Caller-ID-Name' => 'Unknown',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
        ];

        $this->processor->process($event);

        Event::assertNotDispatched(CallEvent::class);
    }

    public function test_processes_voicemail_received_through_module_hook_and_webhook(): void
    {
        [$organization, $extension] = $this->createOrganizationWithExtension();

        $dispatcher = $this->createMock(WebhookDispatcher::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                $organization->id,
                'voicemail.received',
                $this->callback(function (array $payload): bool {
                    return ($payload['event_type'] ?? null) === 'voicemail.received'
                        && ($payload['metadata']['storage_disk'] ?? null) === 'local'
                        && ($payload['metadata']['storage_driver'] ?? null) === 'local-first';
                })
            );

        $registry = Mockery::mock(ModuleRegistry::class);
        $registry->shouldReceive('dispatchEvent')
            ->once()
            ->with(
                'voicemail.received',
                Mockery::on(function (array $payload) use ($organization): bool {
                    return ($payload['organization_id'] ?? null) === $organization->id
                        && ($payload['metadata']['user'] ?? null) === '1001';
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

    public function test_handles_unknown_event_types_gracefully(): void
    {
        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'SOME_UNKNOWN_EVENT',
            'variable_domain_name' => 'test.example.com',
        ];

        // Should not throw exception
        $this->processor->process($event);

        Event::assertNotDispatched(CallEvent::class);
    }
}
