# UI Inventory + Information Architecture Map

Generated from repository state on 2026-03-02 using `php artisan route:list --json`, `routes/web.php`, module route files, Blade views, and `UiController` navigation config.

## 1) Ground Truth Inventory

### 1.1 Web routes (all)

| Method | URI | Name | Auth/Access |
|---|---|---|---|
| GET | `/` | — | public |
| GET | `/login` | `login` | guest-only |
| POST | `/login` | `login.store` | guest-only |
| POST | `/logout` | `logout` | auth |
| POST | `/freeswitch/xml-curl` | `freeswitch.xml-curl` | public (non-UI system endpoint) |
| GET | `/provision/{macAddress}` | `provision` | public (device provisioning endpoint) |
| GET | `/ui/dashboard/{tenant?}` | `ui.dashboard` | auth |
| GET | `/ui/system-health/{tenant?}` | `ui.health` | auth |
| GET | `/ui/tenants/{tenant}/extensions` | `ui.extensions` | auth + tenant.access |
| POST | `/ui/tenants/{tenant}/extensions` | `ui.extensions.store` | auth + tenant.access (HTMX action) |
| PUT | `/ui/tenants/{tenant}/extensions/{extension}` | `ui.extensions.update` | auth + tenant.access (HTMX action) |
| DELETE | `/ui/tenants/{tenant}/extensions/{extension}` | `ui.extensions.destroy` | auth + tenant.access (HTMX action) |
| GET | `/ui/modules/{tenant?}` | `ui.modules` | auth |
| POST | `/ui/modules/{moduleName}/toggle` | `ui.modules.toggle` | auth (admin-guarded in controller) |
| GET | `/sanctum/csrf-cookie` | `sanctum.csrf-cookie` | framework |

### 1.2 UI pages currently accessible in browser (GET pages)

- `/` (welcome entry page)
- `/login` (session auth form)
- `/ui/dashboard/{tenant?}`
- `/ui/system-health/{tenant?}`
- `/ui/tenants/{tenant}/extensions`
- `/ui/modules/{tenant?}`

### 1.3 Blade views inventory

- `resources/views/welcome.blade.php`
- `resources/views/auth/login.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/auth.blade.php`
- `resources/views/components/layouts/app.blade.php`
- `resources/views/components/layouts/auth.blade.php`
- `resources/views/ui/dashboard/index.blade.php`
- `resources/views/ui/dashboard/health.blade.php`
- `resources/views/ui/extensions/index.blade.php`
- `resources/views/ui/extensions/_table.blade.php`
- `resources/views/ui/modules/index.blade.php`
- `resources/views/ui/modules/_row.blade.php`
- UI primitives: `resources/views/components/ui/{button,card,badge,input,modal,table}.blade.php`

### 1.4 Navigation link inventory (actual rendered links)

From `UiController::uiContext()` + `layouts/app.blade.php`:

Primary nav (module-filtered):
- Dashboard → `route('ui.dashboard', ['tenant' => $tenant])`
- Extensions → `route('ui.extensions', ['tenant' => $tenant])` (shown when `pbx-routing` enabled)
- System Health → `route('ui.health', ['tenant' => $tenant])`
- Modules → `route('ui.modules')`

Platform nav (admin only):
- Admin Dashboard → `/api/v1/admin/dashboard`

Other page links:
- Welcome CTA: Open Dashboard → `route('ui.dashboard')`

### 1.5 Module route files inventory

- `modules/PbxRouting/routes/api.php`
- `modules/PbxContactCenter/routes/api.php`
- `modules/PbxAutomation/routes/api.php`
- `modules/PbxAnalytics/routes/api.php`
- `modules/PbxProvisioning/routes/api.php`
- `modules/PbxMediaPolicy/routes/api.php`

Current module route files are API-only (`/api/v1/...`), no module-specific web route files exist yet.

## 2) Routes → Domain Categories

### Platform scope (super admin)

- `/ui/modules/{tenant?}` (module lifecycle panel)
- `/ui/modules/{moduleName}/toggle` (admin-only action)
- `/api/v1/admin/dashboard` (linked from platform nav)

### Tenant scope

- `/ui/dashboard/{tenant?}`
- `/ui/system-health/{tenant?}`
- `/ui/tenants/{tenant}/extensions` (+ create/update/delete HTMX actions)

### Auth/session scope

- `/login` (GET/POST)
- `/logout` (POST)

### Floating/out-of-band endpoints (not part of UI IA)

- `/freeswitch/xml-curl` (telephony integration)
- `/provision/{macAddress}` (device provisioning)

## 3) Page Type Classification

| Page | Type |
|---|---|
| `/` welcome | Entry/launcher page |
| `/login` | Form page |
| `/ui/dashboard/{tenant?}` | Dashboard page |
| `/ui/system-health/{tenant?}` | System health page |
| `/ui/tenants/{tenant}/extensions` | List page + inline form/actions (HTMX partial table refresh) |
| `/ui/modules/{tenant?}` | List/status page |

No current browser page appears to be a pure detail page.

## 4) Current IA Page Tree (as-implemented)

```text
Public
├── /
└── /login

Authenticated UI
├── Dashboard
├── Extensions
├── System Health
└── Modules
```

## 5) Missing Core Pages Report (backend > UI gap)

Based on available backend modules/routes/models, these high-value UI surfaces are missing or API-only:

- Tenant onboarding/setup checklist
- Fraud alert panel
- Codec policy page (codec metrics exists via API)
- Gateway management page (API exists)
- Recording detail inspector (API exists)
- Event replay viewer (API exists)
- Agent activity timeline / wallboard UI (API exists)
- Node health per FreeSWITCH node (system health is currently aggregate-oriented)
- Routing UI pages for DIDs / Ring Groups / IVR / Time Conditions (API exists)
- Provisioning UI pages for device profiles (API exists)

## 6) Module UI Coverage Matrix

| Module | Has browser UI page? | Full CRUD in browser UI? | Dashboard surface? | Live view in browser UI? |
|---|---|---|---|---|
| Routing | Partial (Extensions only) | Partial | No dedicated | No |
| Contact Center | No (API-only) | N/A in browser | No dedicated | No (API supports realtime metrics) |
| Automation | No (API-only) | N/A in browser | No dedicated | No (API event stream exists) |
| Analytics | No (API-only) | N/A in browser | No dedicated | No |
| Provisioning | No (API-only) | N/A in browser | No | No |
| Media Policy (Gateways/Codec) | No (API-only) | N/A in browser | No | No |

## 7) Redundant/Drift Report

Potential redundancies/drift:

1. **Entry-point drift**: `/` welcome page acts as launcher while `/login` is the real auth entry.
2. **Navigation drift**: Admin link points to API JSON endpoint (`/api/v1/admin/dashboard`) instead of a browser UI page.
3. **Coverage drift**: Most modules expose mature APIs but not corresponding browser pages.

## 8) Proposed Unified Navigation Tree (IA-first)

```text
Platform (Admin)
├── Overview
├── Tenants
│   ├── Tenant Overview
│   ├── Tenant Extensions
│   ├── Tenant Routing
│   ├── Tenant Contact Center
│   └── Tenant Settings
├── Nodes
├── Modules
└── System Health

Tenant
├── Dashboard
├── Extensions
├── Devices
├── Routing
│   ├── DIDs
│   ├── Ring Groups
│   ├── IVR
│   └── Time Conditions
├── Contact Center
│   ├── Queues
│   ├── Agents
│   └── Wallboard
├── Automation
│   ├── Webhooks
│   ├── Event Replay
│   └── Event Logs
├── Analytics
│   ├── Recordings
│   ├── SLA
│   └── Call Volume
└── Settings
```

## 9) UX Narrative Sanity (tenant admin)

Current UI cannot yet provide a complete linear flow for:
1) start, 2) configure first call path, 3) test, 4) monitor, 5) troubleshoot.

This indicates IA and surface-coverage work remains, not a styling issue.
