<?php

namespace Tests\Unit\Console;

use App\Events\CallDetailRecordCreated;
use App\Models\CallDetailRecord;
use App\Models\ProcessedCdrFile;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class IngestXmlCdrCommandTest extends TestCase
{
    use RefreshDatabase;

    protected string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('app/testing/xml_cdr_command');
        File::ensureDirectoryExists($this->directory);
        config()->set('telephony.xml_cdr.enabled', true);
        config()->set('telephony.xml_cdr.directory', $this->directory);
        config()->set('telephony.xml_cdr.cleanup_after_ingest', false);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_once_option_uses_single_tier_pipeline_to_ingest_pending_files(): void
    {
        Event::fake([CallDetailRecordCreated::class]);

        $tenant = Tenant::factory()->create([
            'domain' => 'command.example.com',
        ]);

        File::put($this->directory.'/command.xml', $this->xmlFor([
            'uuid' => 'command-call',
            'domain_name' => $tenant->domain,
        ]));

        $this->artisan('cdr:ingest-xml', ['--once' => true])
            ->assertExitCode(0);

        $this->assertDatabaseHas('call_detail_records', [
            'uuid' => 'command-call',
            'tenant_id' => $tenant->id,
        ]);
        $this->assertDatabaseHas('processed_cdr_files', [
            'file_name' => 'command.xml',
            'status' => ProcessedCdrFile::STATUS_PROCESSED,
            'call_uuid' => 'command-call',
        ]);

        Event::assertDispatched(CallDetailRecordCreated::class);
    }

    public function test_once_option_marks_failed_files_without_deleting_them(): void
    {
        Event::fake([CallDetailRecordCreated::class]);

        File::put($this->directory.'/failed.xml', $this->xmlFor([
            'uuid' => 'failed-call',
            'domain_name' => 'missing-tenant.example.com',
        ]));

        $this->artisan('cdr:ingest-xml', ['--once' => true])
            ->assertExitCode(0);

        $this->assertDatabaseHas('processed_cdr_files', [
            'file_name' => 'failed.xml',
            'status' => ProcessedCdrFile::STATUS_FAILED,
        ]);
        $this->assertTrue(File::exists($this->directory.'/failed.xml'));
        $this->assertDatabaseMissing('call_detail_records', [
            'uuid' => 'failed-call',
        ]);

        Event::assertNotDispatched(CallDetailRecordCreated::class);
    }

    public function test_once_option_is_idempotent_for_already_processed_files(): void
    {
        Event::fake([CallDetailRecordCreated::class]);

        $tenant = Tenant::factory()->create([
            'domain' => 'dedupe.example.com',
        ]);

        $path = $this->directory.'/dedupe.xml';
        File::put($path, $this->xmlFor([
            'uuid' => 'dedupe-call',
            'domain_name' => $tenant->domain,
        ]));

        $this->artisan('cdr:ingest-xml', ['--once' => true])
            ->assertExitCode(0);

        Event::fake([CallDetailRecordCreated::class]);

        $this->artisan('cdr:ingest-xml', ['--once' => true])
            ->assertExitCode(0);

        $this->assertSame(1, CallDetailRecord::query()->where('uuid', 'dedupe-call')->count());
        $this->assertSame(1, ProcessedCdrFile::query()->where('file_name', 'dedupe.xml')->count());
        Event::assertNotDispatched(CallDetailRecordCreated::class);
    }

    protected function xmlFor(array $overrides): string
    {
        $defaults = [
            'uuid' => 'command-default',
            'domain_name' => 'command.example.com',
            'caller_id_name' => '',
            'caller_id_number' => '01710000000',
            'destination_number' => '1000',
            'direction' => 'local',
            'recording_file' => '',
            'start_stamp' => '2026-04-12 10:00:00',
            'answer_stamp' => '',
            'end_stamp' => '2026-04-12 10:00:30',
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
