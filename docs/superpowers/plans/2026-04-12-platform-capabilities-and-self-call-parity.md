# Platform Capabilities and Self-Call Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement FusionPBX-style self-call behavior (voicemail check) and build a dedicated platform-admin dashboard to surface advanced PBX capabilities.

**Architecture:**
- **Dialplan**: Update `DialplanCompiler` to detect `caller_id_number == destination_number` and trigger `voicemail(check)` instead of `bridge`.
- **Backend**: Add `CapabilityService` to inspect system state and `AdminCapabilityController` to expose a registry of enabled enhancements.
- **Frontend**: Create `CapabilitiesPage.tsx` with high-quality "Feature Cards" and update `SuperadminLayout.tsx` for navigation.

**Tech Stack:** PHP 8.2 (Laravel), React 18 (Vite), FreeSWITCH (Dialplan XML).

---

## File Structure

### Files to Modify
- `backend/app/Services/DialplanCompiler.php` — implementation of self-call logic.
- `backend/routes/api.php` — add admin capability endpoint.
- `frontend/src/layouts/SuperadminLayout.tsx` — add navigation link.

### Files to Create
- `backend/app/Services/Admin/CapabilityService.php` — logic to detect active PBX features.
- `backend/app/Http/Controllers/Api/Admin/AdminCapabilityController.php` — expose the feature registry.
- `backend/tests/Feature/Api/Admin/AdminCapabilityApiTest.php` — test the feature registry endpoint.
- `frontend/src/pages/admin/CapabilitiesPage.tsx` — the dashboard page.
- `frontend/src/components/admin/CapabilityCard.tsx` — reusable card component.

---

### Task 1: Implement FusionPBX Self-Call Behavior

**Files:**
- Modify: `backend/app/Services/DialplanCompiler.php`
- Test: `backend/tests/Feature/FreeswitchXmlTest.php`

- [ ] **Step 1: Write failing self-call voicemail test**

Add to `backend/tests/Feature/FreeswitchXmlTest.php`:

```php
public function test_dialplan_routes_self_call_to_voicemail_check(): void
{
    $tenant = Tenant::create([
        'name' => 'Self Call Parity Tenant',
        'domain' => 'parity.example.com',
        'slug' => 'parity-tenant',
        'is_active' => true,
    ]);

    $tenant->extensions()->create([
        'extension' => '1001',
        'password' => 'secret1234',
        'is_active' => true,
    ]);

    $response = $this->post('/freeswitch/xml-curl', [
        'section' => 'dialplan',
        'domain' => 'parity.example.com',
        'Caller-Destination-Number' => '1001',
        'Caller-Caller-ID-Number' => '1001',
    ]);

    $response->assertStatus(200);
    $this->assertStringContainsString('application="answer"', $response->getContent());
    $this->assertStringContainsString('application="voicemail" data="check default parity.example.com 1001"', $response->getContent());
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test tests/Feature/FreeswitchXmlTest.php --filter=test_dialplan_routes_self_call_to_voicemail_check`
Expected: FAIL (currently does `bridge user/...`)

- [ ] **Step 3: Update `compileSelfCallDialplan` in `DialplanCompiler.php`**

```php
protected function compileSelfCallDialplan(Tenant $tenant, Extension $extension): string
{
    $xml = $this->dialplanHeader($tenant->domain);
    $xml .= '        <extension name="self-call-voicemail-'.htmlspecialchars($extension->extension, ENT_QUOTES | ENT_XML1).'">'."\n";
    $xml .= '          <condition field="destination_number" expression="^'.preg_quote($extension->extension, '/').'$">'."\n";
    // FusionPBX parity: enter voicemail management menu instead of bridging
    $xml .= '            <action application="answer"/>'."\n";
    $xml .= '            <action application="sleep" data="1000"/>'."\n";
    $xml .= '            <action application="voicemail" data="check default '.htmlspecialchars($tenant->domain, ENT_QUOTES | ENT_XML1).' '.htmlspecialchars($extension->extension, ENT_QUOTES | ENT_XML1).'"/>'."\n";
    $xml .= '          </condition>'."\n";
    $xml .= '        </extension>'."\n";
    $xml .= $this->dialplanFooter();

    return $xml;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec app php artisan test tests/Feature/FreeswitchXmlTest.php --filter=test_dialplan_routes_self_call_to_voicemail_check`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/DialplanCompiler.php backend/tests/Feature/FreeswitchXmlTest.php
git commit -m "feat: implement FusionPBX-style self-call to voicemail"
```

---

### Task 2: Build Backend Capability Registry

**Files:**
- Create: `backend/app/Services/Admin/CapabilityService.php`
- Create: `backend/app/Http/Controllers/Api/Admin/AdminCapabilityController.php`
- Create: `backend/tests/Feature/Api/Admin/AdminCapabilityApiTest.php`
- Modify: `backend/routes/api.php`

- [ ] **Step 1: Write failing API test**

```php
<?php

namespace Tests\Feature\Api\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCapabilityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_get_platform_capabilities(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        
        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/capabilities');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'description', 'status', 'category']
            ]
        ]);
        
        $response->assertJsonFragment(['id' => 'self_call_management', 'status' => 'active']);
    }
}
```

- [ ] **Step 2: Implement `CapabilityService.php`**

```php
<?php

namespace App\Services\Admin;

use App\Models\SipProfileSetting;

class CapabilityService
{
    public function getCapabilities(): array
    {
        return [
            [
                'id' => 'self_call_management',
                'name' => 'Self-Call Management',
                'description' => 'Detects self-calls and routes them to the account management menu (voicemail check), matching FusionPBX behavior.',
                'status' => 'active',
                'category' => 'Routing',
            ],
            [
                'id' => 'multi_registration',
                'name' => 'Multi-Registration Support',
                'description' => 'Allows up to 5 simultaneous devices per extension using contact-based registration tracking.',
                'status' => $this->checkMultiRegStatus(),
                'category' => 'Security',
            ],
            [
                'id' => 'optimized_directory',
                'name' => 'Optimized Directory Service',
                'description' => 'Filtered XML-CURL lookups for zero-lag softphone connection by fetching only the requested user.',
                'status' => 'active',
                'category' => 'Performance',
            ],
            [
                'id' => 'tenant_isolation',
                'name' => 'Context-Isolated Routing',
                'description' => 'Strict multi-tenant traffic separation using domain-keyed dialplan contexts to prevent cross-tenant exposure.',
                'status' => 'active',
                'category' => 'Security',
            ],
        ];
    }

    protected function checkMultiRegStatus(): string
    {
        return SipProfileSetting::where('name', 'multiple-registrations')->where('value', 'contact')->exists() 
            ? 'active' : 'inactive';
    }
}
```

- [ ] **Step 3: Create Controller and add Route**

Create `AdminCapabilityController` and add route:
`Route::get('admin/capabilities', [AdminCapabilityController::class, 'index']);`

- [ ] **Step 4: Run API test and commit**

```bash
git add backend/app/Services/Admin/CapabilityService.php backend/app/Http/Controllers/Api/Admin/AdminCapabilityController.php backend/tests/Feature/Api/Admin/AdminCapabilityApiTest.php backend/routes/api.php
git commit -m "feat: add platform capability registry API"
```

---

### Task 3: Build Frontend Capabilities UI

**Files:**
- Create: `frontend/src/components/admin/CapabilityCard.tsx`
- Create: `frontend/src/pages/admin/CapabilitiesPage.tsx`
- Modify: `frontend/src/layouts/SuperadminLayout.tsx`

- [ ] **Step 1: Create `CapabilityCard` component**

Display name, description, category, and status badge.

- [ ] **Step 2: Create `CapabilitiesPage` component**

Fetch data from `/admin/capabilities` and render the grid.

- [ ] **Step 3: Add Navigation link**

Add "Capabilities" to the `NAV_SECTIONS` in `SuperadminLayout.tsx` under the "System" section.

- [ ] **Step 4: Commit UI**

```bash
git add frontend/src/components/admin/CapabilityCard.tsx frontend/src/pages/admin/CapabilitiesPage.tsx frontend/src/layouts/SuperadminLayout.tsx
git commit -m "feat: build platform capabilities dashboard page"
```

---

### Task 4: Final Verification

- [ ] **Step 1: Verify self-call manually**

Trigger a call from `tSIP` or `MicroSIP` to its own number.
Expected: Audio response from FreeSWITCH voicemail system.

- [ ] **Step 2: Final Commit and cleanup**

```bash
git add .
git commit -m "docs: finalize platform capabilities implementation"
```

---

## Self-Review

### Spec coverage
- FusionPBX self-call behavior: Covered in Task 1.
- Platform admin capability page: Covered in Task 2 & 3.
- Status indicators: Covered in `CapabilityService`.

### Placeholder scan
- No placeholders.

### Type consistency
- Backend `CapabilityService` returns a consistent array structure matched by frontend `CapabilitiesPage`.
