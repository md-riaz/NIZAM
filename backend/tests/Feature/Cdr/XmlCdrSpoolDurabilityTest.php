<?php

namespace Tests\Feature\Cdr;

use App\Events\CallDetailRecordCreated;
use App\Models\CallDetailRecord;
use App\Models\Organization;
use App\Models\ProcessedCdrFile;
use App\Services\Cdr\XmlCdrDiscoveryService;
use App\Services\Cdr\XmlCdrIngestionService;
use App\Services\Cdr\XmlCdrSpool;
use App\Services\EventProcessor;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
     * inotify for IN_CREATE, which fires between those two steps. The batch's
     * sizes are taken before the settling pause and compared after it, so a
     * record that grew across the pause is held back for the next pass.
     */
    public function test_a_record_that_is_still_growing_is_left_for_the_next_pass(): void
    {
        $this->spoolRecord('still-writing', 'writing.example.com');

        // Stands in for mod_xml_cdr continuing to write during the pause.
        $discovery = new class($this->directory) extends XmlCdrDiscoveryService
        {
            protected function pause(): void
            {
                foreach (File::files($this->directory) as $file) {
                    File::append($file->getPathname(), '<!-- still writing -->');
                }
            }
        };

        $this->assertEmpty($discovery->pendingFiles(), 'A record still being written was offered for reading.');

        // And once the writer stops, the very next pass picks it up.
        $this->assertCount(1, app(XmlCdrDiscoveryService::class)->pendingFiles());
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

        // A record only counts as empty once it has been empty for a while.
        touch($empty, now()->subMinutes(5)->getTimestamp());

        $pending = app(XmlCdrDiscoveryService::class)->pendingFiles();

        $this->assertEmpty($pending, 'A record that cannot be valid was still offered for reading.');
        $this->assertFalse(File::exists($empty));
        $this->assertFalse(File::exists($huge));

        $quarantine = (new XmlCdrSpool($this->directory))->quarantineDirectory(XmlCdrSpool::REASON_SIZE);
        $this->assertCount(2, File::files($quarantine));
    }

    /**
     * A record that is empty only because it has just been created stays put.
     *
     * mod_xml_cdr creates the file and then writes it, so zero bytes is a normal
     * intermediate state. Quarantining on sight moved the file out from under
     * FreeSWITCH's open descriptor: it kept writing to the moved inode, and the
     * finished record landed in a directory the scan deliberately never reads.
     */
    public function test_a_freshly_created_empty_record_is_not_quarantined(): void
    {
        $justCreated = $this->directory.'/a_being-written.xml';
        File::put($justCreated, '');

        $this->assertEmpty(app(XmlCdrDiscoveryService::class)->pendingFiles());
        $this->assertTrue(File::exists($justCreated), 'A record still being written was quarantined.');

        // Once FreeSWITCH finishes the write, the next pass picks it up.
        File::put($justCreated, '<cdr><variables><uuid>being-written</uuid></variables></cdr>');

        $this->assertCount(1, app(XmlCdrDiscoveryService::class)->pendingFiles());
    }

    /**
     * A record is only marked terminal once it has genuinely left the spool.
     *
     * Recording the status after a failed move would strand it twice over: still
     * in the working directory where nothing reads it, and marked never to be
     * tried again.
     */
    public function test_a_record_stays_retryable_when_the_quarantine_move_fails(): void
    {
        $path = $this->spoolRecord('immovable', 'nowhere.example.com');

        $immovable = new class($this->directory) extends XmlCdrSpool
        {
            public function quarantine(string $path, string $reason): ?string
            {
                return null;
            }
        };

        $ingestion = new XmlCdrIngestionService(null, $immovable);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $ingestion->ingest($path);
            } catch (\Throwable $exception) {
                $ingestion->markFailed($path, $exception);
            }
        }

        $record = ProcessedCdrFile::query()->firstOrFail();

        $this->assertSame(ProcessedCdrFile::STATUS_FAILED, $record->status, 'A record was marked terminal without leaving the spool.');
        $this->assertNull($record->quarantine_path);
        $this->assertTrue(File::exists($path));
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
     * And it costs the same one query however many records are in the batch.
     *
     * This runs on every filesystem event, so asking the ledger once per file
     * would make a busy spool quadratic in database round trips.
     */
    public function test_a_pass_consults_the_ledger_once_for_the_whole_batch(): void
    {
        foreach (range(1, 20) as $index) {
            $this->spoolRecord('counted-'.$index, 'counted.example.com');
        }

        $queries = 0;
        DB::listen(function (QueryExecuted $query) use (&$queries) {
            if (str_contains($query->sql, 'processed_cdr_files')) {
                $queries++;
            }
        });

        $pending = app(XmlCdrDiscoveryService::class)->pendingFiles();

        $this->assertCount(20, $pending);
        $this->assertSame(1, $queries, 'The ledger was consulted per file instead of per batch.');
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
     * Quarantining the same name twice must not destroy the first copy.
     *
     * The collision name was a timestamp with one-second resolution, so a burst
     * of failures chose the same destination — and `rename()` replaces silently,
     * which here means destroying the evidence the quarantine exists to keep.
     */
    public function test_a_second_quarantine_of_the_same_name_keeps_both(): void
    {
        $spool = new XmlCdrSpool($this->directory);

        foreach (['first', 'second', 'third'] as $contents) {
            File::put($this->directory.'/a_clash.xml', $contents);
            $this->assertNotNull($spool->quarantine($this->directory.'/a_clash.xml', XmlCdrSpool::REASON_XML));
        }

        $kept = collect(File::files($spool->quarantineDirectory(XmlCdrSpool::REASON_XML)))
            ->map(fn ($file) => File::get($file->getPathname()))
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['first', 'second', 'third'], $kept, 'A quarantined record was overwritten by a later one.');
    }

    /**
     * A no-replace move refuses rather than clobbering live work.
     */
    public function test_a_move_will_not_replace_an_existing_file(): void
    {
        $spool = new XmlCdrSpool($this->directory);

        File::put($this->directory.'/source.xml', 'incoming');
        File::put($this->directory.'/occupied.xml', 'already here');

        $this->assertFalse($spool->moveWithoutReplacing($this->directory.'/source.xml', $this->directory.'/occupied.xml'));
        $this->assertSame('already here', File::get($this->directory.'/occupied.xml'));
        $this->assertTrue(File::exists($this->directory.'/source.xml'), 'The source was consumed by a refused move.');
    }

    /**
     * Both writers can look before either inserts, and the loser must not lose
     * what it knew.
     *
     * The live path carries RTP quality and SIP detail the spool never sees. An
     * insert that lost the race used to surface as an error and be logged away,
     * discarding all of it.
     */
    public function test_a_record_written_by_both_paths_keeps_both_contributions(): void
    {
        $organization = Organization::factory()->create(['domain' => 'merge.example.com']);

        CallDetailRecord::factory()->create([
            'organization_id' => $organization->id,
            'uuid' => 'merged-call',
            'mos_score' => 4.4,
            'sip_user_agent' => 'Polycom',
        ]);

        $path = $this->spoolRecord('merged-call', 'merge.example.com');
        $this->attemptOnce();

        $cdr = CallDetailRecord::query()->where('uuid', 'merged-call')->firstOrFail();

        $this->assertSame(1, CallDetailRecord::query()->where('uuid', 'merged-call')->count());
        $this->assertEquals(4.4, (float) $cdr->mos_score, 'The live path\'s quality metrics were lost.');
        $this->assertSame('Polycom', $cdr->sip_user_agent);
        $this->assertSame(60, $cdr->billsec, 'The spooled record did not complete the row.');
        $this->assertFalse(File::exists($path));
    }

    /**
     * The live path must not erase what the spool already knew.
     *
     * Both writers reach the same row and each has fields the other does not.
     * An absent variable on one side means it has nothing to say about that
     * field, not that the field should be emptied — and for the recording path
     * that distinction is the difference between an archived recording and an
     * orphaned file, because the archiver finds the audio by that path.
     */
    public function test_the_live_path_does_not_erase_a_recording_path_the_spool_stored(): void
    {
        $organization = Organization::factory()->create([
            'domain' => 'keep.example.com',
            'is_active' => true,
            'status' => Organization::STATUS_ACTIVE,
        ]);

        CallDetailRecord::factory()->create([
            'organization_id' => $organization->id,
            'uuid' => 'keep-the-path',
            'recording_path' => '/recordings/keep-the-path.wav',
            'sip_user_agent' => 'Polycom',
        ]);

        // A hangup event that carries no recording variables at all.
        (new EventProcessor(
            $this->createMock(\App\Services\WebhookDispatcher::class),
            new \App\Services\UsageMeteringService,
        ))->process([
            'Event-Name' => 'CHANNEL_HANGUP_COMPLETE',
            'variable_domain_name' => 'keep.example.com',
            'Unique-ID' => 'keep-the-path',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Hangup-Cause' => 'NORMAL_CLEARING',
            'variable_billsec' => '42',
            'Call-Direction' => 'inbound',
        ]);

        $cdr = CallDetailRecord::query()->where('uuid', 'keep-the-path')->firstOrFail();

        $this->assertSame('/recordings/keep-the-path.wav', $cdr->recording_path, 'The recording path was erased.');
        $this->assertSame('Polycom', $cdr->sip_user_agent);
        $this->assertSame(42, $cdr->billsec, 'The live path did not update what it did know.');
    }

    /**
     * A record retried after a crash still announces itself.
     *
     * The event is what queues enrichment and archival. It used to fire only
     * when the row was new, so a record that crashed between being saved and
     * being published was, on retry, marked processed with nothing queued for
     * it — no enrichment, and no archived recording.
     */
    public function test_a_retried_record_still_announces_itself(): void
    {
        Organization::factory()->create(['domain' => 'announce.example.com']);
        $path = $this->spoolRecord('announce-me', 'announce.example.com');

        // First attempt: the record is stored, then publication fails. The
        // listener has to be real for the failure to propagate — a faked
        // dispatcher records the event instead of delivering it.
        $ingestion = app(XmlCdrIngestionService::class);

        Event::listen(CallDetailRecordCreated::class, function () {
            throw new \RuntimeException('queue unavailable');
        });

        try {
            $ingestion->ingest($path);
            $this->fail('Publication was expected to fail on the first attempt.');
        } catch (\Throwable $exception) {
            $ingestion->markFailed($path, $exception);
        }

        Event::forget(CallDetailRecordCreated::class);

        $this->assertDatabaseHas('call_detail_records', ['uuid' => 'announce-me']);
        $this->assertTrue(File::exists($path), 'The record was acknowledged before it was announced.');

        // Second attempt: the row already exists, so the old condition would
        // have skipped the announcement entirely.
        Event::fake([CallDetailRecordCreated::class]);

        $ingestion->ingest($path);

        Event::assertDispatched(CallDetailRecordCreated::class);
        $this->assertFalse(File::exists($path));
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
