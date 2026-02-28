# NIZAM Changelog

All notable changes to the NIZAM project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

---

## [Unreleased]

### Added

#### Infrastructure
- Docker Compose baseline with 6 services: app, nginx, postgres, redis, freeswitch, queue worker
- `GET /api/health` — unauthenticated endpoint reporting app, ESL, and FreeSWITCH status
- FreeSWITCH container with `mod_xml_curl` and `mod_event_socket` configuration
- Environment bootstrap documentation in README

#### Switch Integration
- XML directory endpoint for FreeSWITCH (`mod_xml_curl`)
- XML dialplan endpoint with dynamic routing
- Dialplan Compiler service generating XML from database state
- ESL listener service with automatic reconnection and exponential backoff (1s → 30s)
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
- Role-based authorization policies for all resources (Tenant, Extension, DID, RingGroup, IVR, TimeCondition, Webhook, DeviceProfile)
- `$this->authorize()` calls wired into all resource controllers
- Tenant-scoped API middleware (`tenant.access`) on all tenant routes
- Rate limiting: 60 requests/minute per user or IP
- REST endpoints for all resources: Tenant, Extension, DID, Ring Group, IVR, Time Condition, CDR, Device Profile, Webhook
- Call originate and status endpoints
- Call event list and trace endpoints

#### Event & Observability
- Call UUID correlation across full lifecycle
- Persistent `call_events` table for event replay
- Call trace API: `GET /call-events/{uuid}/trace`
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
- `hasPermission()`, `grantPermissions()`, `revokePermissions()` on User model

#### Webhooks
- Outbound event notifications with HMAC-SHA256 signing
- Configurable event subscriptions per tenant
- Queued delivery via `DeliverWebhook` job with retry logic
- Events: call.started, call.answered, call.bridge, call.missed, call.hangup, voicemail.received, registration.registered, registration.unregistered

### Tests
- 266 tests with 520 assertions covering all features
- Unit tests for models, services, policies, observers, modules
- Feature tests for all API endpoints, middleware, provisioning
