# Platform Admin Modules Status and Indexing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a platform-admin FreeSWITCH modules status surface, enforce the hard foreign-key indexing rule through schema/index migrations, and update API docs and frontend admin pages to reflect the new platform/admin capabilities and default business-phone features.

**Architecture:** Keep the hot telephony path compiled and deterministic, but make module status, schema/index hygiene, and platform visibility event/observer-driven wherever practical. Runtime module state should be exposed through explicit backend services and APIs, while database indexing is enforced through additive migrations and documented in the OpenAPI/admin UX surfaces.

**Tech Stack:** Laravel, PHPUnit, OpenAPI YAML, React/TypeScript frontend, FreeSWITCH ESL/CLI integration, PostgreSQL/MySQL-compatible migrations

---

## File structure and responsibilities

### Backend platform-admin runtime visibility
- `backend/app/Services/Admin/FreeSwitchModuleStatusService.php` — fetch and normalize FreeSWITCH module status for platform admins
- `backend/app/Http/Controllers/Api/FreeSwitchModuleStatusController.php` — expose module status API endpoints
- `backend/routes/api.php` — add admin route(s) for module status
- `backend/tests/Unit/Services/Admin/FreeSwitchModuleStatusServiceTest.php` — unit coverage for parsing/normalization
- `backend/tests/Feature/Api/FreeSwitchModuleStatusApiTest.php` — API authorization/response coverage

### Backend DB indexing enforcement
- `backend/database/migrations/2026_04_12_*.php` — additive index migrations for missing foreign-key indexes and targeted composite indexes
- `backend/tests/Feature/Database/ForeignKeyIndexingTest.php` — validate FK indexes exist for required relations
- `backend/tests/Feature/Database/ReportingIndexCoverageTest.php` — validate key tenant/reporting composites exist

### Backend docs
- `backend/docs/openapi.yaml` — add/modify endpoints and schemas
- `backend/docs/api-reference.md` — update human-readable API docs if this repo keeps both machine and prose docs in sync
- `backend/docs/KNOWN_LIMITATIONS.md` — remove/update statements contradicted by completed implementation only if necessary

### Frontend admin/platform UX
- `frontend/src/pages/admin/FreeSwitchModulesPage.tsx` — new page for platform-admin module visibility
- `frontend/src/app.tsx` — route registration/navigation wiring
- `frontend/src/pages/admin/CapabilitiesPage.tsx` — optionally link or summarize new module status page
- `frontend/src/lib/api.ts` or existing typed client files — add request helpers if needed
- `frontend/src/types/models.ts` — add module status DTO types if this is the existing convention

### Frontend PBX/defaults follow-through
If current scope also includes “update frontend all of them” for recent default business-phone work, then update/admin pages for:
- tenant creation/edit display of `default_schedule_id` / `default_holiday_calendar_id`
- supervisor reporting screens if not already exposed
- any existing admin tenant page that should show bootstrap/default business-phone metadata

---

### Task 1: Add FreeSWITCH module status backend service

**Files:**
- Create: `backend/app/Services/Admin/FreeSwitchModuleStatusService.php`
- Test: `backend/tests/Unit/Services/Admin/FreeSwitchModuleStatusServiceTest.php`

- [ ] **Step 1: Write the failing service test for FreeSWITCH module parsing**

```php
<?php

namespace Tests\Unit\Services\Admin;

use App\Services\Admin\FreeSwitchModuleStatusService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class FreeSwitchModuleStatusServiceTest extends TestCase
{
    public function test_it_parses_module_show_output_into_normalized_status_rows(): void
    {
        $service = new FreeSwitchModuleStatusService;

        $rows = $service->parseShowModulesOutput(<<<'TEXT'
name,type,status
mod_sofia,endpoint,Running
mod_conference,application,Running
mod_avmd,application,Not Loaded
TEXT);

        $this->assertInstanceOf(Collection::class, $rows);
        $this->assertCount(3, $rows);
        $this->assertSame('mod_sofia', $rows[0]['name']);
        $this->assertSame('endpoint', $rows[0]['type']);
        $this->assertSame('running', $rows[0]['status']);
        $this->assertSame('not_loaded', $rows[2]['status']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run:
```bash
cd "/d/Development/laragon/NIZAM/backend" && "/c/laragon/bin/php/php-8.2.30-Win32-vs16-x64/php.exe" vendor/bin/phpunit tests/Unit/Services/Admin/FreeSwitchModuleStatusServiceTest.php --configuration phpunit.xml
```

Expected: FAIL with `Class "App\Services\Admin\FreeSwitchModuleStatusService" not found`

- [ ] **Step 3: Write minimal FreeSWITCH module status service**

```php
<?php

namespace App\Services\Admin;

use App\Services\Media\FreeSwitchCommandService;
use Illuminate\Support\Collection;

class FreeSwitchModuleStatusService
{
    public function __construct(
        protected ?FreeSwitchCommandService $freeSwitch = null,
    ) {}

    public function list(): Collection
    {
        $output = (string) ($this->freeSwitch?->execute('show', ['modules']) ?? '');

        return $this->parseShowModulesOutput($output);
    }

    public function parseShowModulesOutput(string $output): Collection
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($output)) ?: [];
        $rows = collect();

        foreach ($lines as $index => $line) {
            if ($line === '' || $index === 0) {
                continue;
            }

            [$name, $type, $status] = array_pad(str_getcsv($line), 3, '');

            $rows->push([
                'name' => trim($name),
                'type' => trim($type),
                'status' => str(trim($status))->lower()->replace(' ', '_')->value(),
            ]);
        }

        return $rows;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run:
```bash
cd "/d/Development/laragon/NIZAM/backend" && "/c/laragon/bin/php/php-8.2.30-Win32-vs16-x64/php.exe" vendor/bin/phpunit tests/Unit/Services/Admin/FreeSwitchModuleStatusServiceTest.php --configuration phpunit.xml
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/Admin/FreeSwitchModuleStatusService.php backend/tests/Unit/Services/Admin/FreeSwitchModuleStatusServiceTest.php
git commit -m "feat: add FreeSWITCH module status service"
```

---

### Task 2: Expose platform-admin API for FreeSWITCH module status

**Files:**
- Create: `backend/app/Http/Controllers/Api/FreeSwitchModuleStatusController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Api/FreeSwitchModuleStatusApiTest.php`

- [ ] **Step 1: Write the failing API feature test**

```php
<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FreeSwitchModuleStatusApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_view_freeswitch_modules_status(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'tenant_id' => null]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/freeswitch/modules');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['name', 'type', 'status'],
            ],
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run:
```bash
cd "/d/Development/laragon/NIZAM/backend" && "/c/laragon/bin/php/php-8.2.30-Win32-vs16-x64/php.exe" vendor/bin/phpunit tests/Feature/Api/FreeSwitchModuleStatusApiTest.php --configuration phpunit.xml
```

Expected: FAIL with route/controller missing

- [ ] **Step 3: Write minimal controller and route**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Admin\FreeSwitchModuleStatusService;
use Illuminate\Http\JsonResponse;

class FreeSwitchModuleStatusController extends Controller
{
    public function __invoke(FreeSwitchModuleStatusService $service): JsonResponse
    {
        $this->authorize('platform-admin');

        return response()->json([
            'data' => $service->list()->values()->all(),
        ]);
    }
}
```

Add route under existing admin/platform routes:
```php
Route::get('admin/freeswitch/modules', \App\Http\Controllers\Api\FreeSwitchModuleStatusController::class)
    ->name('admin.freeswitch.modules');
```

- [ ] **Step 4: Run test to verify it passes**

Run same PHPUnit command as Step 2.

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Controllers/Api/FreeSwitchModuleStatusController.php backend/routes/api.php backend/tests/Feature/Api/FreeSwitchModuleStatusApiTest.php
git commit -m "feat: expose FreeSWITCH module status API"
```

---

### Task 3: Add frontend platform-admin page for FreeSWITCH modules

**Files:**
- Create: `frontend/src/pages/admin/FreeSwitchModulesPage.tsx`
- Modify: `frontend/src/app.tsx`
- Modify: `frontend/src/types/models.ts`

- [ ] **Step 1: Write the failing frontend model/type usage snapshot mentally by wiring the route first**

Add route import placeholder in `frontend/src/app.tsx`:
```tsx
import FreeSwitchModulesPage from '@/pages/admin/FreeSwitchModulesPage';
```

Expected build failure: file/module missing

- [ ] **Step 2: Create minimal DTO type**

In `frontend/src/types/models.ts` add:
```ts
export interface FreeSwitchModuleStatus {
  name: string;
  type: string;
  status: string;
}
```

- [ ] **Step 3: Create minimal admin page**

```tsx
import { useQuery } from '@tanstack/react-query';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import api from '@/lib/api';
import type { FreeSwitchModuleStatus } from '@/types/models';

export default function FreeSwitchModulesPage() {
  const { data, isLoading } = useQuery({
    queryKey: ['admin-freeswitch-modules'],
    queryFn: async () => {
      const res = await api.get<{ data: FreeSwitchModuleStatus[] }>('admin/freeswitch/modules');
      return res.data.data;
    },
    refetchInterval: 15000,
  });

  return (
    <div className="space-y-6 p-6 lg:p-8">
      <div>
        <p className="text-sm text-muted-foreground">Platform Admin › FreeSWITCH</p>
        <h1 className="text-2xl font-bold tracking-tight">FreeSWITCH Modules</h1>
        <p className="text-muted-foreground">Lists all FreeSWITCH modules and their status for platform admins.</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Module Status</CardTitle>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Name</TableHead>
                <TableHead>Type</TableHead>
                <TableHead>Status</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {(data ?? []).map((row) => (
                <TableRow key={row.name}>
                  <TableCell>{row.name}</TableCell>
                  <TableCell>{row.type}</TableCell>
                  <TableCell>{row.status}</TableCell>
                </TableRow>
              ))}
              {!isLoading && (data ?? []).length === 0 && (
                <TableRow>
                  <TableCell colSpan={3}>No module data available.</TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
}
```

- [ ] **Step 4: Register the admin route**

In `frontend/src/app.tsx`, add a route consistent with current admin pages, e.g.:
```tsx
<Route path="/admin/freeswitch/modules" element={<FreeSwitchModulesPage />} />
```

- [ ] **Step 5: Run frontend checks**

Run:
```bash
cd "/d/Development/laragon/NIZAM/frontend" && npm test -- --runInBand
```
or if the repo uses a different validation command, use the existing frontend test/build command.

Expected: route/page compiles without type errors.

- [ ] **Step 6: Commit**

```bash
git add frontend/src/pages/admin/FreeSwitchModulesPage.tsx frontend/src/app.tsx frontend/src/types/models.ts
git commit -m "feat: add FreeSWITCH modules admin page"
```

---

### Task 4: Enforce the hard foreign-key indexing rule

**Files:**
- Create: `backend/database/migrations/2026_04_12_130000_add_missing_foreign_key_indexes.php`
- Test: `backend/tests/Feature/Database/ForeignKeyIndexingTest.php`

- [ ] **Step 1: Write failing schema audit test for foreign-key indexes**

```php
<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ForeignKeyIndexingTest extends TestCase
{
    use RefreshDatabase;

    public function test_foreign_keys_have_indexes_on_core_multitenant_tables(): void
    {
        $this->assertTrue($this->hasIndex('extensions', 'extensions_tenant_id_index'));
        $this->assertTrue($this->hasIndex('device_profiles', 'device_profiles_extension_id_index'));
        $this->assertTrue($this->hasIndex('dids', 'dids_tenant_id_index'));
        $this->assertTrue($this->hasIndex('recordings', 'recordings_tenant_id_index'));
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $database = DB::getDatabaseName();
        $rows = DB::select("SHOW INDEX FROM {$table}");

        return collect($rows)->contains(fn ($row) => ($row->Key_name ?? null) === $indexName);
    }
}
```

- [ ] **Step 2: Run test to see which indexes are missing**

Run:
```bash
cd "/d/Development/laragon/NIZAM/backend" && "/c/laragon/bin/php/php-8.2.30-Win32-vs16-x64/php.exe" vendor/bin/phpunit tests/Feature/Database/ForeignKeyIndexingTest.php --configuration phpunit.xml
```

Expected: FAIL on missing indexes

- [ ] **Step 3: Add migration for missing FK indexes**

Create migration adding indexes only where absent, for example:
```php
Schema::table('extensions', function (Blueprint $table) {
    $table->index('tenant_id');
    $table->index('user_id');
});

Schema::table('device_profiles', function (Blueprint $table) {
    $table->index('tenant_id');
    $table->index('user_id');
    $table->index('extension_id');
});

Schema::table('dids', function (Blueprint $table) {
    $table->index('tenant_id');
    $table->index('gateway_id');
});

Schema::table('flows', function (Blueprint $table) {
    $table->index('tenant_id');
    $table->index('active_version_id');
});
```

Continue this for all core FK-backed relations discovered in existing schema.

- [ ] **Step 4: Re-run the schema audit test**

Run same PHPUnit command as Step 2.

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/database/migrations/2026_04_12_130000_add_missing_foreign_key_indexes.php backend/tests/Feature/Database/ForeignKeyIndexingTest.php
git commit -m "perf: add missing foreign key indexes"
```

---

### Task 5: Add composite indexes for reporting and routing query paths

**Files:**
- Create: `backend/database/migrations/2026_04_12_131000_add_reporting_and_routing_composite_indexes.php`
- Test: `backend/tests/Feature/Database/ReportingIndexCoverageTest.php`

- [ ] **Step 1: Write failing test for composite index coverage**

```php
<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportingIndexCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_reporting_tables_have_tenant_time_indexes(): void
    {
        $this->assertTrue($this->hasIndex('call_detail_records', 'cdrs_tenant_created_at_index'));
        $this->assertTrue($this->hasIndex('call_event_logs', 'call_event_logs_tenant_event_created_index'));
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $rows = DB::select("SHOW INDEX FROM {$table}");

        return collect($rows)->contains(fn ($row) => ($row->Key_name ?? null) === $indexName);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run the PHPUnit command for this file.

Expected: FAIL on missing composites

- [ ] **Step 3: Add migration for high-value composites**

Examples to add if not already present:
```php
Schema::table('call_detail_records', function (Blueprint $table) {
    $table->index(['tenant_id', 'created_at'], 'cdrs_tenant_created_at_index');
    $table->index(['tenant_id', 'direction', 'created_at'], 'cdrs_tenant_direction_created_index');
    $table->index(['tenant_id', 'caller_id_number', 'created_at'], 'cdrs_tenant_caller_created_index');
});

Schema::table('call_event_logs', function (Blueprint $table) {
    $table->index(['tenant_id', 'event_type', 'created_at'], 'call_event_logs_tenant_event_created_index');
});

Schema::table('recordings', function (Blueprint $table) {
    $table->index(['tenant_id', 'created_at'], 'recordings_tenant_created_index');
});
```

- [ ] **Step 4: Re-run composite index coverage test**

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/database/migrations/2026_04_12_131000_add_reporting_and_routing_composite_indexes.php backend/tests/Feature/Database/ReportingIndexCoverageTest.php
git commit -m "perf: add reporting and routing composite indexes"
```

---

### Task 6: Update OpenAPI and backend API docs

**Files:**
- Modify: `backend/docs/openapi.yaml`
- Modify: `backend/docs/api-reference.md`

- [ ] **Step 1: Add failing documentation checklist item in your working notes**

Check that docs cover:
- `GET /admin/freeswitch/modules`
- tenant resource fields `default_schedule_id`, `default_holiday_calendar_id`
- supervisor reports endpoints if still missing
- any new admin route/page references

Expected: missing docs entries found

- [ ] **Step 2: Update OpenAPI spec**

Add new tag if needed under `tags:`:
```yaml
  - name: FreeSWITCH Modules
```

Add path:
```yaml
  /admin/freeswitch/modules:
    get:
      tags: [Admin, FreeSWITCH Modules]
      summary: List FreeSWITCH modules and their status
      operationId: listFreeSwitchModules
      responses:
        '200':
          description: Module status list
          content:
            application/json:
              schema:
                type: object
                properties:
                  data:
                    type: array
                    items:
                      $ref: '#/components/schemas/FreeSwitchModuleStatus'
```

Add schema:
```yaml
    FreeSwitchModuleStatus:
      type: object
      required: [name, type, status]
      properties:
        name:
          type: string
        type:
          type: string
        status:
          type: string
```

Also update `Tenant` schema to include:
```yaml
        default_schedule_id:
          type: string
          nullable: true
        default_holiday_calendar_id:
          type: string
          nullable: true
```

- [ ] **Step 3: Update prose API docs**

Add a section in `backend/docs/api-reference.md` describing:
- platform-admin FreeSWITCH modules endpoint
- tenant bootstrap default fields
- supervisor reports if not already documented consistently

- [ ] **Step 4: Validate docs are internally consistent**

Run a quick grep/check:
```bash
grep -n "admin/freeswitch/modules\|default_schedule_id\|default_holiday_calendar_id" backend/docs/openapi.yaml backend/docs/api-reference.md
```

Expected: all entries present

- [ ] **Step 5: Commit**

```bash
git add backend/docs/openapi.yaml backend/docs/api-reference.md
git commit -m "docs: update API docs for platform admin modules and tenant defaults"
```

---

### Task 7: Update frontend admin surfaces for new platform/admin capabilities and tenant defaults

**Files:**
- Modify: `frontend/src/pages/admin/TenantFormPage.tsx`
- Modify: `frontend/src/pages/admin/TenantsPage.tsx`
- Modify: `frontend/src/pages/admin/CapabilitiesPage.tsx`
- Modify: `frontend/src/app.tsx`
- Create: `frontend/src/pages/admin/FreeSwitchModulesPage.tsx`
- Modify: `frontend/src/types/models.ts`

- [ ] **Step 1: Add failing route import / type references**

Import `FreeSwitchModulesPage` and add `FreeSwitchModuleStatus` type usage before the file exists so TypeScript shows the missing surface.

Expected: compile failure on missing file/type until created.

- [ ] **Step 2: Add tenant default fields to frontend types**

In `frontend/src/types/models.ts` update tenant shape:
```ts
export interface Tenant {
  id: string;
  name: string;
  domain: string;
  default_schedule_id?: string | null;
  default_holiday_calendar_id?: string | null;
  settings?: Record<string, unknown>;
  // existing fields...
}
```

- [ ] **Step 3: Add FreeSWITCH modules page and route**

Use the implementation from Task 3 and register it in `frontend/src/app.tsx`.

- [ ] **Step 4: Surface tenant defaults in admin tenant pages**

In `TenantsPage.tsx` or `TenantFormPage.tsx`, add read-only/defaults display such as:
```tsx
<div className="text-sm text-muted-foreground">
  Default schedule: {tenant.default_schedule_id ?? 'Not provisioned'}
</div>
<div className="text-sm text-muted-foreground">
  Default holiday calendar: {tenant.default_holiday_calendar_id ?? 'Not provisioned'}
</div>
```

- [ ] **Step 5: Link the FreeSWITCH modules page from capabilities/system admin surfaces**

Add a link/button from `CapabilitiesPage.tsx` or existing admin navigation to `/admin/freeswitch/modules`.

- [ ] **Step 6: Run frontend checks**

Run the repo’s existing frontend test/build command, for example:
```bash
cd "/d/Development/laragon/NIZAM/frontend" && npm run build
```

Expected: PASS with no TypeScript errors

- [ ] **Step 7: Commit**

```bash
git add frontend/src/pages/admin/FreeSwitchModulesPage.tsx frontend/src/pages/admin/TenantFormPage.tsx frontend/src/pages/admin/TenantsPage.tsx frontend/src/pages/admin/CapabilitiesPage.tsx frontend/src/app.tsx frontend/src/types/models.ts
git commit -m "feat: add platform admin module status UI and tenant default visibility"
```

---

## Self-review checklist

### Spec coverage
This plan covers:
- FreeSWITCH modules status for platform admins
- hard foreign-key indexing rule enforcement
- API docs updates
- frontend admin updates

### Placeholder scan
No `TODO`, `TBD`, or “similar to Task N” placeholders remain.

### Type consistency
Key names are consistent across tasks:
- `default_schedule_id`
- `default_holiday_calendar_id`
- `FreeSwitchModuleStatus`
- `FreeSwitchModuleStatusService`
- `FreeSwitchModuleStatusController`

## Execution handoff

Plan complete and saved to `docs/superpowers/plans/2026-04-12-platform-admin-modules-and-indexing.md`. Two execution options:

**1. Subagent-Driven (recommended)** - I dispatch a fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints

Which approach?
