<?php

namespace Tests\Unit\Services\Cdr;

use App\Models\ProcessedCdrFile;
use App\Services\Cdr\XmlCdrDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class XmlCdrDiscoveryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/testing/xml_cdr_discovery'));

        parent::tearDown();
    }

    public function test_it_lists_pending_xml_cdr_files_from_directory(): void
    {
        $directory = storage_path('app/testing/xml_cdr_discovery');
        File::ensureDirectoryExists($directory);
        File::put($directory.'/b.xml', '<cdr />');
        File::put($directory.'/a.xml', '<cdr />');
        File::put($directory.'/ignore.txt', 'not xml');

        $service = new XmlCdrDiscoveryService($directory);

        $files = $service->pendingFiles();

        $this->assertCount(2, $files);
        $this->assertSame(['a.xml', 'b.xml'], array_map('basename', $files));
    }

    public function test_it_skips_already_processed_files_using_dedupe_key(): void
    {
        $directory = storage_path('app/testing/xml_cdr_discovery');
        File::ensureDirectoryExists($directory);

        $processedPath = $directory.'/a.xml';
        $pendingPath = $directory.'/b.xml';

        File::put($processedPath, '<cdr><variables><uuid>a</uuid></variables></cdr>');
        File::put($pendingPath, '<cdr><variables><uuid>b</uuid></variables></cdr>');

        $checksum = hash_file('sha256', $processedPath);

        ProcessedCdrFile::create([
            'file_path' => $processedPath,
            'file_name' => 'a.xml',
            'checksum' => $checksum,
            'dedupe_key' => ProcessedCdrFile::dedupeKeyFor($processedPath, $checksum),
            'status' => ProcessedCdrFile::STATUS_PROCESSED,
        ]);

        $service = new XmlCdrDiscoveryService($directory);

        $files = $service->pendingFiles();

        $this->assertSame(['b.xml'], array_map('basename', $files));
    }
}
