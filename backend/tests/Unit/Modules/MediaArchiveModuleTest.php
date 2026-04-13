<?php

namespace Tests\Unit\Modules;

use App\Models\CallDetailRecord;
use App\Models\Recording;
use App\Models\Tenant;
use App\Modules\Media\MediaArchiveModule;
use App\Services\Storage\LocalFileSystemDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaArchiveModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_archives_recording_on_call_end_and_updates_cdr_metadata(): void
    {
        Storage::fake('recordings');

        $tenant = Tenant::factory()->create();
        $cdr = CallDetailRecord::factory()->create([
            'tenant_id' => $tenant->id,
            'uuid' => 'call-archive-123',
            'recording_path' => storage_path('framework/testing/media-archive/call-archive-123.wav'),
            'metadata' => ['source' => 'test'],
        ]);

        @mkdir(dirname($cdr->recording_path), 0777, true);
        file_put_contents($cdr->recording_path, 'archived-audio');

        $module = new MediaArchiveModule(new LocalFileSystemDriver(Storage::disk('recordings')));
        $module->register();

        $recording = $module->archiveFromCallEnd([
            'tenant_id' => $tenant->id,
            'call_uuid' => $cdr->uuid,
            'recording_path' => $cdr->recording_path,
            'direction' => 'inbound',
            'caller_id_number' => '1001',
            'destination_number' => '1002',
            'duration' => 42,
            'hangup_cause' => 'NORMAL_CLEARING',
            'ended_at' => '2026-04-12T10:15:00+00:00',
        ]);

        $this->assertInstanceOf(Recording::class, $recording);
        $this->assertSame('local', $recording->storage_driver);
        $this->assertNotNull($recording->archived_at);
        $this->assertSame($recording->file_path, $recording->storage_reference);
        $this->assertStringStartsWith('archive/recordings/', $recording->storage_reference);
        Storage::disk('recordings')->assertExists($recording->file_path);
        $this->assertFalse(is_file($cdr->recording_path));

        $cdr->refresh();
        $this->assertSame($recording->file_path, $cdr->recording_path);
        $this->assertSame('local', $cdr->metadata['recording_archive']['storage_driver']);
        $this->assertSame($recording->file_path, $cdr->metadata['recording_archive']['storage_path']);
        $this->assertSame($recording->file_path, $cdr->metadata['recording_archive']['storage_reference']);
    }

    public function test_it_canonicalizes_metadata_paths_when_archiving(): void
    {
        Storage::fake('recordings');

        $tenant = Tenant::factory()->create();
        $sourcePath = storage_path('framework/testing/media-archive/windows-path.wav');
        @mkdir(dirname($sourcePath), 0777, true);
        file_put_contents($sourcePath, 'archived-audio');

        $module = new MediaArchiveModule(new LocalFileSystemDriver(Storage::disk('recordings')));
        $module->register();

        $recording = $module->archiveFromCallEnd([
            'tenant_id' => $tenant->id,
            'call_uuid' => 'call-archive-paths',
            'recording_path' => $sourcePath,
            'ended_at' => '2026-04-12T10:15:00+00:00',
            'metadata' => [
                'storage_path' => 'C:\\freeswitch\\recordings\\call-archive-paths.wav',
                'storage_reference' => 'C:\\freeswitch\\recordings\\call-archive-paths.wav',
            ],
        ]);

        $this->assertInstanceOf(Recording::class, $recording);
        $this->assertSame($recording->file_path, $recording->archive_metadata['storage_path']);
        $this->assertSame($recording->file_path, $recording->archive_metadata['storage_reference']);
        $this->assertSame('archive/recordings/'.$tenant->id.'/'.now()->format('Y/m/d').'/call-archive-paths.wav', $recording->storage_reference);
    }

    public function test_it_returns_null_when_source_file_is_missing(): void
    {
        Storage::fake('recordings');

        $tenant = Tenant::factory()->create();
        $module = new MediaArchiveModule(new LocalFileSystemDriver(Storage::disk('recordings')));
        $module->register();

        $recording = $module->archiveFromCallEnd([
            'tenant_id' => $tenant->id,
            'call_uuid' => 'missing-call',
            'recording_path' => storage_path('framework/testing/media-archive/missing.wav'),
        ]);

        $this->assertNull($recording);
        $this->assertDatabaseCount('recordings', 0);
    }
}
