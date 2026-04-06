<?php

namespace Tests\Unit\Models;

use App\Models\CallDetailRecord;
use App\Models\Recording;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CdrRecordingRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_cdr_has_many_recordings(): void
    {
        $tenant = Tenant::factory()->create();
        $cdr = CallDetailRecord::factory()->create([
            'tenant_id' => $tenant->id,
            'uuid' => 'test-call-uuid-123',
        ]);

        Recording::factory()->create([
            'tenant_id' => $tenant->id,
            'call_uuid' => 'test-call-uuid-123',
        ]);
        Recording::factory()->create([
            'tenant_id' => $tenant->id,
            'call_uuid' => 'test-call-uuid-123',
        ]);

        $this->assertCount(2, $cdr->recordings);
        $this->assertInstanceOf(Recording::class, $cdr->recordings->first());
    }

    public function test_recording_belongs_to_cdr(): void
    {
        $tenant = Tenant::factory()->create();
        $cdr = CallDetailRecord::factory()->create([
            'tenant_id' => $tenant->id,
            'uuid' => 'cdr-uuid-456',
        ]);

        $recording = Recording::factory()->create([
            'tenant_id' => $tenant->id,
            'call_uuid' => 'cdr-uuid-456',
        ]);

        $this->assertInstanceOf(CallDetailRecord::class, $recording->cdr);
        $this->assertEquals($cdr->id, $recording->cdr->id);
    }

    public function test_cdr_without_recordings_returns_empty_collection(): void
    {
        $tenant = Tenant::factory()->create();
        $cdr = CallDetailRecord::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertCount(0, $cdr->recordings);
    }

    public function test_recording_without_cdr_returns_null(): void
    {
        $tenant = Tenant::factory()->create();
        $recording = Recording::factory()->create([
            'tenant_id' => $tenant->id,
            'call_uuid' => 'orphan-uuid',
        ]);

        $this->assertNull($recording->cdr);
    }
}
