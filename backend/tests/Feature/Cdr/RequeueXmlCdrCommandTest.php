<?php

namespace Tests\Feature\Cdr;

use App\Models\Organization;
use App\Models\ProcessedCdrFile;
use App\Services\Cdr\XmlCdrSpool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Quarantine has to be reversible, or it is just a slower way to lose a record.
 *
 * The ingester gives up on a record after a bounded number of attempts so one
 * poison file cannot occupy a slot in every batch forever. But the reason a
 * record failed is usually fixable, and moving the file back by hand is not
 * enough on its own: the ledger still remembers the terminal status, and
 * discovery skips the file on sight.
 */
class RequeueXmlCdrCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('app/testing/xml_cdr_requeue');
        File::deleteDirectory($this->directory);
        File::ensureDirectoryExists($this->directory);

        config()->set('telephony.xml_cdr.directory', $this->directory);
        config()->set('telephony.xml_cdr.enabled', true);
        config()->set('telephony.xml_cdr.max_attempts', 1);
        config()->set('telephony.xml_cdr.retry_delay_seconds', 0);
        config()->set('telephony.xml_cdr.stability_microseconds', 1);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_a_requeued_record_is_ingested_once_its_organization_exists(): void
    {
        $path = $this->spoolRecord('second-chance', 'appears-later.example.com');

        // First run: the organization does not exist, so one attempt exhausts
        // the budget and the record is quarantined.
        $this->artisan('cdr:ingest-xml', ['--once' => true])->assertExitCode(0);

        $this->assertFalse(File::exists($path));
        $this->assertSame(ProcessedCdrFile::STATUS_QUARANTINED, ProcessedCdrFile::query()->value('status'));

        // The reason it failed is fixed.
        Organization::factory()->create(['domain' => 'appears-later.example.com']);

        $this->artisan('cdr:requeue-xml')->assertExitCode(0);

        $this->assertTrue(File::exists($path), 'The record was not returned to the spool.');
        $this->assertSame(0, ProcessedCdrFile::query()->count(), 'The terminal ledger entry outlived the requeue.');

        $this->artisan('cdr:ingest-xml', ['--once' => true])->assertExitCode(0);

        $this->assertDatabaseHas('call_detail_records', ['uuid' => 'second-chance']);
    }

    public function test_a_dry_run_moves_nothing(): void
    {
        $path = $this->spoolRecord('look-only', 'absent.example.com');

        $this->artisan('cdr:ingest-xml', ['--once' => true])->assertExitCode(0);
        $this->assertFalse(File::exists($path));

        $this->artisan('cdr:requeue-xml', ['--dry-run' => true])->assertExitCode(0);

        $this->assertFalse(File::exists($path), 'A dry run moved the record.');
        $this->assertSame(1, ProcessedCdrFile::query()->count(), 'A dry run cleared the ledger.');
    }

    /**
     * A record already back in the spool is being worked on; clobbering it would
     * lose whichever copy is further along.
     */
    public function test_it_refuses_to_overwrite_a_record_already_in_the_spool(): void
    {
        $path = $this->spoolRecord('collision', 'absent.example.com');

        $this->artisan('cdr:ingest-xml', ['--once' => true])->assertExitCode(0);

        File::put($path, 'a newer copy');

        $this->artisan('cdr:requeue-xml')->assertExitCode(0);

        $this->assertSame('a newer copy', File::get($path));
    }

    public function test_it_can_requeue_one_reason_at_a_time(): void
    {
        $spool = new XmlCdrSpool($this->directory);
        $spool->ensureQuarantineDirectories();

        File::put($spool->quarantineDirectory(XmlCdrSpool::REASON_XML).'/a_broken.xml', '<cdr>');
        File::put($spool->quarantineDirectory(XmlCdrSpool::REASON_SQL).'/a_unstored.xml', '<cdr/>');

        $this->artisan('cdr:requeue-xml', ['--reason' => 'xml'])->assertExitCode(0);

        $this->assertTrue(File::exists($this->directory.'/a_broken.xml'));
        $this->assertFalse(File::exists($this->directory.'/a_unstored.xml'));
    }

    public function test_an_unknown_reason_is_rejected(): void
    {
        $this->artisan('cdr:requeue-xml', ['--reason' => 'nonsense'])->assertExitCode(1);
    }

    private function spoolRecord(string $uuid, string $domain): string
    {
        $path = $this->directory.'/a_'.$uuid.'.xml';

        File::put($path, <<<XML
        <?xml version="1.0"?>
        <cdr>
          <variables>
            <uuid>{$uuid}</uuid>
            <domain_name>{$domain}</domain_name>
            <caller_id_number>1001</caller_id_number>
            <destination_number>1002</destination_number>
            <start_stamp>2026-09-18 10:00:00</start_stamp>
            <end_stamp>2026-09-18 10:01:00</end_stamp>
            <billsec>60</billsec>
            <hangup_cause>NORMAL_CLEARING</hangup_cause>
          </variables>
        </cdr>
        XML);

        return $path;
    }
}
