<?php

namespace Tests\Unit\Models;

use App\Models\ProcessedCdrFile;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessedCdrFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_ledger_entry_records_what_happened_to_a_spooled_record(): void
    {
        $record = ProcessedCdrFile::create([
            'file_path' => 'xml_cdr/a_test-uuid.cdr.xml',
            'file_name' => 'a_test-uuid.cdr.xml',
            'status' => ProcessedCdrFile::STATUS_PROCESSED,
            'call_uuid' => 'test-uuid',
        ]);

        $this->assertNotNull($record->id);
        $this->assertSame(ProcessedCdrFile::STATUS_PROCESSED, $record->status);
        $this->assertSame(0, $record->attempts);
    }

    /**
     * The file name is the identity, so two entries cannot claim the same one.
     *
     * mod_xml_cdr names each record after the call it describes, which makes the
     * name unique without reading a byte of the file.
     */
    public function test_a_file_name_can_only_appear_once(): void
    {
        ProcessedCdrFile::create([
            'file_path' => 'xml_cdr/a_test-uuid.cdr.xml',
            'file_name' => 'a_test-uuid.cdr.xml',
            'status' => ProcessedCdrFile::STATUS_PROCESSED,
        ]);

        $this->expectException(QueryException::class);

        ProcessedCdrFile::create([
            'file_path' => 'somewhere/else/a_test-uuid.cdr.xml',
            'file_name' => 'a_test-uuid.cdr.xml',
            'status' => ProcessedCdrFile::STATUS_FAILED,
        ]);
    }

    /**
     * Only these two mean the record needs no further attention.
     */
    public function test_failed_is_not_a_settled_status(): void
    {
        $this->assertSame(
            [ProcessedCdrFile::STATUS_PROCESSED, ProcessedCdrFile::STATUS_QUARANTINED],
            ProcessedCdrFile::settledStatuses()
        );

        $this->assertNotContains(ProcessedCdrFile::STATUS_FAILED, ProcessedCdrFile::settledStatuses());
    }
}
