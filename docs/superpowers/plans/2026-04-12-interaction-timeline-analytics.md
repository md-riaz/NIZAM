# Interaction Timeline Analytics Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a detailed interaction timeline / advanced call analytics view for Nizam that turns raw call sessions, event logs, and delivery attempts into a business-readable interaction journey UI.

**Architecture:** Keep raw CDRs as the historical billing/compliance record, but add a dedicated interaction analytics layer on top of `CallSession`, `CallEventLog`, `CallTraceEvent`, `CallDeliveryAttempt`, and `PushNotificationLog`. The backend should expose a read-optimized interaction API, and the frontend should render a timeline, details table, summary panel, and analytics metadata for supervisors and business users.

**Tech Stack:** Laravel, PHPUnit, existing call/session/event models, React/TypeScript frontend, OpenAPI YAML, existing admin pages and query hooks

---

## File structure and responsibilities

### Backend analytics read layer
- `backend/app/Services/Interaction/InteractionOverviewService.php` — aggregate one interaction/session into a UI-friendly view model
- `backend/app/Services/Interaction/InteractionTimelineBuilder.php` — convert trace events, call events, delivery attempts, and push logs into ordered timeline segments
- `backend/app/Http/Controllers/Api/InteractionController.php` — organization-scoped interaction overview endpoint(s)
- `backend/tests/Unit/Services/Interaction/InteractionTimelineBuilderTest.php`
- `backend/tests/Unit/Services/Interaction/InteractionOverviewServiceTest.php`
- `backend/tests/Feature/Api/InteractionApiTest.php`

### Frontend interaction analytics UI
- `frontend/src/pages/admin/InteractionDetailPage.tsx` — full interaction timeline view
- `frontend/src/pages/admin/CdrsPage.tsx` — link into interaction detail view
- `frontend/src/types/models.ts` — DTOs for interaction overview/timeline
- `frontend/src/app.tsx` — route registration

### Docs
- `backend/docs/openapi.yaml`
- `backend/docs/api-reference.md`

---

### Task 1: Build interaction timeline aggregation service

**Files:**
- Create: `backend/app/Services/Interaction/InteractionTimelineBuilder.php`
- Test: `backend/tests/Unit/Services/Interaction/InteractionTimelineBuilderTest.php`

- [ ] **Step 1: Write the failing timeline builder test**

```php
<?php

namespace Tests\Unit\Services\Interaction;

use App\Services\Interaction\InteractionTimelineBuilder;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InteractionTimelineBuilderTest extends TestCase
{
    public function test_it_builds_ordered_timeline_segments_from_mixed_events(): void
    {
        $builder = new InteractionTimelineBuilder;

        $timeline = $builder->build([
            ['type' => 'call.started', 'occurred_at' => Carbon::parse('2026-04-12 10:00:00'), 'details' => ['label' => 'Call started']],
            ['type' => 'dialplan.entered', 'occurred_at' => Carbon::parse('2026-04-12 10:00:05'), 'details' => ['label' => 'Dial plan initiated']],
            ['type' => 'call.connected', 'occurred_at' => Carbon::parse('2026-04-12 10:01:00'), 'details' => ['label' => 'Connected']],
        ]);

        $this->assertCount(3, $timeline);
        $this->assertSame('call.started', $timeline[0]['type']);
        $this->assertSame('dialplan.entered', $timeline[1]['type']);
        $this->assertSame('call.connected', $timeline[2]['type']);
        $this->assertSame('00m 55s', $timeline[1]['duration_label']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run:
```bash
cd "/d/Development/laragon/NIZAM/backend" && "/c/laragon/bin/php/php-8.2.30-Win32-vs16-x64/php.exe" vendor/bin/phpunit tests/Unit/Services/Interaction/InteractionTimelineBuilderTest.php --configuration phpunit.xml
```

Expected: FAIL because the builder does not exist.

- [ ] **Step 3: Write minimal timeline builder**

```php
<?php

namespace App\Services\Interaction;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class InteractionTimelineBuilder
{
    /**
     * @param  array<int, array{type: string, occurred_at: Carbon, details: array<string, mixed>}>  $events
     * @return array<int, array<string, mixed>>
     */
    public function build(array $events): array
    {
        $sorted = collect($events)
            ->sortBy(fn (array $event) => $event['occurred_at']->getTimestamp())
            ->values();

        return $sorted->map(function (array $event, int $index) use ($sorted): array {
            $next = $sorted->get($index + 1);
            $durationSeconds = $next
                ? max(0, $event['occurred_at']->diffInSeconds($next['occurred_at'], false))
                : 0;

            return [
                'type' => $event['type'],
                'occurred_at' => $event['occurred_at']->toIso8601String(),
                'details' => $event['details'],
                'duration_seconds' => $durationSeconds,
                'duration_label' => sprintf('%02dm %02ds', intdiv($durationSeconds, 60), $durationSeconds % 60),
            ];
        })->all();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/Interaction/InteractionTimelineBuilder.php backend/tests/Unit/Services/Interaction/InteractionTimelineBuilderTest.php
git commit -m "feat: add interaction timeline builder"
```

---

### Task 2: Build interaction overview service from current models

**Files:**
- Create: `backend/app/Services/Interaction/InteractionOverviewService.php`
- Test: `backend/tests/Unit/Services/Interaction/InteractionOverviewServiceTest.php`

- [ ] **Step 1: Write the failing overview service test**

```php
<?php

namespace Tests\Unit\Services\Interaction;

use App\Models\CallSession;
use App\Models\Organization;
use App\Services\Interaction\InteractionOverviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InteractionOverviewServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_business_readable_interaction_overview(): void
    {
        $organization = Organization::factory()->create();
        $session = CallSession::factory()->create([
            'organization_id' => $organization->id,
            'call_uuid' => 'call-123',
        ]);

        $service = app(InteractionOverviewService::class);
        $overview = $service->build($organization, $session);

        $this->assertSame('call-123', $overview['call_uuid']);
        $this->assertArrayHasKey('summary', $overview);
        $this->assertArrayHasKey('timeline', $overview);
        $this->assertArrayHasKey('delivery_attempts', $overview);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Expected: FAIL because service is missing.

- [ ] **Step 3: Write minimal overview service**

Use existing data sources already in the codebase:
- `CallSession`
- `CallTraceEvent`
- `CallDeliveryAttempt`
- `PushNotificationLog`
- `CallTraceAnalyzer` if helpful for summary reuse

Example structure:
```php
<?php

namespace App\Services\Interaction;

use App\Models\CallSession;
use App\Models\Organization;
use App\Services\Call\CallTraceAnalyzer;

class InteractionOverviewService
{
    public function __construct(
        protected InteractionTimelineBuilder $timelineBuilder,
        protected CallTraceAnalyzer $traceAnalyzer,
    ) {}

    public function build(Organization $organization, CallSession $session): array
    {
        abort_unless($session->organization_id === $organization->id, 404);

        $session->load([
            'traceEvents',
            'deliveryAttempts.endpointBinding',
            'winningDeliveryAttempt.endpointBinding',
            'pushNotificationLogs.endpointBinding',
        ]);

        $analysis = $this->traceAnalyzer->analyze($session);

        return [
            'call_uuid' => $session->call_uuid,
            'state' => $session->state,
            'started_at' => optional($session->started_at)?->toIso8601String(),
            'ended_at' => optional($session->ended_at)?->toIso8601String(),
            'summary' => $analysis['summary'] ?? [],
            'timeline' => $analysis['timeline'] ?? [],
            'delivery_attempts' => $analysis['delivery_attempts'] ?? [],
            'push_notification_logs' => $analysis['push_notification_logs'] ?? [],
            'winning_attempt' => $analysis['winning_attempt'] ?? null,
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/Interaction/InteractionOverviewService.php backend/tests/Unit/Services/Interaction/InteractionOverviewServiceTest.php
git commit -m "feat: add interaction overview service"
```

---

### Task 3: Expose interaction analytics API

**Files:**
- Create: `backend/app/Http/Controllers/Api/InteractionController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Api/InteractionApiTest.php`

- [ ] **Step 1: Write the failing feature test**

```php
<?php

namespace Tests\Feature\Api;

use App\Models\CallSession;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InteractionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_admin_can_view_interaction_overview(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create(['role' => 'admin', 'organization_id' => $organization->id]);
        $session = CallSession::factory()->create(['organization_id' => $organization->id]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/organizations/{$organization->id}/interactions/{$session->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'call_uuid',
                'summary',
                'timeline',
                'delivery_attempts',
            ],
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Expected: route/controller missing.

- [ ] **Step 3: Add minimal controller and route**

Controller:
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallSession;
use App\Models\Organization;
use App\Services\Interaction\InteractionOverviewService;
use Illuminate\Http\JsonResponse;

class InteractionController extends Controller
{
    public function show(Organization $organization, CallSession $callSession, InteractionOverviewService $service): JsonResponse
    {
        if ($callSession->organization_id !== $organization->id) {
            return response()->json(['message' => 'Interaction not found.'], 404);
        }

        return response()->json([
            'data' => $service->build($organization, $callSession),
        ]);
    }
}
```

Route:
```php
Route::get('interactions/{callSession}', [InteractionController::class, 'show'])->name('interactions.show');
```

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Controllers/Api/InteractionController.php backend/routes/api.php backend/tests/Feature/Api/InteractionApiTest.php
git commit -m "feat: add interaction overview API"
```

---

### Task 4: Add interaction detail frontend page

**Files:**
- Create: `frontend/src/pages/admin/InteractionDetailPage.tsx`
- Modify: `frontend/src/app.tsx`
- Modify: `frontend/src/types/models.ts`

- [ ] **Step 1: Add DTO types in frontend**

In `frontend/src/types/models.ts` add:
```ts
export interface InteractionTimelineItem {
  type: string;
  occurred_at: string;
  duration_seconds: number;
  duration_label: string;
  details: Record<string, unknown>;
}

export interface InteractionOverview {
  call_uuid: string;
  state?: string | null;
  started_at?: string | null;
  ended_at?: string | null;
  summary: Record<string, unknown>;
  timeline: InteractionTimelineItem[];
  delivery_attempts: unknown[];
  push_notification_logs: unknown[];
  winning_attempt?: unknown;
}
```

- [ ] **Step 2: Create the page**

Build a page that includes:
- interaction header
- summary card
- timeline strip/list
- details table
- sections for delivery attempts and push logs

Minimal page shape:
```tsx
import { useQuery } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import api from '@/lib/api';
import { useOrganization } from '@/context/OrganizationContext';
import type { InteractionOverview } from '@/types/models';

export default function InteractionDetailPage() {
  const { id } = useParams<{ id: string }>();
  const { organizationApiPrefix, activeOrganization } = useOrganization();

  const { data, isLoading } = useQuery({
    queryKey: ['interaction', activeOrganization?.id, id],
    queryFn: async () => {
      const res = await api.get<{ data: InteractionOverview }>(`${organizationApiPrefix}/interactions/${id}`);
      return res.data.data;
    },
    enabled: !!activeOrganization && !!id,
  });

  // render summary + timeline + detail table
}
```

- [ ] **Step 3: Register route**

In `frontend/src/app.tsx` add:
```tsx
<Route path="calls/:id/interaction" element={<InteractionDetailPage />} />
```

- [ ] **Step 4: Run frontend build**

Run:
```bash
cd "/d/Development/laragon/NIZAM/frontend" && npm run build
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add frontend/src/pages/admin/InteractionDetailPage.tsx frontend/src/app.tsx frontend/src/types/models.ts
git commit -m "feat: add interaction detail analytics page"
```

---

### Task 5: Link CDR page into interaction detail view

**Files:**
- Modify: `frontend/src/pages/admin/CdrsPage.tsx`

- [ ] **Step 1: Add a failing UI expectation in your working notes**

The current CDRs page shows flat rows only and has no route to richer interaction detail.

Expected gap: no interaction CTA.

- [ ] **Step 2: Add minimal link/button per row**

Example:
```tsx
<Button asChild variant="ghost" size="sm">
  <Link to={`/admin/calls/${cdr.id}/interaction`}>View interaction</Link>
</Button>
```

If CDR `id` is not the right session key, wire it through the available call/session relationship instead of inventing a fake mapping.

- [ ] **Step 3: Run frontend build**

Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add frontend/src/pages/admin/CdrsPage.tsx
git commit -m "feat: link CDRs to interaction analytics view"
```

---

### Task 6: Document interaction analytics API and UI

**Files:**
- Modify: `backend/docs/openapi.yaml`
- Modify: `backend/docs/api-reference.md`

- [ ] **Step 1: Add failing docs checklist**

Confirm missing docs for:
- `GET /api/v1/organizations/{organizationId}/interactions/{callSessionId}`
- interaction overview response schema

Expected: missing docs entries.

- [ ] **Step 2: Update OpenAPI**

Add path:
```yaml
  /organizations/{organizationId}/interactions/{callSessionId}:
    get:
      tags: [Calls]
      summary: Get detailed interaction overview
```

Add schema:
```yaml
    InteractionOverview:
      type: object
      properties:
        call_uuid: { type: string }
        state: { type: string, nullable: true }
        started_at: { type: string, format: date-time, nullable: true }
        ended_at: { type: string, format: date-time, nullable: true }
        summary: { type: object }
        timeline:
          type: array
          items:
            $ref: '#/components/schemas/InteractionTimelineItem'
```

- [ ] **Step 3: Update prose API docs**

Describe the interaction view as an analytics/read model distinct from flat CDRs.

- [ ] **Step 4: Validate docs with grep**

Run:
```bash
grep -n "InteractionOverview\|interactions/{callSessionId}\|InteractionTimelineItem" backend/docs/openapi.yaml backend/docs/api-reference.md
```

Expected: all entries present.

- [ ] **Step 5: Commit**

```bash
git add backend/docs/openapi.yaml backend/docs/api-reference.md
git commit -m "docs: add interaction analytics API documentation"
```

---

## Self-review checklist

### Spec coverage
This plan covers:
- backend interaction analytics read layer
- organization-scoped interaction API
- frontend interaction detail view
- CDR linkage into the richer interaction view
- OpenAPI and prose docs

### Placeholder scan
No `TODO`, `TBD`, or “similar to Task N” placeholders remain.

### Type consistency
Key names are consistent across tasks:
- `InteractionOverviewService`
- `InteractionTimelineBuilder`
- `InteractionController`
- `InteractionOverview`
- `InteractionTimelineItem`

## Execution handoff

Plan complete and saved to `docs/superpowers/plans/2026-04-12-interaction-timeline-analytics.md`. Two execution options:

**1. Subagent-Driven (recommended)** - I dispatch a fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints

Which approach?
