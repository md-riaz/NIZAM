<?php

namespace Tests\Feature\Cdr;

use App\Models\CallDetailRecord;
use App\Models\Organization;
use App\Models\ProcessedCdrFile;
use App\Services\Cdr\XmlCdrDiscoveryService;
use App\Services\Cdr\XmlCdrIngestionService;
use App\Services\Cdr\XmlCdrSpool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The spool is the only durable copy of a call detail record.
 *
 * FreeSWITCH writes each record once and never offers it again, so whatever is
 * on disk is all there is. Everything asserted here is a way that guarantee used
 * to be broken: a failure was permanent, a partial write was read as a whole
 * record, a malformed file was re-read on every pass forever, and a backlog was
 * processed in a single unbounded pass.
 */
class XmlCdrSpoolDurabilityTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('app/testing/xml_cdr_spool');
        File::deleteDirectory($this->directory);
        File::ensureDirectoryExists($this->directory);

        config()->set('telephony.xml_cdr.directory', $this->directory);
        config()->set('telephony.xml_cdr.cleanup_after_ingest', true);
        config()->set('telephony.xml_cdr.max_attempts', 3);
        // Retries are spaced out in production; these cases are about what
        // happens across attempts, not when they happen.
        config()->set('telephony.xml_cdr.retry_delay_seconds', 0);
        // The stability check costs real wall-clock per file; a single
        // microsecond still exercises the two-read comparison.
        config()->set('telephony.xml_cdr.stability_microseconds', 1);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    /**
     * A record that cannot be stored yet must stay in the spool.
     *
     * The ledger used to be consulted by key alone, with no regard for status, so
     * one failure removed the record from consideration permanently while its
     * file stayed on disk forever. A database that was briefly unreachable, or an
     * organization created an hour later, cost that call outright.
     */
    public function test_a_failed_record_is_attempted_again(): void
    {
        $path = $this->spoolRecord('retry-me', 'not-yet.example.com');

        $this->attemptOnce();

        $this->assertTrue(File::exists($path), 'The record was removed before it was stored.');
        $this->assertSame(1, ProcessedCdrFile::query()->value('attempts'));
        $this->assertSame([$path], app(XmlCdrDiscoveryService::class)->pendingFiles());
    }

    /**
     * And must be stored once the reason it failed goes away.
     */
    public function test_a_retried_record_is_stored_once_its_organization_exists(): void
    {
        $path = $this->spoolRecord('late-org', 'late.example.com');

        $this->attemptOnce();
        $this->assertDatabaseMissing('call_detail_records', ['uuid' => 'late-org']);

        Organization::factory()->create(['domain' => 'late.example.com']);

        $this->attemptOnce();

        $this->assertDatabaseHas('call_detail_records', ['uuid' => 'late-org']);
        $this->assertFalse(File::exists($path), 'A stored record should leave the spool.');
    }

    /**
     * Retrying cannot be unbounded, or one bad record re-reads forever.
     */
    public function test_a_record_is_quarantined_once_it_runs_out_of_attempts(): void
    {
        $path = $this->spoolRecord('never-works', 'missing.example.com');

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->attemptOnce();
        }

        $this->assertFalse(File::exists($path), 'An exhausted record should leave the working directory.');
        $this->assertEmpty(app(XmlCdrDiscoveryService::class)->pendingFiles());

        $record = ProcessedCdrFile::query()->firstOrFail();
        $this->assertSame(ProcessedCdrFile::STATUS_QUARANTINED, $record->status);
        $this->assertSame(XmlCdrSpool::REASON_SQL, $record->quarantine_reason);

        // Quarantine moves the evidence aside, it does not destroy it.
        $this->assertTrue(File::exists($record->quarantine_path), 'The quarantined record was not kept.');
    }

    /**
     * A record still being written must not be read as a finished one.
     *
     * mod_xml_cdr creates the file and then writes it. The watcher used to ask
     * inotify for IN_CREATE, which fires between those two steps.
     */
    public function test_a_record_that_is_still_growing_is_left_for_the_next_pass(): void
    {
        $path = $this->directory.'/growing.xml';
        File::put($path, '<cdr><variables><uuid>partial');

        // Stands in for a writer that appends between the two size reads, which
        // is the window a real reader has to survive.
        $growing = new class($this->directory) extends XmlCdrSpool
        {
            private int $reads = 0;

            protected function currentSize(string $path): int|false
            {
                return 100 + (++$this->reads * 10);
            }
        };

        $this->assertFalse($growing->isSettled($path, 1), 'A file that changed size was treated as settled.');
        $this->assertTrue(
            (new XmlCdrSpool($this->directory))->isSettled($path, 1),
            'A file that stopped changing was not treated as settled.'
        );
    }

    /**
     * And discovery must not offer it for reading while it is still growing.
     */
    public function test_a_growing_record_is_not_offered_for_reading(): void
    {
        $this->spoolRecord('still-writing', 'writing.example.com');

        $unsettled = new class($this->directory) extends XmlCdrSpool
        {
            public function isSettled(string $path, int $microseconds = 10000): bool
            {
                return false;
            }
        };

        $discovery = new XmlCdrDiscoveryService($this->directory, $unsettled);

        $this->assertEmpty($discovery->pendingFiles(), 'A record still being written was offered for reading.');
    }

    /**
     * An empty or implausibly large record is moved aside without being read.
     */
    public function test_zero_byte_and_oversized_records_are_quarantined_unread(): void
    {
        $empty = $this->directory.'/empty.xml';
        $huge = $this->directory.'/huge.xml';

        File::put($empty, '');
        File::put($huge, str_repeat('x', 1024));
        config()->set('telephony.xml_cdr.max_bytes', 512);

        $pending = app(XmlCdrDiscoveryService::class)->pendingFiles();

        $this->assertEmpty($pending, 'A record that cannot be valid was still offered for reading.');
        $this->assertFalse(File::exists($empty));
        $this->assertFalse(File::exists($huge));

        $quarantine = (new XmlCdrSpool($this->directory))->quarantineDirectory(XmlCdrSpool::REASON_SIZE);
        $this->assertCount(2, File::files($quarantine));
    }

    /**
     * The quarantine tree is not rescanned, or quarantining would achieve nothing.
     */
    public function test_quarantined_records_are_not_discovered_again(): void
    {
        $spool = new XmlCdrSpool($this->directory);
        $path = $this->spoolRecord('set-aside', 'gone.example.com');

        $spool->quarantine($path, XmlCdrSpool::REASON_XML);

        $this->assertEmpty(app(XmlCdrDiscoveryService::class)->pendingFiles());
    }

    /**
     * One pass is bounded so a backlog cannot block the loop for minutes.
     */
    public function test_a_pass_reads_at_most_one_batch(): void
    {
        config()->set('telephony.xml_cdr.batch_limit', 3);

        foreach (range(1, 10) as $index) {
            $this->spoolRecord('batched-'.$index, 'batch.example.com');
        }

        $this->assertCount(3, app(XmlCdrDiscoveryService::class)->pendingFiles());
    }

    /**
     * A record that parsed and stored is the only one that leaves the spool.
     */
    public function test_a_stored_record_is_removed_and_not_offered_again(): void
    {
        Organization::factory()->create(['domain' => 'stored.example.com']);
        $path = $this->spoolRecord('stored-once', 'stored.example.com');

        $this->attemptOnce();

        $this->assertFalse(File::exists($path));
        $this->assertEmpty(app(XmlCdrDiscoveryService::class)->pendingFiles());
        $this->assertSame(1, CallDetailRecord::query()->where('uuid', 'stored-once')->count());
    }

    /**
     * Unparseable input is quarantined apart from storage failures, because the
     * two are requeued under different circumstances.
     */
    public function test_unparseable_input_is_quarantined_separately_from_storage_failures(): void
    {
        $path = $this->directory.'/broken.xml';
        File::put($path, '<cdr><variables><uuid>truncated');

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->attemptOnce();
        }

        $record = ProcessedCdrFile::query()->firstOrFail();

        $this->assertSame(XmlCdrSpool::REASON_XML, $record->quarantine_reason);
        $this->assertStringContainsString('/failed/xml/', (string) $record->quarantine_path);
    }

    /**
     * A record just attempted waits before it is tried again.
     *
     * Without a delay the drain loop would spend all three attempts in the same
     * few milliseconds, which is not a retry: whatever caused the failure has had
     * no chance to change. It would also keep a failing record in every batch,
     * hiding whatever is queued behind it.
     */
    public function test_a_just_failed_record_waits_before_being_retried(): void
    {
        config()->set('telephony.xml_cdr.retry_delay_seconds', 60);

        $this->spoolRecord('slow-down', 'absent.example.com');

        $this->attemptOnce();

        $this->assertEmpty(
            app(XmlCdrDiscoveryService::class)->pendingFiles(),
            'A record was retried immediately after failing.'
        );

        $this->travel(61)->seconds();

        $this->assertCount(1, app(XmlCdrDiscoveryService::class)->pendingFiles());
    }

    /**
     * Run one discovery-and-ingest pass the way the command does.
     */
    private function attemptOnce(): void
    {
        $ingestion = app(XmlCdrIngestionService::class);

        foreach (app(XmlCdrDiscoveryService::class)->pendingFiles() as $path) {
            try {
                $ingestion->ingest($path);
            } catch (\Throwable $exception) {
                $ingestion->markFailed($path, $exception);
            }
        }
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
