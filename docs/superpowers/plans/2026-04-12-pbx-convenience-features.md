# PBX Convenience Features Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a first-wave PBX convenience layer for Nizam covering personal call control, shared office controls, directory/discovery, and matching API/docs/frontend coverage.

**Architecture:** Build these features as additive, event/observer-driven business-phone capabilities on top of the compiled tenant manifest and dialplan compiler. The hot call path remains deterministic and compiled; configuration, provisioning, and visibility are exposed through explicit backend APIs, OpenAPI/docs, and admin frontend pages.

**Tech Stack:** Laravel, PHPUnit, OpenAPI YAML, React/TypeScript frontend, FreeSWITCH XML/dialplan compilation, tenant-scoped APIs

---

## File structure and responsibilities

### Personal call control
- `backend/app/Models/Extension.php` — add persistence fields for follow-me/DND convenience where needed
- `backend/app/Http/Controllers/Api/ExtensionFeatureController.php` — tenant-scoped API for extension-level convenience features
- `backend/app/Services/ExtensionFeatureService.php` — domain logic for follow-me, DND, and service-code behavior
- `backend/tests/Feature/Api/ExtensionFeatureApiTest.php` — API coverage
- `backend/tests/Unit/Services/ExtensionFeatureServiceTest.php` — domain logic coverage

### Shared office controls
- `backend/app/Http/Controllers/Api/OfficeFeatureController.php` — parking/pickup/intercom/paging APIs
- `backend/app/Services/OfficeFeatureService.php` — office control logic and dialplan/default target decisions
- `backend/tests/Feature/Api/OfficeFeatureApiTest.php`
- `backend/tests/Unit/Services/OfficeFeatureServiceTest.php`

### Directory / dial-by-name
- `backend/app/Http/Controllers/Api/DirectoryController.php`
- `backend/app/Services/DirectoryService.php`
- `backend/tests/Feature/Api/DirectoryApiTest.php`
- `backend/tests/Unit/Services/DirectoryServiceTest.php`

### Dialplan and manifest integration
- `backend/app/Services/DialplanCompiler.php`
- `backend/app/Services/TenantManifestBuilder.php`
- `backend/tests/Unit/Services/DialplanCompilerExtendedTest.php`
- `backend/tests/Feature/FreeswitchXmlTest.php`

### API docs
- `backend/docs/openapi.yaml`
- `backend/docs/api-reference.md`

### Frontend admin/business-phone UX
- `frontend/src/pages/admin/ExtensionDetailPage.tsx`
- `frontend/src/pages/admin/ExtensionFormPage.tsx`
- `frontend/src/pages/admin/DirectoryPage.tsx`
- `frontend/src/pages/admin/OfficeFeaturesPage.tsx`
- `frontend/src/app.tsx`
- `frontend/src/types/models.ts`

---

### Task 1: Add personal call control model and service layer

**Files:**
- Modify: `backend/app/Models/Extension.php`
- Create: `backend/app/Services/ExtensionFeatureService.php`
- Create: `backend/database/migrations/2026_04_12_140000_add_extension_feature_fields.php`
- Test: `backend/tests/Unit/Services/ExtensionFeatureServiceTest.php`

- [ ] **Step 1: Write the failing unit test for extension convenience state**

```php
<?php

namespace Tests\Unit\Services;

use App\Models\Extension;
use App\Models\Tenant;
use App\Services\ExtensionFeatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtensionFeatureServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);
    }

    public function test_it_can_enable_follow_me_and_dnd_for_an_extension(): void
    {
        $tenant = Tenant::factory()->create();
        $extension = Extension::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $service = app(ExtensionFeatureService::class);

        $updated = $service->updateFeatures($extension, [
            'follow_me_enabled' => true,
            'follow_me_destination' => '+8801712345678',
            'dnd_enabled' => true,
        ]);

        $this->assertTrue($updated->follow_me_enabled);
        $this->assertSame('+8801712345678', $updated->follow_me_destination);
        $this->assertTrue($updated->dnd_enabled);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run:
```bash
cd "/d/Development/laragon/NIZAM/backend" && "/c/laragon/bin/php/php-8.2.30-Win32-vs16-x64/php.exe" vendor/bin/phpunit tests/Unit/Services/ExtensionFeatureServiceTest.php --configuration phpunit.xml
```

Expected: FAIL because fields/service do not exist yet.

- [ ] **Step 3: Add minimal migration and model fields**

```php
Schema::table('extensions', function (Blueprint $table) {
    $table->boolean('follow_me_enabled')->default(false)->after('voicemail_enabled');
    $table->string('follow_me_destination')->nullable()->after('follow_me_enabled');
    $table->boolean('dnd_enabled')->default(false)->after('follow_me_destination');
});
```

Update `Extension::$fillable` and casts:
```php
'follow_me_enabled',
'follow_me_destination',
'dnd_enabled',
```

```php
'follow_me_enabled' => 'boolean',
'dnd_enabled' => 'boolean',
```

- [ ] **Step 4: Add minimal service implementation**

```php
<?php

namespace App\Services;

use App\Models\Extension;

class ExtensionFeatureService
{
    public function updateFeatures(Extension $extension, array $attributes): Extension
    {
        $extension->forceFill([
            'follow_me_enabled' => (bool) ($attributes['follow_me_enabled'] ?? false),
            'follow_me_destination' => $attributes['follow_me_destination'] ?? null,
            'dnd_enabled' => (bool) ($attributes['dnd_enabled'] ?? false),
        ])->save();

        return $extension->fresh();
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run same PHPUnit command as Step 2.

Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add backend/app/Models/Extension.php backend/app/Services/ExtensionFeatureService.php backend/database/migrations/2026_04_12_140000_add_extension_feature_fields.php backend/tests/Unit/Services/ExtensionFeatureServiceTest.php
git commit -m "feat: add extension convenience feature state"
```

---

### Task 2: Expose extension convenience APIs

**Files:**
- Create: `backend/app/Http/Controllers/Api/ExtensionFeatureController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Api/ExtensionFeatureApiTest.php`

- [ ] **Step 1: Write the failing feature test**

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Extension;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtensionFeatureApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);
    }

    public function test_tenant_admin_can_update_extension_features(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['role' => 'admin', 'tenant_id' => $tenant->id]);
        $extension = Extension::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/tenants/{$tenant->id}/extensions/{$extension->id}/features", [
                'follow_me_enabled' => true,
                'follow_me_destination' => '+8801712345678',
                'dnd_enabled' => true,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.follow_me_enabled', true);
        $response->assertJsonPath('data.dnd_enabled', true);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run the PHPUnit command for this file.

Expected: FAIL because route/controller do not exist.

- [ ] **Step 3: Add minimal controller and route**

Controller:
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Extension;
use App\Models\Tenant;
use App\Services\ExtensionFeatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExtensionFeatureController extends Controller
{
    public function update(Request $request, Tenant $tenant, Extension $extension, ExtensionFeatureService $service): JsonResponse
    {
        abort_unless($extension->tenant_id === $tenant->id, 404);

        $validated = $request->validate([
            'follow_me_enabled' => ['boolean'],
            'follow_me_destination' => ['nullable', 'string', 'max:255'],
            'dnd_enabled' => ['boolean'],
        ]);

        $updated = $service->updateFeatures($extension, $validated);

        return response()->json(['data' => $updated]);
    }
}
```

Route:
```php
Route::put('extensions/{extension}/features', [ExtensionFeatureController::class, 'update'])->name('extensions.features.update');
```

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Controllers/Api/ExtensionFeatureController.php backend/routes/api.php backend/tests/Feature/Api/ExtensionFeatureApiTest.php
git commit -m "feat: add extension convenience feature API"
```

---

### Task 3: Integrate personal call control into compiled dialplan/service codes

**Files:**
- Modify: `backend/app/Services/DialplanCompiler.php`
- Modify: `backend/app/Services/TenantManifestBuilder.php`
- Test: `backend/tests/Unit/Services/DialplanCompilerExtendedTest.php`
- Test: `backend/tests/Feature/FreeswitchXmlTest.php`

- [ ] **Step 1: Write failing dialplan test for convenience service routes**

```php
public function test_compiled_dialplan_includes_follow_me_and_dnd_service_routes(): void
{
    $tenant = Tenant::factory()->create(['domain' => 'pbx.example.com']);

    $xml = app(DialplanCompiler::class)->compileDialplan($tenant->domain, '*98');

    $this->assertStringContainsString('*98', $xml);
}
```

Then add stronger assertions for newly added routes:
- follow-me toggle service route
- DND on/off service routes
- call return service route

- [ ] **Step 2: Run test to verify it fails**

Run focused PHPUnit on dialplan tests.

Expected: FAIL for missing route output.

- [ ] **Step 3: Implement minimal compiled service routes**

In `DialplanCompiler`, add helpers that emit service routes using `config('telephony.bootstrap.service_codes')` plus extension feature state where relevant.

Examples:
```php
protected function compileVoicemailMainRoute(Tenant $tenant): string { /* ... */ }
protected function compileDndRoute(Tenant $tenant, string $code, bool $enabled): string { /* ... */ }
protected function compileCallReturnRoute(Tenant $tenant): string { /* ... */ }
protected function compileSendToVoicemailRoute(Tenant $tenant): string { /* ... */ }
```

Then include them in the manifest builder and direct compiler path.

- [ ] **Step 4: Run tests to verify they pass**

Run focused dialplan/XML tests.

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/DialplanCompiler.php backend/app/Services/TenantManifestBuilder.php backend/tests/Unit/Services/DialplanCompilerExtendedTest.php backend/tests/Feature/FreeswitchXmlTest.php
git commit -m "feat: compile personal convenience service routes"
```

---

### Task 4: Add shared office controls service/API (parking, pickup, intercom/paging)

**Files:**
- Create: `backend/app/Services/OfficeFeatureService.php`
- Create: `backend/app/Http/Controllers/Api/OfficeFeatureController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Unit/Services/OfficeFeatureServiceTest.php`
- Test: `backend/tests/Feature/Api/OfficeFeatureApiTest.php`

- [ ] **Step 1: Write failing unit and feature tests for office controls**

Cover a minimal API such as:
- `GET /tenants/{tenant}/office-features`
- `PUT /tenants/{tenant}/office-features`

with fields like:
```json
{
  "parking_enabled": true,
  "pickup_enabled": true,
  "paging_enabled": false,
  "intercom_enabled": false,
  "directory_enabled": true
}
```

- [ ] **Step 2: Run tests to verify they fail**

Expected: missing service/controller/routes.

- [ ] **Step 3: Add minimal model-free service + API**

Keep it additive by storing values under `tenant.settings.business_phone.office_features`.

```php
class OfficeFeatureService
{
    public function get(Tenant $tenant): array { /* merge defaults */ }
    public function update(Tenant $tenant, array $attributes): array { /* save settings */ }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/OfficeFeatureService.php backend/app/Http/Controllers/Api/OfficeFeatureController.php backend/routes/api.php backend/tests/Unit/Services/OfficeFeatureServiceTest.php backend/tests/Feature/Api/OfficeFeatureApiTest.php
git commit -m "feat: add office convenience feature API"
```

---

### Task 5: Add company directory / dial-by-name backend services and API

**Files:**
- Create: `backend/app/Services/DirectoryService.php`
- Create: `backend/app/Http/Controllers/Api/DirectoryController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Unit/Services/DirectoryServiceTest.php`
- Test: `backend/tests/Feature/Api/DirectoryApiTest.php`

- [ ] **Step 1: Write failing tests for tenant directory search**

Cover behavior such as:
- only active extensions in the tenant
- searchable by first name / last name / extension
- returns directory name + extension

- [ ] **Step 2: Run tests to verify they fail**

Expected: missing service/controller/routes.

- [ ] **Step 3: Add minimal directory service and API**

```php
class DirectoryService
{
    public function search(Tenant $tenant, ?string $query = null): Collection
    {
        return $tenant->extensions()
            ->where('is_active', true)
            ->when($query, function ($builder) use ($query) {
                $builder->where(function ($q) use ($query) {
                    $q->where('directory_first_name', 'like', "%{$query}%")
                      ->orWhere('directory_last_name', 'like', "%{$query}%")
                      ->orWhere('extension', 'like', "%{$query}%");
                });
            })
            ->get();
    }
}
```

Add route such as:
```php
Route::get('directory', [DirectoryController::class, 'index'])->name('directory.index');
```

- [ ] **Step 4: Run tests to verify they pass**

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/DirectoryService.php backend/app/Http/Controllers/Api/DirectoryController.php backend/routes/api.php backend/tests/Unit/Services/DirectoryServiceTest.php backend/tests/Feature/Api/DirectoryApiTest.php
git commit -m "feat: add company directory API"
```

---

### Task 6: Update OpenAPI and API docs for convenience features

**Files:**
- Modify: `backend/docs/openapi.yaml`
- Modify: `backend/docs/api-reference.md`

- [ ] **Step 1: Write a failing docs checklist for missing convenience endpoints**

Confirm docs need entries for:
- extension feature update endpoint
n- office features endpoint(s)
- directory endpoint
- service-code/default business-phone behavior notes

Expected: missing docs entries identified.

- [ ] **Step 2: Update OpenAPI**

Add paths/schemas for:
- extension feature update
- office feature get/update
- directory search/list

Add schemas like:
```yaml
    ExtensionFeatures:
      type: object
      properties:
        follow_me_enabled: { type: boolean }
        follow_me_destination: { type: string, nullable: true }
        dnd_enabled: { type: boolean }
```

```yaml
    OfficeFeatures:
      type: object
      properties:
        parking_enabled: { type: boolean }
        pickup_enabled: { type: boolean }
        paging_enabled: { type: boolean }
        intercom_enabled: { type: boolean }
        directory_enabled: { type: boolean }
```

- [ ] **Step 3: Update prose API docs**

Document the endpoint purpose and current behavior accurately.

- [ ] **Step 4: Validate docs with grep sanity check**

Run:
```bash
grep -n "ExtensionFeatures\|OfficeFeatures\|/directory\|office-features" backend/docs/openapi.yaml backend/docs/api-reference.md
```

Expected: all convenience APIs documented.

- [ ] **Step 5: Commit**

```bash
git add backend/docs/openapi.yaml backend/docs/api-reference.md
git commit -m "docs: add PBX convenience feature API docs"
```

---

### Task 7: Add frontend admin pages for convenience features and directory

**Files:**
- Create: `frontend/src/pages/admin/DirectoryPage.tsx`
- Create: `frontend/src/pages/admin/OfficeFeaturesPage.tsx`
- Modify: `frontend/src/pages/admin/ExtensionDetailPage.tsx`
- Modify: `frontend/src/app.tsx`
- Modify: `frontend/src/layouts/SuperadminLayout.tsx`
- Modify: `frontend/src/types/models.ts`

- [ ] **Step 1: Add DTO types**

In `frontend/src/types/models.ts` add:
```ts
export interface ExtensionFeatures {
  follow_me_enabled: boolean;
  follow_me_destination?: string | null;
  dnd_enabled: boolean;
}

export interface OfficeFeatures {
  parking_enabled: boolean;
  pickup_enabled: boolean;
  paging_enabled: boolean;
  intercom_enabled: boolean;
  directory_enabled: boolean;
}
```

- [ ] **Step 2: Create Directory admin page**

Build a simple tenant-scoped searchable directory page consuming `GET /api/v1/tenants/{tenant}/directory`.

- [ ] **Step 3: Create Office Features admin page**

Build a simple settings page consuming office-features GET/PUT.

- [ ] **Step 4: Surface extension convenience state in extension detail page**

Add read-only badges/summary for:
- follow-me enabled/destination
- DND enabled

- [ ] **Step 5: Register routes and navigation**

Add routes like:
```tsx
<Route path="directory" element={<DirectoryPage />} />
<Route path="office-features" element={<OfficeFeaturesPage />} />
```

Add sidebar/nav entries in the appropriate admin sections.

- [ ] **Step 6: Run frontend build**

Run:
```bash
cd "/d/Development/laragon/NIZAM/frontend" && npm run build
```

Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add frontend/src/pages/admin/DirectoryPage.tsx frontend/src/pages/admin/OfficeFeaturesPage.tsx frontend/src/pages/admin/ExtensionDetailPage.tsx frontend/src/app.tsx frontend/src/layouts/SuperadminLayout.tsx frontend/src/types/models.ts
git commit -m "feat: add PBX convenience admin UI"
```

---

## Self-review checklist

### Spec coverage
This plan covers:
- personal call control (follow-me, DND, voicemail-main/service routes, call return)
- shared office controls (parking, pickup, paging/intercom via office-feature surface)
- company directory / dial-by-name foundation
- OpenAPI/docs updates
- frontend/admin surfaces

### Placeholder scan
No `TODO`, `TBD`, or “similar to Task N” placeholders remain.

### Type consistency
Key names are consistent across tasks:
- `follow_me_enabled`
- `follow_me_destination`
- `dnd_enabled`
- `OfficeFeatures`
- `DirectoryService`
- `ExtensionFeatureService`

## Execution handoff

Plan complete and saved to `docs/superpowers/plans/2026-04-12-pbx-convenience-features.md`. Two execution options:

**1. Subagent-Driven (recommended)** - I dispatch a fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints

Which approach?
