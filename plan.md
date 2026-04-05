# Frontend React API Implementation Plan

## Objective

Expand the React admin application so it covers the major existing backend API capabilities that are already stable and operationally important, while deliberately deferring call-flow authoring features that should eventually live inside a graph-based or node-based call control builder.

This plan is focused on:
- improving practical admin usability
- closing the largest backend/frontend coverage gaps
- prioritizing operational and tenant-management workflows first
- avoiding wasted effort on CRUD pages that will later be replaced by a better call-flow editor model

## Explicit Scope Decision

The following backend areas should **not** be built now as standalone CRUD-heavy menu pages unless they are needed in a minimal supporting role:
- IVRs
- time conditions
- flows
- queues
- related call-flow routing primitives that will be better represented as action blocks / nodes in a future visual call-flow builder

Instead, these should be treated as:
- future platform capabilities
- backend-ready APIs that remain available
- candidates for integration into a graph-based call control experience later

If any temporary UI is needed before the builder exists, it should be minimal, internal, and intentionally transitional.

## Current Frontend State

The mounted React admin app currently exposes these main routes:
- `/login`
- `/admin`
- `/admin/tenants`
- `/admin/users`
- `/admin/extensions`
- `/admin/ring-groups`
- `/admin/dids`
- `/admin/dids/create`
- `/admin/dids/:id/edit`
- `/admin/gateways`
- `/admin/gateways/create`
- `/admin/gateways/:id/edit`
- `/admin/cdrs`
- `/admin/logs`
- `/admin/system-logs`
- `/admin/settings`
- `/admin/sip-status`

## Current API Usage Summary

The frontend already consumes a relatively small subset of the backend surface, mainly:
- auth: login, logout, me
- platform lists: tenants, users
- health
- WebRTC TLS settings
- log viewer APIs
- SIP status APIs
- tenant lists / read-only views for extensions, ring groups, CDRs, audit logs
- DID CRUD
- gateway CRUD

This means the frontend is currently an admin shell with a few implemented workflows, not yet a full control plane for the backend.

## Planning Principles

1. Build operationally valuable workflows first.
2. Prefer complete vertical slices over more read-only pages.
3. Prioritize APIs tied to real admin work: provisioning, user management, tenant settings, recordings, device management, exports, status, observability.
4. Avoid investing heavily in CRUD pages for features that are expected to move into the future call-flow builder.
5. Reuse shared query, form, table, and confirmation patterns.
6. Keep tenant-scoped features clearly separated from platform-admin features.
7. Build with accessibility in mind, including keyboard support, visible focus states, clear labels, and robust empty/loading/error states.

## Target Outcome

After execution of this plan, the frontend should provide:
- full tenant and user lifecycle management
- complete extension management including WebRTC provisioning visibility
- core telephony connectivity management
- better operational tooling for logs, exports, status, and SSL/WebRTC controls
- access to recordings, analytics, and device provisioning areas
- a clear placeholder strategy for future graph-based call-flow features

---

# Implementation Roadmap

## Phase 1 — Stabilize Existing Pages Into Real Workflows

### Goal
Turn currently partial or read-only screens into fully usable admin workflows.

### 1. Tenants
Current state:
- list view only
- create button has no implemented flow
- no edit/settings/provision UX

Implement:
- tenant create form
- tenant edit form
- tenant settings page
- tenant provision action flow
- tenant detail summary page or right-side drill-down

APIs to use:
- `GET /tenants`
- `POST /tenants`
- `GET /tenants/{tenant}`
- `PUT /tenants/{tenant}`
- `DELETE /tenants/{tenant}` if allowed by business rules
- `GET /tenants/{tenant}/settings`
- `PUT /tenants/{tenant}/settings`
- `POST /tenants/provision`

Frontend additions:
- `TenantsListPage`
- `TenantFormPage`
- `TenantSettingsPage`
- provision confirmation dialog
- route updates under `/admin/tenants/...`

Priority notes:
- tenant create + settings should be high priority
- delete can be hidden behind stricter checks if destructive

### 2. Users and Permissions
Current state:
- list view only
- create button is not implemented
- no permission management UI

Implement:
- create/edit user form
- reset role/tenant assignment flow
- permissions viewer
- grant/revoke permission actions

APIs to use:
- `GET /users`
- `POST /users`
- `GET /users/{user}`
- `PUT /users/{user}`
- `DELETE /users/{user}`
- `GET /users/{user}/permissions`
- `POST /users/{user}/permissions/grant`
- `POST /users/{user}/permissions/revoke`
- `GET /permissions`

Frontend additions:
- `UserFormPage`
- `UserPermissionsPage` or permissions drawer
- tenant selector for scoped users
- role badges and filters

Priority notes:
- permissions management is important because the backend already exposes it and admin usability depends on it

### 3. Extensions
Current state:
- list view + bulk registration status view
- no create/edit/delete/provisioning UI
- no WebRTC configuration view

Implement:
- create/edit/delete extension workflows
- extension detail drawer/page
- WebRTC config viewer for each extension
- copy SIP credentials / provisioning snippets where safe
- registration status detail panel

APIs to use:
- `GET /tenants/{tenant}/extensions`
- `POST /tenants/{tenant}/extensions`
- `GET /tenants/{tenant}/extensions/{extension}`
- `PUT /tenants/{tenant}/extensions/{extension}`
- `DELETE /tenants/{tenant}/extensions/{extension}`
- `GET /tenants/{tenant}/extensions/{extension}/webrtc-config`
- `GET /tenants/{tenant}/extensions/status/all`
- `GET /tenants/{tenant}/extensions/{extension}/status`

Frontend additions:
- `ExtensionFormPage`
- `ExtensionDetailsPage`
- `ExtensionWebRtcPanel`
- status polling refinement

Priority notes:
- this is one of the most important gaps because extensions are central to PBX administration

### 4. Ring Groups
Current state:
- list only
- create/edit/delete buttons not wired

Implement:
- full CRUD for ring groups
- members editor
- ring strategy form controls

APIs to use:
- `GET /tenants/{tenant}/ring-groups`
- `POST /tenants/{tenant}/ring-groups`
- `GET /tenants/{tenant}/ring-groups/{ringGroup}`
- `PUT /tenants/{tenant}/ring-groups/{ringGroup}`
- `DELETE /tenants/{tenant}/ring-groups/{ringGroup}`

Frontend additions:
- `RingGroupFormPage`
- reusable member picker component using extensions list

Priority notes:
- keep this because ring groups remain useful as concrete telephony resources even if broader call-flow logic moves to node-based flows later

### 5. DIDs and Gateways
Current state:
- better than most pages; CRUD exists
- still missing richer validation and linked resource pickers

Improve:
- stronger destination pickers for DIDs
- richer gateway status visibility
- destructive action feedback
- optimistic invalidation consistency
- optional status integration via gateway status endpoint

Potential APIs to add to UI usage:
- `GET /tenants/{tenant}/gateways/{gateway}/status`

Priority notes:
- these pages should be refined, not rewritten

---

## Phase 2 — Operational and Observability Features

### Goal
Support real production operations, incident response, and administration.

### 6. Admin Dashboard Upgrade
Current state:
- basic tenants count + health check only

Implement:
- use dedicated admin dashboard API instead of composing minimal widgets from generic endpoints
- add cards for tenants, users, active registrations, call volume, health, recent alerts
- add quick links into major admin actions

APIs to use:
- `GET /admin/dashboard`
- keep `GET /health` as lightweight infrastructure status if still useful

Priority notes:
- dashboard should become a meaningful operational landing page

### 7. SSL Management
Current state:
- frontend has WebRTC TLS settings page only
- backend also exposes platform SSL APIs

Implement:
- SSL overview page
- current certificate state
- update SSL settings
- request certificate action
- show relationship between SSL and WebRTC TLS mode

APIs to use:
- `GET /admin/ssl`
- `PUT /admin/ssl`
- `POST /admin/ssl/request`

Frontend additions:
- either expand current settings page or add a dedicated SSL section under system settings

Priority notes:
- high operational value because TLS and browser trust directly affect WebRTC success

### 8. SIP Profiles and Blocked Destinations
Current state:
- SIP live status exists
- config CRUD for sip profiles / blocked destinations is missing

Implement:
- SIP profile management page
- blocked destinations management page
- clear warnings for destructive SIP profile edits

APIs to use:
- `admin/sip-profiles` resource endpoints
- `admin/blocked-destinations` resource endpoints

Priority notes:
- useful for superadmin operations and security posture
- can follow SSL work

### 9. Log Viewer Enhancements
Current state:
- good read-only start
- no actual export/download actions wired

Implement:
- working download/export controls
- preset filters
- copy line / share diagnostics conveniences
- auto-refresh toggle
- stronger error handling

APIs to use:
- existing `admin/logs`, `admin/logs/application`, `admin/logs/freeswitch`
- if download endpoints do not exist, either add them later or implement client-side export from returned data

Priority notes:
- medium priority, mostly UX completion

### 10. SIP Status Enhancements
Current state:
- useful live page already exists
- still lacks profile detail page and deeper drilldown

Implement:
- profile detail drilldown
- gateway detail actions and filters
- registrations search/filter/export
- action result toasts and audit trail references

APIs to use:
- existing SIP status endpoints
- `GET /admin/sip-status/profiles/detail`

Priority notes:
- medium-high priority because this page already has strong operational value

---

## Phase 3 — Telephony Support Features With Strong ROI

### Goal
Deliver major backend capabilities that improve real deployments without stepping into the future graph-based call-flow editor prematurely.

### 11. System Media
Backend support exists for platform-managed audio/media assets.

Implement:
- list, upload, preview metadata, replace, delete system media
- media usage notes where possible

APIs to use:
- `GET /tenants/{tenant}/system-media`
- `POST /tenants/{tenant}/system-media`
- `GET /tenants/{tenant}/system-media/{mediaId}`
- `PUT /tenants/{tenant}/system-media/{mediaId}`
- `DELETE /tenants/{tenant}/system-media/{mediaId}`

Priority notes:
- useful now even before graph-based call-flow arrives

### 12. Device Profiles
Implement:
- list/create/edit/delete device profiles
- profile templates, provisioning values, brand/model support

APIs to use:
- `device-profiles` resource endpoints under tenant scope

Priority notes:
- important if desk-phone provisioning is part of the platform rollout

### 13. Mobile Devices
Implement:
- device registration list
- token refresh / heartbeat visibility
- device capability viewer
- remove device action

APIs to use:
- mobile device endpoints under tenant scope

Priority notes:
- useful for mobile/WebRTC endpoint management
- can be shipped after extension improvements

### 14. Recordings
Implement:
- recordings list
- recording details
- download action
- delete action with warnings
- filter by date/extension/caller

APIs to use:
- `GET /tenants/{tenant}/recordings`
- `GET /tenants/{tenant}/recordings/{recording}`
- `DELETE /tenants/{tenant}/recordings/{recording}`
- `GET /tenants/{tenant}/recordings/{recording}/download`

Priority notes:
- strong operational value and relatively self-contained

### 15. Audit Logs Detail
Current state:
- list only

Implement:
- audit log detail drawer/page
- filters by actor/action/object/date
- JSON change viewer

APIs to use:
- `GET /tenants/{tenant}/audit-logs`
- `GET /tenants/{tenant}/audit-logs/{auditLog}`

Priority notes:
- useful for admin trust and compliance

### 16. CDR Export and Analytics
Current state:
- basic CDR list only
- export button not wired

Implement:
- working CSV export
- filters: date range, extension, direction, hangup cause
- analytics dashboard cards and charts

APIs to use:
- `GET /tenants/{tenant}/cdrs`
- `GET /tenants/{tenant}/cdrs/{cdr}`
- `GET /tenants/{tenant}/cdrs/export`
- `POST /tenants/{tenant}/cdrs/export`
- `GET /tenants/{tenant}/cdrs/analytics/summary`
- `GET /tenants/{tenant}/cdrs/analytics/volume`
- `GET /tenants/{tenant}/cdrs/analytics/quality`
- `GET /tenants/{tenant}/cdrs/analytics/destinations`

Priority notes:
- high-value reporting area
- analytics should likely come before more niche telephony modules

---

## Phase 4 — Contact Center and Advanced Operational Domains

### Goal
Expose advanced but still non-graph-builder functionality in a deliberate way.

These are important, but should follow the core admin and tenant management work.

### 17. Teams
Implement full CRUD if the business workflow depends on teams outside the future flow builder.

### 18. Agents
Implement:
- agent list/create/edit/delete
- change state actions
- agent status visibility

APIs:
- `agents` resource endpoints
- `POST /agents/{agent}/state`

### 19. Bridges
Implement CRUD if bridges are a real operator-facing feature.

### 20. Webhooks
Implement:
- webhook CRUD
- delivery attempts list
- delivery stats view
- secret masking and rotate patterns if needed later

APIs:
- webhook endpoints and delivery stats endpoints

### 21. Call Routing Policies
Implement:
- policy CRUD
- evaluate action UI
- simulation results panel

Priority notes:
- useful for admins, but should be validated against future call-flow direction to avoid duplicated UX concepts

---

## Phase 5 — Future Graph-Based Call Flow Builder Preparation

### Goal
Avoid dead-end UI work now while preparing for the future node-based system.

This phase is mostly planning and architecture alignment, not full delivery now.

### Deferred Domains
The following backend APIs should remain backend-first until the builder is ready:
- `flows`
- `ivrs`
- `time-conditions`
- `queues`
- queue member management
- queue metrics views that are tightly coupled to future routing logic

### Recommended short-term frontend strategy
- do not add top-level CRUD-heavy menu pages for these domains unless there is a strict temporary need
- if visibility is required, provide:
  - read-only inspector pages
  - feature placeholders
  - admin-only internal tooling
- document clearly that these areas will be absorbed into the future visual call-flow system

### Future builder requirements to prepare now
- node palette aligned with backend actions/resources
- reusable selectors for extension, ring group, media, webhook, schedule, team, agent, queue
- validation model for graph publishing
- versioning and draft/publish workflow
- simulation/test execution tools
- execution trace visualizer using call-events and call traces

---

# Recommended Delivery Order

## Wave 1 — Must-Have Admin Completion
1. tenant create/edit/settings/provision
2. user create/edit/permissions
3. extension create/edit/delete + WebRTC config visibility
4. ring group CRUD
5. DID/gateway refinements

## Wave 2 — Operational Reliability
6. admin dashboard upgrade
7. SSL management
8. SIP profile + blocked destination management
9. log viewer completion
10. SIP status drilldowns

## Wave 3 — Tenant Operations and Reporting
11. recordings
12. CDR export
13. CDR analytics
14. audit log detail
15. system media
16. device profiles
17. mobile devices

## Wave 4 — Advanced Domains
18. teams
19. agents
20. webhooks
21. bridges
22. call routing policies

## Wave 5 — Future Builder
23. graph-based call-flow builder discovery and UI architecture
24. migration path from standalone backend resources into node-based flow authoring

---

# Frontend Architecture Work Needed Alongside Features

## Shared Data Layer
Create or standardize:
- domain-specific API modules or hooks per feature area
- consistent query keys
- mutation invalidation strategy
- shared pagination/filter helpers if list sizes grow

Suggested structure:
- `resources/js/features/tenants/*`
- `resources/js/features/users/*`
- `resources/js/features/extensions/*`
- `resources/js/features/operations/*`
- `resources/js/features/reporting/*`

## Shared UI Patterns
Build reusable components for:
- list page shell
- table toolbar with filter/search/export
- confirmation dialog
- delete action patterns
- detail drawer
- status pill/badge mapping
- JSON inspector
- credential / token masked copy field
- empty state component
- error state / retry block

## Routing Improvements
Add nested route groups where useful:
- `/admin/tenants/*`
- `/admin/users/*`
- `/admin/extensions/*`
- `/admin/operations/*`
- `/admin/reporting/*`

This will reduce flat route sprawl and make future expansion easier.

## Form Strategy
Standardize on:
- `react-hook-form`
- `zod` schemas for client validation
- API error mapping back to fields
- submit states / dirty states / unsaved change warnings

## State and Polling Strategy
For live operational pages:
- centralize polling intervals
- allow manual refresh + auto refresh toggles
- avoid unnecessary high-frequency refetching
- add cancellation and stale-state handling for quick tenant switching

---

# API-to-UI Mapping Priorities

## Platform Admin Features
### Implement now
- tenants
- users
- permissions
- admin dashboard
- SSL
- WebRTC TLS settings
- SIP profiles
- blocked destinations
- logs
- SIP status

### Keep for later only if needed
- token management UI

## Tenant Admin Features
### Implement now
- extensions
- extension status
- extension WebRTC config
- ring groups
- DIDs
- gateways
- recordings
- CDRs
- CDR export
- CDR analytics
- audit logs
- system media
- device profiles
- mobile devices

### Defer toward graph-based builder
- flows
- IVRs
- time conditions
- queues
- queue member workflows
- flow publishing UI as an end-user feature

### Conditional / later
- teams
- agents
- bridges
- webhooks
- call routing policies
- wallboard and real-time contact center views
- call events, call traces, and live call control tooling

---

# Suggested Page Additions

## High-priority new pages
- `/admin/tenants/create`
- `/admin/tenants/:id/edit`
- `/admin/tenants/:id/settings`
- `/admin/users/create`
- `/admin/users/:id/edit`
- `/admin/users/:id/permissions`
- `/admin/extensions/create`
- `/admin/extensions/:id/edit`
- `/admin/extensions/:id`
- `/admin/ring-groups/create`
- `/admin/ring-groups/:id/edit`
- `/admin/recordings`
- `/admin/device-profiles`
- `/admin/mobile-devices`
- `/admin/ssl`
- `/admin/sip-profiles`
- `/admin/blocked-destinations`
- `/admin/cdr-analytics`

## Placeholder / future routes only when needed
- `/admin/call-flow-builder`
- `/admin/call-flow-library`
- `/admin/call-execution-traces`

---

# Risks and Mitigations

## Risk 1: Building temporary CRUD UIs for future graph-owned domains
Mitigation:
- do not invest in full CRUD pages for flows, IVRs, time conditions, and queues right now
- use placeholders or internal-only stopgap tools if absolutely necessary

## Risk 2: Flat route/page sprawl
Mitigation:
- group pages by feature domain
- create reusable list/form/detail patterns

## Risk 3: Inconsistent API handling across pages
Mitigation:
- centralize query keys, API wrappers, error normalization, and mutation invalidation rules

## Risk 4: Poor tenant-switch handling on live pages
Mitigation:
- ensure all tenant-scoped queries key off active tenant ID
- clear stale selected entity state on tenant switch

## Risk 5: Operational pages becoming noisy or expensive
Mitigation:
- control polling frequencies
- add manual refresh toggles
- use focused queries and lazy detail fetches

---

# Acceptance Criteria For This Roadmap

A good implementation of this plan should result in:
- admins can create and manage tenants and users from the UI
- tenant admins can fully manage extensions, ring groups, DIDs, and gateways
- WebRTC/SSL/SIP operational workflows are visible and actionable from the UI
- recordings, exports, analytics, and audit detail become usable without leaving the frontend
- the frontend does not overcommit to page-based CRUD for domains planned for a future graph-based flow builder
- the future call-flow builder path remains clean and intentional

---

# Recommended First Sprint

If this should be started immediately, the first sprint should contain:
1. tenant create/edit/settings/provision
2. user create/edit/permissions
3. extension create/edit/delete + WebRTC config panel
4. ring group CRUD
5. route and shared-component cleanup needed to support those pages

This first sprint delivers the biggest practical improvement to frontend completeness without conflicting with the future graph-based call-flow direction.
