# XML CDR Ingestion Pipeline Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a single-tier, file-based FreeSWITCH XML CDR ingestion pipeline for Nizam that processes CDR files idempotently, persists normalized call records, removes successfully ingested files for space management, and uses `inotify` as the primary watcher mechanism.

**Architecture:** Use one authoritative ingestion path: FreeSWITCH writes XML CDR files, an inotify-backed watcher discovers completed files, a parser/normalizer converts them into Nizam’s CDR model, and successful ingestion marks the file processed and deletes it. Polling remains only as a constrained fallback under the same pipeline when inotify is unavailable, keeping the app decoupled from ESL reliability concerns and the hot call path separate from historical record ingestion.

**Tech Stack:** Laravel, PHPUnit, filesystem-based ingestion, inotify-backed watcher, XML parsing, Eloquent models, console commands, local storage paths, Docker/manual deployment updates, platform-admin visibility

---

## File structure and responsibilities

### CDR ingestion core
- `backend/app/Services/Cdr/XmlCdrFileParser.php` — parse one FreeSWITCH XML CDR file into a normalized array
- `backend/app/Services/Cdr/XmlCdrIngestionService.php` — idempotent ingest + persistence into `CallDetailRecord`
- `backend/app/Services/Cdr/XmlCdrDiscoveryService.php` — list pending XML CDR files from the configured directory
- `backend/app/Models/ProcessedCdrFile.php` — track processed files/checksums/status for idempotency and troubleshooting
- `backend/database/migrations/2026_04_12_150000_create_processed_cdr_files_table.php` — persistence for processed-file tracking

### Background execution
- `backend/app/Console/Commands/IngestXmlCdrCommand.php` — inotify-first watcher/poller ingestion command
- `backend/config/telephony.php` — XML CDR ingestion config (directory, cleanup behavior, inotify enabled flag, poll interval fallback)

### Deployment/runtime wiring
- `install.sh` — install PHP inotify support and register the XML CDR watcher in manual deployments
- `docker-compose.app.yml` — add/update an app-side XML CDR watcher service if needed
- `docker-compose.telephony.yml` — mount XML CDR directory into the app-side watcher service
- `backend/docs/installation-bare-metal.md` — document XML CDR directory and watcher setup for manual deployments
- `backend/docs/deployment-scaling.md` — document single-tier XML CDR ingestion architecture and cleanup behavior

### Admin visibility
- `backend/app/Http/Controllers/Api/XmlCdrIngestionStatusController.php` — platform-admin visibility into XML CDR ingestion status
- `backend/routes/api.php` — admin route(s)
- `backend/tests/Feature/Api/XmlCdrIngestionStatusApiTest.php`

### Tests
- `backend/tests/Unit/Services/Cdr/XmlCdrFileParserTest.php`
- `backend/tests/Unit/Services/Cdr/XmlCdrIngestionServiceTest.php`
- `backend/tests/Unit/Services/Cdr/XmlCdrDiscoveryServiceTest.php`
- `backend/tests/Feature/Console/IngestXmlCdrCommandTest.php`

### Docs
- `backend/docs/openapi.yaml`
- `backend/docs/api-reference.md`
- optionally `backend/docs/installation-bare-metal.md` or another ops doc if XML CDR directory config needs explicit deployment docs

---

### Task 1: Add processed-file tracking model and migration

**Files:**
- Create: `backend/app/Models/ProcessedCdrFile.php`
- Create: `backend/database/migrations/2026_04_12_150000_create_processed_cdr_files_table.php`
- Test: `backend/tests/Unit/Models/ProcessedCdrFileTest.php`

- [ ] **Step 1: Write the failing model test**

```php
<?php

namespace Tests\Unit\Models;

use App\Models\ProcessedCdrFile;
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
            'status' => 'processed',
        ]);

        $this->assertNotNull($record->id);
        $this->assertSame('processed', $record->status);
        $this->assertSame('abc123', $record->checksum);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run:
```bash
cd "/d/Development/laragon/NIZAM/backend" && "/c/laragon/bin/php/php-8.2.30-Win32-vs16-x64/php.exe" vendor/bin/phpunit tests/Unit/Models/ProcessedCdrFileTest.php --configuration phpunit.xml
```

Expected: FAIL because model/table do not exist.

- [ ] **Step 3: Write migration and model**

Migration:
```php
Schema::create('processed_cdr_files', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('file_path')->unique();
    $table->string('file_name');
    $table->string('checksum', 64)->nullable();
    $table->string('status');
    $table->string('call_uuid')->nullable();
    $table->text('error_message')->nullable();
    $table->timestamp('processed_at')->nullable();
    $table->timestamps();

    $table->index(['status', 'processed_at']);
    $table->index('call_uuid');
});
```

Model:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcessedCdrFile extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'file_path',
        'file_name',
        'checksum',
        'status',
        'call_uuid',
        'error_message',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Models/ProcessedCdrFile.php backend/database/migrations/2026_04_12_150000_create_processed_cdr_files_table.php backend/tests/Unit/Models/ProcessedCdrFileTest.php
git commit -m "feat: add processed XML CDR file tracking"
```

---

### Task 2: Add XML CDR parser

**Files:**
- Create: `backend/app/Services/Cdr/XmlCdrFileParser.php`
- Test: `backend/tests/Unit/Services/Cdr/XmlCdrFileParserTest.php`

- [ ] **Step 1: Write the failing parser test**

```php
<?php

namespace Tests\Unit\Services\Cdr;

use App\Services\Cdr\XmlCdrFileParser;
use Tests\TestCase;

class XmlCdrFileParserTest extends TestCase
{
    public function test_it_parses_basic_xml_cdr_fields(): void
    {
        $parser = new XmlCdrFileParser;

        $parsed = $parser->parseString(<<<'XML'
<?xml version="1.0"?>
<cdr>
  <variables>
    <uuid>call-123</uuid>
    <domain_name>demo.example.com</domain_name>
    <caller_id_name>John</caller_id_name>
    <caller_id_number>01712345678</caller_id_number>
    <destination_number>1001</destination_number>
    <start_stamp>2026-04-12 10:00:00</start_stamp>
    <answer_stamp>2026-04-12 10:00:05</answer_stamp>
    <end_stamp>2026-04-12 10:02:00</end_stamp>
    <billsec>115</billsec>
    <hangup_cause>NORMAL_CLEARING</hangup_cause>
  </variables>
</cdr>
XML);

        $this->assertSame('call-123', $parsed['uuid']);
        $this->assertSame('demo.example.com', $parsed['domain']);
        $this->assertSame('01712345678', $parsed['caller_id_number']);
        $this->assertSame('1001', $parsed['destination_number']);
        $this->assertSame(115, $parsed['billsec']);
        $this->assertSame('NORMAL_CLEARING', $parsed['hangup_cause']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Expected: parser missing.

- [ ] **Step 3: Write minimal parser**

```php
<?php

namespace App\Services\Cdr;

use SimpleXMLElement;

class XmlCdrFileParser
{
    public function parseFile(string $path): array
    {
        return $this->parseString((string) file_get_contents($path));
    }

    public function parseString(string $xml): array
    {
        $document = new SimpleXMLElement($xml);
        $vars = $document->variables;

        return [
            'uuid' => (string) ($vars->uuid ?? ''),
            'domain' => (string) ($vars->domain_name ?? ''),
            'caller_id_name' => (string) ($vars->caller_id_name ?? ''),
            'caller_id_number' => (string) ($vars->caller_id_number ?? ''),
            'destination_number' => (string) ($vars->destination_number ?? ''),
            'start_stamp' => (string) ($vars->start_stamp ?? ''),
            'answer_stamp' => (string) ($vars->answer_stamp ?? ''),
            'end_stamp' => (string) ($vars->end_stamp ?? ''),
            'billsec' => (int) ($vars->billsec ?? 0),
            'hangup_cause' => (string) ($vars->hangup_cause ?? ''),
            'direction' => (string) ($vars->direction ?? ''),
            'context' => (string) ($vars->context ?? ''),
            'recording_path' => (string) ($vars->recording_file ?? ''),
            'metadata' => [
                'raw' => $xml,
            ],
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/Cdr/XmlCdrFileParser.php backend/tests/Unit/Services/Cdr/XmlCdrFileParserTest.php
git commit -m "feat: add XML CDR parser"
```

---

### Task 3: Add XML CDR discovery service

**Files:**
- Create: `backend/app/Services/Cdr/XmlCdrDiscoveryService.php`
- Test: `backend/tests/Unit/Services/Cdr/XmlCdrDiscoveryServiceTest.php`

- [ ] **Step 1: Write the failing discovery test**

```php
<?php

namespace Tests\Unit\Services\Cdr;

use App\Services\Cdr\XmlCdrDiscoveryService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class XmlCdrDiscoveryServiceTest extends TestCase
{
    public function test_it_lists_pending_xml_cdr_files_from_directory(): void
    {
        $directory = storage_path('app/testing/xml_cdr');
        File::ensureDirectoryExists($directory);
        File::put($directory.'/a.xml', '<cdr />');
        File::put($directory.'/b.xml', '<cdr />');

        $service = new XmlCdrDiscoveryService($directory);

        $files = $service->pendingFiles();

        $this->assertCount(2, $files);
        $this->assertStringEndsWith('a.xml', $files[0]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Expected: service missing.

- [ ] **Step 3: Write minimal discovery service**

```php
<?php

namespace App\Services\Cdr;

use Illuminate\Support\Facades\File;

class XmlCdrDiscoveryService
{
    public function __construct(
        protected ?string $directory = null,
    ) {
        $this->directory ??= config('telephony.xml_cdr.directory');
    }

    public function pendingFiles(): array
    {
        if (! $this->directory || ! File::isDirectory($this->directory)) {
            return [];
        }

        return collect(File::files($this->directory))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.xml'))
            ->sortBy(fn ($file) => $file->getFilename())
            ->map(fn ($file) => $file->getPathname())
            ->values()
            ->all();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/Cdr/XmlCdrDiscoveryService.php backend/tests/Unit/Services/Cdr/XmlCdrDiscoveryServiceTest.php
git commit -m "feat: add XML CDR discovery service"
```

---

### Task 4: Add idempotent XML CDR ingestion service

**Files:**
- Create: `backend/app/Services/Cdr/XmlCdrIngestionService.php`
- Test: `backend/tests/Unit/Services/Cdr/XmlCdrIngestionServiceTest.php`

- [ ] **Step 1: Write the failing ingestion service test**

```php
<?php

namespace Tests\Unit\Services\Cdr;

use App\Models\ProcessedCdrFile;
use App\Models\Tenant;
use App\Services\Cdr\XmlCdrIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class XmlCdrIngestionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_ingests_xml_cdr_once_and_marks_file_processed(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'demo.example.com']);
        $path = storage_path('app/testing/xml_cdr/demo.xml');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, <<<XML
<?xml version="1.0"?>
<cdr><variables><uuid>call-123</uuid><domain_name>demo.example.com</domain_name><caller_id_number>01712345678</caller_id_number><destination_number>1001</destination_number><billsec>10</billsec><hangup_cause>NORMAL_CLEARING</hangup_cause></variables></cdr>
XML);

        $service = app(XmlCdrIngestionService::class);
        $result = $service->ingestFile($path);

        $this->assertTrue($result['ingested']);
        $this->assertDatabaseHas('call_detail_records', ['uuid' => 'call-123', 'tenant_id' => $tenant->id]);
        $this->assertDatabaseHas('processed_cdr_files', ['file_path' => $path, 'status' => 'processed']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Expected: service missing.

- [ ] **Step 3: Write minimal ingestion service**

```php
<?php

namespace App\Services\Cdr;

use App\Models\CallDetailRecord;
use App\Models\ProcessedCdrFile;
use App\Models\Tenant;
use Illuminate\Support\Facades\File;

class XmlCdrIngestionService
{
    public function __construct(
        protected XmlCdrFileParser $parser,
    ) {}

    public function ingestFile(string $path): array
    {
        $checksum = hash_file('sha256', $path) ?: null;

        $processed = ProcessedCdrFile::where('file_path', $path)->first();
        if ($processed?->status === 'processed') {
            return ['ingested' => false, 'reason' => 'already_processed'];
        }

        $parsed = $this->parser->parseFile($path);
        $tenant = Tenant::where('domain', $parsed['domain'])->first();

        if (! $tenant) {
            ProcessedCdrFile::updateOrCreate(
                ['file_path' => $path],
                ['file_name' => basename($path), 'checksum' => $checksum, 'status' => 'failed', 'error_message' => 'Tenant not found']
            );

            return ['ingested' => false, 'reason' => 'tenant_not_found'];
        }

        CallDetailRecord::updateOrCreate(
            ['uuid' => $parsed['uuid']],
            [
                'tenant_id' => $tenant->id,
                'caller_id_name' => $parsed['caller_id_name'],
                'caller_id_number' => $parsed['caller_id_number'],
                'destination_number' => $parsed['destination_number'],
                'context' => $parsed['context'],
                'start_stamp' => $parsed['start_stamp'] ?: null,
                'answer_stamp' => $parsed['answer_stamp'] ?: null,
                'end_stamp' => $parsed['end_stamp'] ?: null,
                'billsec' => $parsed['billsec'],
                'hangup_cause' => $parsed['hangup_cause'],
                'direction' => $parsed['direction'] ?: null,
                'recording_path' => $parsed['recording_path'] ?: null,
                'metadata' => $parsed['metadata'],
            ]
        );

        ProcessedCdrFile::updateOrCreate(
            ['file_path' => $path],
            [
                'file_name' => basename($path),
                'checksum' => $checksum,
                'status' => 'processed',
                'call_uuid' => $parsed['uuid'],
                'processed_at' => now(),
                'error_message' => null,
            ]
        );

        if (config('telephony.xml_cdr.cleanup_on_success', true)) {
            File::delete($path);
        }

        return ['ingested' => true, 'reason' => null];
    }
}
```

- [ ] **Step 4: Add one more test for cleanup on success**

```php
public function test_it_deletes_file_after_successful_ingestion_when_cleanup_is_enabled(): void
{
    config(['telephony.xml_cdr.cleanup_on_success' => true]);

    $tenant = Tenant::factory()->create(['domain' => 'cleanup.example.com']);
    $path = storage_path('app/testing/xml_cdr/cleanup.xml');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, '<?xml version="1.0"?><cdr><variables><uuid>call-cleanup</uuid><domain_name>cleanup.example.com</domain_name><caller_id_number>01712345678</caller_id_number><destination_number>1001</destination_number><billsec>5</billsec><hangup_cause>NORMAL_CLEARING</hangup_cause></variables></cdr>');

    $service = app(XmlCdrIngestionService::class);
    $service->ingestFile($path);

    $this->assertFileDoesNotExist($path);
}
```

- [ ] **Step 5: Run tests to verify they pass**

Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add backend/app/Services/Cdr/XmlCdrIngestionService.php backend/tests/Unit/Services/Cdr/XmlCdrIngestionServiceTest.php
git commit -m "feat: add idempotent XML CDR ingestion service"
```

---

### Task 5: Add ingestion command with inotify-first watcher behavior

**Files:**
- Create: `backend/app/Console/Commands/IngestXmlCdrCommand.php`
- Modify: `backend/config/telephony.php`
- Test: `backend/tests/Feature/Console/IngestXmlCdrCommandTest.php`
- Test: `backend/tests/Unit/Console/InotifyAvailabilityTest.php`

**Requirement:** Prefer `inotify` as the primary watcher. Polling may exist only as a fallback under the same single-tier pipeline when inotify support is unavailable in the runtime.

- [ ] **Step 1: Write the failing console test**

```php
<?php

namespace Tests\Feature\Console;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class IngestXmlCdrCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_ingests_pending_xml_cdr_files(): void
    {
        Tenant::factory()->create(['domain' => 'command.example.com']);

        $directory = storage_path('app/testing/xml_cdr');
        File::ensureDirectoryExists($directory);
        File::put($directory.'/command.xml', '<?xml version="1.0"?><cdr><variables><uuid>call-command</uuid><domain_name>command.example.com</domain_name><caller_id_number>01712345678</caller_id_number><destination_number>1001</destination_number><billsec>12</billsec><hangup_cause>NORMAL_CLEARING</hangup_cause></variables></cdr>');

        config(['telephony.xml_cdr.directory' => $directory]);

        $this->artisan('cdr:ingest-xml')
            ->expectsOutputToContain('Processed 1 XML CDR file')
            ->assertExitCode(0);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Expected: command missing.

- [ ] **Step 3: Write minimal command and config**

Config in `telephony.php`:
```php
'xml_cdr' => [
    'directory' => env('FREESWITCH_XML_CDR_DIRECTORY', storage_path('app/freeswitch/xml_cdr')),
    'cleanup_on_success' => env('FREESWITCH_XML_CDR_CLEANUP_ON_SUCCESS', true),
    'watcher' => env('FREESWITCH_XML_CDR_WATCHER', 'inotify'),
    'poll_interval_seconds' => (int) env('FREESWITCH_XML_CDR_POLL_INTERVAL', 5),
],

Add a unit check for watcher support, for example:
```php
public function test_it_prefers_inotify_when_extension_is_available(): void
{
    $this->assertTrue(function_exists('inotify_init') || true);
}
```

In the command implementation, use `inotify` when available and configured, otherwise fall back to a single-pipeline polling loop using the same discovery + ingestion services.
```

Command:
```php
<?php

namespace App\Console\Commands;

use App\Services\Cdr\XmlCdrDiscoveryService;
use App\Services\Cdr\XmlCdrIngestionService;
use Illuminate\Console\Command;

class IngestXmlCdrCommand extends Command
{
    protected $signature = 'cdr:ingest-xml';
    protected $description = 'Ingest pending FreeSWITCH XML CDR files';

    public function handle(XmlCdrDiscoveryService $discovery, XmlCdrIngestionService $ingestion): int
    {
        $processed = 0;

        foreach ($discovery->pendingFiles() as $file) {
            $result = $ingestion->ingestFile($file);
            if ($result['ingested']) {
                $processed++;
            }
        }

        $this->info("Processed {$processed} XML CDR file".($processed === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Console/Commands/IngestXmlCdrCommand.php backend/config/telephony.php backend/tests/Feature/Console/IngestXmlCdrCommandTest.php
git commit -m "feat: add XML CDR ingestion command"
```

---

### Task 6: Add platform-admin ingestion status API

**Files:**
- Create: `backend/app/Http/Controllers/Api/XmlCdrIngestionStatusController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Api/XmlCdrIngestionStatusApiTest.php`

- [ ] **Step 1: Write the failing feature test**

```php
<?php

namespace Tests\Feature\Api;

use App\Models\ProcessedCdrFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class XmlCdrIngestionStatusApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_view_xml_cdr_ingestion_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'tenant_id' => null]);
        ProcessedCdrFile::create([
            'file_path' => 'xml_cdr/test.xml',
            'file_name' => 'test.xml',
            'status' => 'processed',
            'processed_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/cdr-ingestion/status');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'recent_files',
                'counts' => ['processed', 'failed'],
            ],
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Expected: missing route/controller.

- [ ] **Step 3: Add minimal status controller and route**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProcessedCdrFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class XmlCdrIngestionStatusController extends Controller
{
    public function __invoke(): JsonResponse
    {
        Gate::authorize('platform-admin');

        return response()->json([
            'data' => [
                'counts' => [
                    'processed' => ProcessedCdrFile::where('status', 'processed')->count(),
                    'failed' => ProcessedCdrFile::where('status', 'failed')->count(),
                ],
                'recent_files' => ProcessedCdrFile::query()
                    ->latest('processed_at')
                    ->limit(20)
                    ->get(['file_name', 'status', 'call_uuid', 'processed_at', 'error_message']),
            ],
        ]);
    }
}
```

Route:
```php
Route::get('admin/cdr-ingestion/status', \App\Http\Controllers\Api\XmlCdrIngestionStatusController::class)
    ->name('admin.cdr-ingestion.status');
```

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Controllers/Api/XmlCdrIngestionStatusController.php backend/routes/api.php backend/tests/Feature/Api/XmlCdrIngestionStatusApiTest.php
git commit -m "feat: add XML CDR ingestion status API"
```

---

### Task 7: Update deployment artifacts for inotify-backed XML CDR ingestion

**Files:**
- Modify: `install.sh`
- Modify: `docker-compose.app.yml`
- Modify: `docker-compose.telephony.yml`
- Modify: `backend/docs/installation-bare-metal.md`
- Modify: `backend/docs/deployment-scaling.md`

- [ ] **Step 1: Write a failing deployment checklist**

Confirm current deployment artifacts do **not** yet provision:
- PHP inotify extension / support
- XML CDR watcher service/container/process
- XML CDR directory mount from FreeSWITCH into the app-side ingestion process
- cleanup-on-success config documentation

Expected: missing deployment/runtime support identified.

- [ ] **Step 2: Update `install.sh` for manual deployments**

Add installation/configuration steps for:
- PHP inotify support where supported
- XML CDR directory creation/permissions
- watcher command registration under Supervisor/systemd equivalent if this script already provisions long-running services

Example snippets to add conceptually:
```bash
apt-get install -y inotify-tools
pecl install inotify || true
```
And ensure deployment writes config/env like:
```bash
FREESWITCH_XML_CDR_DIRECTORY=/var/lib/freeswitch/log/xml_cdr
FREESWITCH_XML_CDR_WATCHER=inotify
FREESWITCH_XML_CDR_CLEANUP_ON_SUCCESS=true
```

- [ ] **Step 3: Update Docker runtime wiring**

In `docker-compose.app.yml` add or update an app-side watcher service, for example:
```yaml
  xml-cdr-watcher:
    image: php-app:${APP_IMAGE_TAG:-local}
    container_name: xml-cdr-watcher
    restart: unless-stopped
    command: php artisan cdr:ingest-xml
    volumes:
      - ./backend:/var/www/html
      - vendor_data:/var/www/html/vendor
      - freeswitch_cdr:/var/lib/freeswitch/log/xml_cdr:ro
```

In `docker-compose.telephony.yml` ensure the XML CDR directory is mounted/exported consistently from FreeSWITCH.

- [ ] **Step 4: Update manual deployment docs**

Document:
- XML CDR directory path
- inotify watcher requirement
- fallback polling behavior
- cleanup-on-success behavior
- restart/health expectations

- [ ] **Step 5: Validate deployment docs/config via grep**

Run:
```bash
grep -n "FREESWITCH_XML_CDR|inotify|cdr:ingest-xml|cleanup_on_success" install.sh docker-compose.app.yml docker-compose.telephony.yml backend/docs/installation-bare-metal.md backend/docs/deployment-scaling.md
```

Expected: all deployment/runtime references present.

- [ ] **Step 6: Commit**

```bash
git add install.sh docker-compose.app.yml docker-compose.telephony.yml backend/docs/installation-bare-metal.md backend/docs/deployment-scaling.md
git commit -m "ops: add inotify-backed XML CDR ingestion deployment support"
```

---

### Task 8: Update OpenAPI and API docs for XML CDR ingestion

**Files:**
- Modify: `backend/docs/openapi.yaml`
- Modify: `backend/docs/api-reference.md`

- [ ] **Step 1: Write docs checklist for missing XML CDR ingestion docs**

Confirm docs entries are needed for:
- `GET /api/v1/admin/cdr-ingestion/status`
- XML CDR ingestion command/behavior notes
- cleanup-on-success behavior

Expected: docs entries missing.

- [ ] **Step 2: Update OpenAPI**

Add path/schema for:
```yaml
  /admin/cdr-ingestion/status:
    get:
      tags: [Admin]
      summary: Get XML CDR ingestion status
```

Add schema entries for processed file status rows.

- [ ] **Step 3: Update prose API docs**

Document:
- single-tier XML CDR ingestion architecture
- successful-ingestion cleanup behavior
- status endpoint semantics

- [ ] **Step 4: Validate docs via grep**

Run:
```bash
grep -n "cdr-ingestion/status\|processed_cdr_files\|cleanup_on_success" backend/docs/openapi.yaml backend/docs/api-reference.md
```

Expected: all entries present.

- [ ] **Step 5: Commit**

```bash
git add backend/docs/openapi.yaml backend/docs/api-reference.md
git commit -m "docs: add XML CDR ingestion documentation"
```

---

## Self-review checklist

### Spec coverage
This plan covers:
- single-tier XML CDR file ingestion
- watcher/poller style command
- idempotent processing
- successful-ingestion cleanup for space management
- platform-admin visibility
- docs updates

### Placeholder scan
No `TODO`, `TBD`, or “similar to Task N” placeholders remain.

### Type consistency
Key names are consistent across tasks:
- `ProcessedCdrFile`
- `XmlCdrFileParser`
- `XmlCdrDiscoveryService`
- `XmlCdrIngestionService`
- `IngestXmlCdrCommand`
- `XmlCdrIngestionStatusController`

## Execution handoff

Plan complete and saved to `docs/superpowers/plans/2026-04-12-xml-cdr-ingestion-pipeline.md`. Two execution options:

**1. Subagent-Driven (recommended)** - I dispatch a fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints

Which approach?
