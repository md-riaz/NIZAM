<?php

namespace Tests\Unit\Models;

use App\Models\Recording;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordingEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_can_store_queue_metadata(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Corp',
            'domain' => 'test.example.com',
            'slug' => 'test-corp',
            'max_extensions' => 50,
        ]);

        $recording = Recording::create([
            'tenant_id' => $tenant->id,
            'call_uuid' => 'test-uuid-123',
            'file_path' => '/recordings/test.wav',
            'file_name' => 'test.wav',
            'file_size' => 1024,
            'format' => 'wav',
            'duration' => 60,
            'direction' => 'inbound',
            'caller_id_number' => '+15551234567',
            'destination_number' => '1001',
            'queue_name' => 'Support Queue',
            'agent_id' => 'agent-uuid-123',
            'wait_time' => 15,
            'outcome' => 'answered',
            'abandon_reason' => null,
        ]);

        $this->assertDatabaseHas('recordings', [
            'id' => $recording->id,
            'queue_name' => 'Support Queue',
            'agent_id' => 'agent-uuid-123',
            'wait_time' => 15,
            'outcome' => 'answered',
        ]);

        $recording->refresh();
        $this->assertEquals('Support Queue', $recording->queue_name);
        $this->assertEquals(15, $recording->wait_time);
    }

    public function test_recording_queue_metadata_nullable(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Corp',
            'domain' => 'test.example.com',
            'slug' => 'test-corp',
            'max_extensions' => 50,
        ]);

        $recording = Recording::create([
            'tenant_id' => $tenant->id,
            'call_uuid' => 'test-uuid-456',
            'file_path' => '/recordings/test2.wav',
            'file_name' => 'test2.wav',
            'file_size' => 2048,
            'format' => 'wav',
            'duration' => 30,
            'direction' => 'outbound',
            'caller_id_number' => '+15551234567',
            'destination_number' => '1002',
        ]);

        $recording->refresh();
        $this->assertNull($recording->queue_name);
        $this->assertNull($recording->agent_id);
        $this->assertNull($recording->wait_time);
        $this->assertNull($recording->outcome);
        $this->assertNull($recording->abandon_reason);
    }

    public function test_recording_with_abandon_metadata(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Corp',
            'domain' => 'test.example.com',
            'slug' => 'test-corp',
            'max_extensions' => 50,
        ]);

        $recording = Recording::create([
            'tenant_id' => $tenant->id,
            'call_uuid' => 'test-uuid-789',
            'file_path' => '/recordings/test3.wav',
            'file_name' => 'test3.wav',
            'file_size' => 512,
            'format' => 'wav',
            'duration' => 5,
            'direction' => 'inbound',
            'caller_id_number' => '+15559876543',
            'destination_number' => '1001',
            'queue_name' => 'Sales Queue',
            'wait_time' => 120,
            'outcome' => 'abandoned',
            'abandon_reason' => 'caller_hangup',
        ]);

        $recording->refresh();
        $this->assertEquals('abandoned', $recording->outcome);
        $this->assertEquals('caller_hangup', $recording->abandon_reason);
        $this->assertEquals(120, $recording->wait_time);
    }
}
