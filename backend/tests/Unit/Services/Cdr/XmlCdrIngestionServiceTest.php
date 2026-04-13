<?php

namespace Tests\Unit\Services\Cdr;

use App\Events\CallDetailRecordCreated;
use App\Models\CallDetailRecord;
use App\Models\ProcessedCdrFile;
use App\Models\Tenant;
use App\Services\Cdr\XmlCdrIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class XmlCdrIngestionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('app/testing/xml_cdr_ingestion');
        File::ensureDirectoryExists($this->directory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_it_ingests_xml_cdr_and_cleans_up_file_when_configured(): void
    {
        Event::fake([CallDetailRecordCreated::class]);

        $tenant = Tenant::factory()->create([
            'domain' => 'demo.example.com',
        ]);

        config()->set('telephony.xml_cdr.cleanup_after_ingest', true);

        $path = $this->directory.'/call-a.xml';
        File::put($path, $this->xmlFor([
            'uuid' => 'call-a',
            'domain_name' => $tenant->domain,
            'caller_id_name' => 'Alice',
            'caller_id_number' => '01710000000',
            'destination_number' => '1001',
            'direction' => 'inbound',
            'recording_file' => '/recordings/call-a.wav',
            'start_stamp' => '2026-04-12 10:00:00',
            'answer_stamp' => '2026-04-12 10:00:05',
            'end_stamp' => '2026-04-12 10:00:45',
            'billsec' => '40',
            'hangup_cause' => 'NORMAL_CLEARING',
        ]));

        $service = new XmlCdrIngestionService;
        $processed = $service->ingest($path);

        $cdr = CallDetailRecord::query()->where('uuid', 'call-a')->firstOrFail();

        $this->assertSame($tenant->id, $cdr->tenant_id);
        $this->assertSame('Alice', $cdr->caller_id_name);
        $this->assertSame('01710000000', $cdr->caller_id_number);
        $this->assertSame('1001', $cdr->destination_number);
        $this->assertSame('inbound', $cdr->direction);
        $this->assertSame('/recordings/call-a.wav', $cdr->recording_path);
        $this->assertSame(40, $cdr->billsec);
        $this->assertSame(45, $cdr->duration);

        $this->assertSame(ProcessedCdrFile::STATUS_PROCESSED, $processed->status);
        $this->assertSame('call-a', $processed->call_uuid);
        $this->assertFalse(File::exists($path));

        Event::assertDispatched(CallDetailRecordCreated::class, function (CallDetailRecordCreated $event) use ($cdr): bool {
            return $event->cdr->is($cdr);
        });
    }

    public function test_it_preserves_file_when_cleanup_is_disabled(): void
    {
        Event::fake([CallDetailRecordCreated::class]);

        $tenant = Tenant::factory()->create([
            'domain' => 'keep.example.com',
        ]);

        config()->set('telephony.xml_cdr.cleanup_after_ingest', false);

        $path = $this->directory.'/call-b.xml';
        File::put($path, $this->xmlFor([
            'uuid' => 'call-b',
            'domain_name' => $tenant->domain,
            'caller_id_number' => '01710000001',
            'destination_number' => '1002',
        ]));

        $service = new XmlCdrIngestionService;
        $service->ingest($path);

        $this->assertTrue(File::exists($path));
        $this->assertDatabaseHas('processed_cdr_files', [
            'file_name' => 'call-b.xml',
            'status' => ProcessedCdrFile::STATUS_PROCESSED,
        ]);
    }

    public function test_it_records_failed_ingestions(): void
    {
        $path = $this->directory.'/failed.xml';
        File::put($path, '<cdr />');

        $service = new XmlCdrIngestionService;
        $service->markFailed($path, new \RuntimeException('bad xml cdr'));

        $this->assertDatabaseHas('processed_cdr_files', [
            'file_name' => 'failed.xml',
            'status' => ProcessedCdrFile::STATUS_FAILED,
            'error_message' => 'bad xml cdr',
        ]);
    }

    protected function xmlFor(array $overrides): string
    {
        $defaults = [
            'uuid' => 'call-default',
            'domain_name' => 'demo.example.com',
            'caller_id_name' => '',
            'caller_id_number' => '01710000000',
            'destination_number' => '1000',
            'direction' => 'local',
            'recording_file' => '',
            'start_stamp' => '2026-04-12 10:00:00',
            'answer_stamp' => '',
            'end_stamp' => '2026-04-12 10:00:00',
            'billsec' => '0',
            'hangup_cause' => 'NORMAL_CLEARING',
        ];

        $values = array_merge($defaults, $overrides);

        return <<<XML
<?xml version="1.0"?>
<cdr>
  <variables>
    <uuid>{$values['uuid']}</uuid>
    <domain_name>{$values['domain_name']}</domain_name>
    <caller_id_name>{$values['caller_id_name']}</caller_id_name>
    <caller_id_number>{$values['caller_id_number']}</caller_id_number>
    <destination_number>{$values['destination_number']}</destination_number>
    <direction>{$values['direction']}</direction>
    <recording_file>{$values['recording_file']}</recording_file>
    <start_stamp>{$values['start_stamp']}</start_stamp>
    <answer_stamp>{$values['answer_stamp']}</answer_stamp>
    <end_stamp>{$values['end_stamp']}</end_stamp>
    <billsec>{$values['billsec']}</billsec>
    <hangup_cause>{$values['hangup_cause']}</hangup_cause>
  </variables>
</cdr>
XML;
    }
}
