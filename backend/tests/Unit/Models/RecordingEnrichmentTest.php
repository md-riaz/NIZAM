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

        $recording = Recording::factory()->create([
            'tenant_id' => $tenant->id,
            'call_uuid' => 'test-uuid-123',
            'queue_name' => 'Support Queue',
            'agent_id' => 'agent-uuid-123',
            'wait_time' => 15,
            'outcome' => 'answered',
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

        $recording = Recording::factory()->create([
            'tenant_id' => $tenant->id,
            'call_uuid' => 'test-uuid-456',
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

        $recording = Recording::factory()->create([
            'tenant_id' => $tenant->id,
            'call_uuid' => 'test-uuid-789',
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
