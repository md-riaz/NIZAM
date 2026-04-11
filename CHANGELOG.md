# NIZAM Changelog

All notable changes to the NIZAM project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

---

## [1.0.0] — 2026-03-01

### Summary

NIZAM v1.0.0 is the first stable, production-ready release of the Open Communications Control Platform.
This release establishes a frozen API contract, defined scope, and operational readiness for multi-tenant PBX deployments.

### Added

#### Governance & Documentation
- `LICENSE` — MIT License
- `CONTRIBUTING.md` — Contributor guidelines including branch naming, testing, and code style
- `CODE_OF_CONDUCT.md` — Contributor Covenant v2.1
- `docs/v1-scope.md` — Explicit v1.0 feature boundary (included and excluded features)
- `docs/performance-baseline.md` — Performance targets and regression thresholds under 200-call load
- `docs/ARCHITECTURE.md` — Architectural doctrine and non-negotiables
- `docs/api-reference.md` — Full REST API reference
- `docs/module-development.md` — Module SDK documentation
- `docs/deployment-scaling.md` — Deployment and scaling guide
- `docs/environment-bootstrap.md` — Local and production environment setup
- `docs/versioning-and-releases.md` — Semantic versioning and release policy
- `docs/slos.md` — Service Level Objectives
- `docs/escalation-checklist.md` — On-call escalation procedures
- `docs/openapi.yaml` — OpenAPI 3.1 specification (versioned)
- `docs/postman-collection.json` — Postman collection for all API endpoints
- `docs/runbooks/` — Runbooks for FreeSWITCH node down, ESL disconnect storm, Redis pressure, webhook backlog, DB connection exhaustion

#### API Contract
- All API routes versioned under `/api/v1` prefix
- OpenAPI spec updated to reflect `/api/v1` server base URL
- Breaking changes forbidden after this release

#### SDK
- PHP SDK at `sdk/php/` with README and usage examples

### Changed
- API routes moved from `/api/*` to `/api/v1/*` (breaking change for pre-release clients)

### Known Limitations
See [docs/v1-scope.md](docs/v1-scope.md#known-limitations-v10) for the full list. Key limitations:
- Single FreeSWITCH node only (no clustering)
- WebRTC requires external TLS certificates (WSS/DTLS supported)
- No visual flow builder (API-first only)
- Analytics is rule-based (no ML)

---

## [1.0.1] - 2026-04-09

### Changed
- **Frontend Routing:** Migrated the app from `<BrowserRouter>` to the data router `<RouterProvider>` (`createBrowserRouter`) to properly support React Router v6.4+ features like `useBlocker` without runtime crashes.
- **SIP Profile Form:** Separated WebRTC transport enablement into independent WS and WSS toggles, allowing WS to be enabled without forcing WSS/TLS settings (useful when proxying WebRTC via NGINX).
- **Extension Details:** Replaced the WebRTC configuration card with a generic "SIP Credentials" card that displays the SIP Server, TLS Server (if applicable), Transport options, Username, and Password. WebRTC status is now shown as an indicator badge.
- **Codec Resolution:** `BridgeCompiler` now accepts and forwards the real A-leg endpoint type (`sip` or `webrtc`) to `CodecResolutionService` instead of hardcoding `'sip'`. WebRTC calls now correctly resolve Opus-first codec defaults and honour `web_only` transcoding policies during bridge compilation.
- **Dialplan Compiler:** Added `inferEndpointType()` which detects WebRTC calls from the FreeSWITCH XML-CURL payload (`variable_sip_via_protocol=wss` or `variable_sip_transport=wss`) and threads the result through all bridge compilation paths (`compileDidExtension`, `compileAntiAction`, `compileDestinationAction`).
- **Call Session:** `FreeswitchXmlController` now persists the inferred `endpoint_type` into `CallSession->variables` on both the compiled-manifest and interpreted-fallback paths, making the A-leg transport type available for tracing, analytics, and downstream logic.

### Fixed
- **Form Validation:** Improved the `getErrorMessage` utility to parse Laravel's `errors` validation bag and display nested field errors as a readable list instead of a cryptic summary string.
- **Form Validation:** Added pre-submit frontend validation to the SIP Profile dynamic settings table to prevent empty setting rows from being sent to the API.
- **UI UX:** Added a `required` prop to the reusable `FormLabel` component to render a red asterisk `*`, and applied it to mandatory fields in the SIP Profile editor.
- **UI UX:** Empty required fields in the SIP Profile settings table now highlight with a red border if left blank after a save attempt.
- **Test Safety:** Hardened Laravel test bootstrap so `php artisan test` always uses in-memory SQLite and aborts immediately if the test environment ever tries to boot against a non-SQLite connection, protecting the Docker Postgres users table from accidental wipes.

---

## [1.0.2] - 2026-04-11

### Added
- **SIP Credentials UI**: Added "Domain / Realm" to the SIP Credentials display on the extension detail page and included it in the "Copy credentials" action.

### Changed
- **SIP Configuration**: Updated the extension SIP configuration endpoint to return the current application host for the SIP server instead of the tenant domain, improving compatibility with softphones registering from external networks.
- **Seeding:** Updated `DatabaseSeeder` to use the `ADMIN_EMAIL`, `ADMIN_NAME`, and `ADMIN_PASSWORD` from `.env` consistently across all seeded admin records.

### Fixed
- **Database Seeding**: Resolved a unique constraint conflict where the platform admin and tenant admin were assigned the same email address. The platform admin now uses a `system@` prefix.
- **Test Isolation**: Isolated FreeSWITCH gateway provisioning during tests. Tests now write XML profiles to a temporary directory (`storage/framework/testing/gateways`) instead of the real configuration, preventing "orphan" registrations and "fail wait" loops in development.
- **Gateway Sync**: Corrected the configuration key used in `GatewayProvisioningServiceTest` and `GatewayCodecRenderingTest` to correctly redirect filesystem output during unit tests.

---

## [1.0.1] - 2026-04-09
#### Infrastructure
- Docker Compose baseline with 6 services: app, nginx, postgres, redis, freeswitch, queue worker
- `GET /api/v1/health` — unauthenticated endpoint reporting app, ESL, and FreeSWITCH status
- FreeSWITCH container with `mod_xml_curl` and `mod_event_socket` configuration
- Environment bootstrap documentation in README
- Enabled essential FreeSWITCH modules in Dockerfile: `mod_callcenter`, `mod_shout`, `mod_xml_curl`, `mod_curl`
- Fixed `fs_cli` connectivity by updating `event_socket.conf.xml` ACL to `any_v4.auto`
- Added Supervisor configuration for ESL listener daemon (commented out by default for deployment)

#### Switch Integration
- XML directory endpoint for FreeSWITCH (`mod_xml_curl`)
- XML dialplan endpoint with dynamic routing
- Dialplan Compiler service generating XML from database state
- ESL listener service with automatic reconnection and exponential backoff (1s → 30s)
- **ESL Webhook Dispatcher** (`freeswitch:listen` artisan command):
  - Connects to FreeSWITCH ESL via native PHP sockets (zero external dependencies)
  - Subscribes to `CHANNEL_CREATE`, `CHANNEL_ANSWER`, `CHANNEL_HANGUP`, `CHANNEL_HANGUP_COMPLETE`
  - Maps events to webhook types: `call.created`, `call.answered`, `call.ended`, `call.completed`
  - Multi-tenant resolution via 3 strategies: `variable_domain_name`, `Caller-Context`, extension lookup
  - Dispatches through existing `WebhookDispatcher` service with HMAC signing and delivery tracking
- Event normalization layer: CHANNEL_CREATE, CHANNEL_ANSWER, CHANNEL_BRIDGE, CHANNEL_HANGUP_COMPLETE
- SIP registration tracking via `sofia::register` / `sofia::unregister` custom events
- SIGINT/SIGTERM signal handling for graceful ESL listener shutdown

#### Data Architecture
- Multi-tenant schema with domain-based isolation
- Extension model with SIP passwords and voicemail PINs in plaintext (for webphone/sip.js integration)
- Voicemail PIN stored as plaintext for dashboard/API display
- DID routing model with polymorphic destination support
- Ring Group, IVR, Time Condition models with compiler logic
- CDR schema with UUID correlation
- Call event log schema for persistent event replay
- Audit log schema with old/new value tracking

#### Core Telephony
- Extension CRUD with tenant scoping
- DID → Extension routing via Dialplan Compiler
- Ring Group support (simultaneous + sequential strategies)
- IVR model with digit-to-destination mapping
- Time Condition evaluation engine with FreeSWITCH `<condition>` attributes (wday, time-of-day, mday, mon)
- Time Condition match/no-match routing with `<action>` and `<anti-action>` elements
- Fail-safe routing: unroutable destinations return `respond 404`

#### API Governance
- Sanctum token authentication (register, login, logout, me)
- Role-based authorization policies for all resources (Tenant, Extension, DID, RingGroup, IVR, TimeCondition, Webhook, DeviceProfile, Recording, CDR, CallEvent, Call, User)
- `$this->authorize()` calls wired into all resource controllers
- Tenant-scoped API middleware (`tenant.access`) on all tenant routes
- Rate limiting: 60 requests/minute per user or IP
- REST endpoints for all resources: Tenant, Extension, DID, Ring Group, IVR, Time Condition, CDR, Device Profile, Webhook, Recording, User
- Call originate and status endpoints
- Call event list, trace, and real-time SSE stream endpoints

#### Event & Observability
- Call UUID correlation across full lifecycle
- Persistent `call_events` table for event replay
- Call trace API: `GET /call-events/{uuid}/trace`
- Server-Sent Events (SSE) endpoint: `GET /call-events/stream` for real-time event streaming with Last-Event-ID reconnection support
- CDR-Recording relationship via call UUID for linking recordings to call records
- Gateway status polling command (`nizam:gateway-status`) with cached results
- Private WebSocket channels per tenant (`private-tenant.{id}.calls`) with channel authorization
- Broadcast channel authorization in `routes/channels.php`
- CDR auto-creation on call hangup
- Event broadcasting on tenant-scoped WebSocket channels

#### Provisioning
- Device Profile model with vendor abstraction
- Template rendering engine with variable substitution
- MAC detection endpoint (`GET /provision/{mac}`)
- Auto-regeneration of device profiles on extension update (ExtensionObserver)

#### Security
- SIP passwords and voicemail PINs stored as plaintext (for webphone/sip.js integration)
- Webhook secrets encrypted at rest
- API rate limiting (60 req/min)
- Tenant isolation enforcement via middleware
- Audit log system tracking all domain model changes
- Audit log API: read-only endpoints for querying audit trail (`GET /audit-logs`)
- Fail-safe routing default (404 for unroutable destinations)

#### Module Framework
- `NizamModule` interface with lifecycle hooks
- `ModuleRegistry` singleton for module management
- Hooks: dialplan contributions, event subscriptions, permission extensions
- Migration isolation per module via `migrationsPath()` hook
- Error isolation per module event handler
- `make:nizam-module` artisan command — generates full module skeleton with all hooks

#### Permissions
- Granular permission model with user-permission assignments
- Core permissions for all CRUD operations (tenants, extensions, DIDs, ring groups, IVRs, etc.)
- Module-contributed permissions synced via `nizam:sync-permissions` command
- Admin role bypasses all permission checks
- Granular permissions enforced in all authorization policies (default-open when no permissions assigned, restrictive once granted)
- `hasPermission()`, `grantPermissions()`, `revokePermissions()` on User model
- User management API (admin-only CRUD for users)
- Permission management API (grant/revoke permissions, list available permissions)

#### Recordings
- Recording model with file indexing, metadata tracking
- Recording API: list, show, download, delete
- Configurable recording storage disk (`RECORDING_PATH` env var)

#### Tenant Management
- Tenant settings endpoint (`GET /settings`, `PUT /settings` with merge behavior)
- Tenant dashboard statistics API (`GET /stats`) with resource counts and CDR summaries

#### CDR Export
- CDR CSV export endpoint (`GET /cdrs/export`) with filter support and 10,000 record limit

#### API Token Management
- List, create, and revoke named API tokens (`GET /auth/tokens`, `POST /auth/tokens`, `DELETE /auth/tokens/{id}`)
- Supports named tokens with configurable abilities

#### Webhooks
- Outbound event notifications with HMAC-SHA256 signing
- Configurable event subscriptions per tenant
- Queued delivery via `DeliverWebhook` job with retry logic
- Events: call.started, call.answered, call.bridge, call.missed, call.hangup, voicemail.received, registration.registered, registration.unregistered

#### UI/UX Enhancements
- Added dynamic label support to the superadmin navigation sidebar (SuperadminLayout.tsx)
- Sidebar visually differentiates between Platform Admin mode and Tenant mode
- Updated "Gateways" menu item to dynamically render as "Platform Gateways" when acting globally and "Gateways" when a tenant is selected
#### API & Features
- **System Media & Prompts API**: Integrated `spatie/laravel-medialibrary` for RESTful audio management (prompts, MOH).
- **Registration Status API**: Real-time SIP registration queries for Extensions and Gateways via FreeSWITCH ESL.
- Added `/api/v1/tenants/{id}/system-media` endpoints for media lifecycle management.
- Added `/api/v1/tenants/{id}/extensions/status/all` and `/api/v1/tenants/{id}/gateways/{id}/status` for live monitoring.

### Tests
- 330 tests with 641 assertions covering all features
- Unit tests for models, services, policies, observers, modules
- Feature tests for all API endpoints, middleware, provisioning
- Permission-policy integration tests
- **New**: Integration tests for SystemMedia and RegistrationStatus (pending execution)
