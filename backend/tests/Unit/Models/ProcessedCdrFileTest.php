<?php

namespace Tests\Unit\Models;

use App\Models\ProcessedCdrFile;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessedCdrFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_processed_cdr_file_can_be_created_with_status_and_checksum(): void
    {
        $record = ProcessedCdrFile::create([
            'file_path' => 'xml_cdr/test-uuid.xml',
            'file_name' => 'test-uuid.xml',
            'checksum' => 'abc123',
            'dedupe_key' => ProcessedCdrFile::dedupeKeyFor('xml_cdr/test-uuid.xml', 'abc123'),
            'status' => ProcessedCdrFile::STATUS_PROCESSED,
        ]);

        $this->assertNotNull($record->id);
        $this->assertSame(ProcessedCdrFile::STATUS_PROCESSED, $record->status);
        $this->assertSame('abc123', $record->checksum);
    }

    public function test_dedupe_key_is_stable_for_path_normalization(): void
    {
        $linuxStyle = ProcessedCdrFile::dedupeKeyFor('xml_cdr/test-uuid.xml', 'abc123');
        $windowsStyle = ProcessedCdrFile::dedupeKeyFor('XML_CDR\\test-uuid.xml', 'abc123');

        $this->assertSame($linuxStyle, $windowsStyle);
    }

    public function test_dedupe_key_must_be_unique(): void
    {
        $dedupeKey = ProcessedCdrFile::dedupeKeyFor('xml_cdr/test-uuid.xml', 'abc123');

        ProcessedCdrFile::create([
            'file_path' => 'xml_cdr/test-uuid.xml',
            'file_name' => 'test-uuid.xml',
            'checksum' => 'abc123',
            'dedupe_key' => $dedupeKey,
            'status' => ProcessedCdrFile::STATUS_PROCESSED,
        ]);

        $this->expectException(QueryException::class);

        ProcessedCdrFile::create([
            'file_path' => 'xml_cdr/test-uuid-copy.xml',
            'file_name' => 'test-uuid-copy.xml',
            'checksum' => 'abc123',
            'dedupe_key' => $dedupeKey,
            'status' => ProcessedCdrFile::STATUS_PROCESSED,
        ]);
    }
}
