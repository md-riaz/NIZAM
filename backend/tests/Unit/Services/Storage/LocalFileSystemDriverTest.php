<?php

namespace Tests\Unit\Services\Storage;

use App\Services\Storage\LocalFileSystemDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LocalFileSystemDriverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_archives_a_local_recording_and_returns_metadata(): void
    {
        Storage::fake('recordings');

        $source = storage_path('framework/testing/local-driver-source.wav');
        @mkdir(dirname($source), 0777, true);
        file_put_contents($source, 'sample-audio');

        $driver = new LocalFileSystemDriver(Storage::disk('recordings'));

        $metadata = $driver->archive($source, 'archive/recordings/tenant-a/2026/04/12/call-123.wav');

        $this->assertSame('archive/recordings/tenant-a/2026/04/12/call-123.wav', $metadata['path']);
        $this->assertSame(strlen('sample-audio'), $metadata['size']);
        $this->assertFalse(is_file($source));
        Storage::disk('recordings')->assertExists('archive/recordings/tenant-a/2026/04/12/call-123.wav');
    }
}
