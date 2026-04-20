<?php

namespace Tests\Unit\Modules;

use App\Models\CallEventLog;
use App\Modules\Voicemail\VoicemailEventService;
use App\Modules\Voicemail\VoicemailModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoicemailModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_voicemail_module_manifest_and_hooks_are_declared(): void
    {
        $module = new VoicemailModule;

        $this->assertSame('voicemail', $module->name());
        $this->assertSame('1.0.0', $module->version());
        $this->assertContains(CallEventLog::EVENT_VOICEMAIL_RECEIVED, $module->subscribedEvents());
        $this->assertSame('local', $module->config()['storage_disk']);
        $this->assertSame('local-first', $module->config()['storage_strategy']);
    }

    public function test_voicemail_event_service_builds_local_first_payload(): void
    {
        $organization = \App\Models\Organization::factory()->create([
            'domain' => 'vm.example.com',
            'is_active' => true,
        ]);

        $service = new VoicemailEventService;

        $payload = $service->handleMaintenanceEvent([
            'VM-Action' => 'leave-message',
            'VM-Domain' => 'vm.example.com',
            'VM-User' => '1001',
            'VM-Caller-ID-Number' => '5551000',
            'VM-Caller-ID-Name' => 'Caller',
            'VM-Message-Len' => '42',
        ]);

        $this->assertNotNull($payload);
        $this->assertSame($organization->id, $payload['organization_id']);
        $this->assertSame(CallEventLog::EVENT_VOICEMAIL_RECEIVED, $payload['event_type']);
        $this->assertSame('local', $payload['metadata']['storage_disk']);
        $this->assertSame('local-first', $payload['metadata']['storage_driver']);
        $this->assertSame('voicemail/vm.example.com/1001', $payload['metadata']['storage_path']);
        $this->assertSame($payload['metadata']['storage_path'], $payload['metadata']['storage_reference']);
    }

    public function test_voicemail_event_service_canonicalizes_windows_and_absolute_paths(): void
    {
        $organization = \App\Models\Organization::factory()->create([
            'domain' => 'vm.example.com',
            'is_active' => true,
        ]);

        $service = new VoicemailEventService;

        $payload = $service->handleMaintenanceEvent([
            'VM-Action' => 'leave-message',
            'VM-Domain' => $organization->domain,
            'VM-User' => '1001',
            'VM-Message-File' => 'C:\\freeswitch\\storage\\voicemail\\vm.example.com\\1001\\msg.wav',
        ]);

        $this->assertNotNull($payload);
        $this->assertSame('freeswitch/storage/voicemail/vm.example.com/1001/msg.wav', $payload['metadata']['storage_path']);
        $this->assertSame($payload['metadata']['storage_path'], $payload['metadata']['storage_reference']);
        $this->assertSame('C:\\freeswitch\\storage\\voicemail\\vm.example.com\\1001\\msg.wav', $payload['metadata']['raw_storage_path']);
        $this->assertSame('freeswitch/storage/voicemail/vm.example.com/1001/msg.wav', $payload['metadata']['message_file']);
        $this->assertSame('C:\\freeswitch\\storage\\voicemail\\vm.example.com\\1001\\msg.wav', $payload['metadata']['raw_message_file']);
    }
}
