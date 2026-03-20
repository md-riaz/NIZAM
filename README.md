# NIZAM

**NIZAM** — Open Communications Control Platform

> From Arabic: نظام (Nizām) — meaning *system, order, structure*.

NIZAM is an API-first, modular communications platform built on top of [FreeSWITCH](https://freeswitch.com), designed to provide structured automation, integration, and multi-tenant telephony control — serving as a modern alternative to FusionPBX and Wazo.

---

## Vision

NIZAM separates concerns into distinct layers:

| Layer | Technology | Responsibility |
|-------|-----------|----------------|
| **Media Core** | FreeSWITCH | SIP signaling, RTP media, WebRTC (WSS/DTLS), call bridging, recording, conferencing |
| **Control Plane** | Laravel 12 | Business logic, tenant management, routing, provisioning |
| **Integration Layer** | REST + WebSocket + Events | API access, real-time streaming, webhooks |
| **Provisioning Layer** | Template engine | Device automation, vendor profiles |

FreeSWITCH remains stateless regarding business logic. All business state lives in NIZAM.

---

## Architecture

```
┌─────────────────────────────────────────────────────┐
│                    NIZAM Platform                    │
├─────────────┬──────────────┬────────────────────────┤
│  REST API   │  WebSocket   │  Event Bus             │
│  (Sanctum)  │  (Reverb)    │  (Redis/Queue)         │
├─────────────┴──────────────┴────────────────────────┤
│              Laravel Control Plane                   │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐            │
│  │ Tenant   │ │Extension │ │ Routing  │  ...       │
│  │ Service  │ │ Service  │ │ Service  │            │
│  └──────────┘ └──────────┘ └──────────┘            │
├─────────────────────────────────────────────────────┤
│           Dialplan Compiler (mod_xml_curl)           │
├─────────────────────────────────────────────────────┤
│              FreeSWITCH Media Core                   │
│        SIP · RTP · Voicemail · Conferencing          │
└─────────────────────────────────────────────────────┘
```

### Configuration Model

1. API call updates the database (single source of truth)
2. Dialplan compiler generates runtime XML configuration
3. `mod_xml_curl` dynamically serves directory & dialplan to FreeSWITCH
4. No manual XML editing required

---

## Core Features

### Multi-Tenancy
- Domain-based tenant isolation
- Per-tenant resource limits
- Scoped authentication via Sanctum
- Role-based authorization (admin bypasses tenant checks)

### Extensions
- SIP user management with plaintext passwords (accessible for webphone/sip.js integration)
- Voicemail settings (PIN stored in plaintext for display in dashboards/API)
- Caller ID control (effective and outbound)

### Inbound Routing (DIDs)
- Layered DID precedence: generic, gateway-specific, and gateway-registration-specific routes for the same number
- DID → Destination mapping
- Destination types: Extension, Ring Group, IVR, Time Condition, Voicemail, Call Routing Policy, Flow, Bridge
- Fail-safe routing: unroutable destinations return `404` instead of empty dialplan

### Ring Groups
- Simultaneous and sequential strategies
- Cause-aware fallback routing on no-answer / unavailable style outcomes
- Fallback destinations can route to extension, voicemail, IVR, flow, bridge, and other compiled destinations

### IVR Menus
- Prompt upload support
- Digit-to-destination mapping
- Timeout routing

### Time Conditions
- Office hours logic with day/time rules
- Match and no-match destination routing
- Can target bridge and flow destinations in addition to local tenant objects

### CDR & Recording
- Indexed call detail records
- UUID correlation with FreeSWITCH
- Recording path tracking
- Recording model with file indexing, download API, and deletion
- CDR CSV export with filtering (`GET /api/tenants/{id}/cdrs/export`)

### Device Provisioning
- Template-based device configs
- Vendor profiles (Polycom, Yealink, Grandstream) with MAC detection
- Auto-provisioning endpoint for phones (`GET /provision/{mac}`)
- Automatic device profile regeneration when extension fields change

### Webhooks
- Outbound event notifications for CRM/ERP integration
- Configurable event subscriptions per tenant
- HMAC-SHA256 signed payloads for security (secrets encrypted at rest)
- Queued delivery with exponential backoff retry
- Events: `call.started`, `call.answered`, `call.bridge`, `call.missed`, `call.hangup`, `voicemail.received`, `registration.registered`, `registration.unregistered`

### Event Bus & Observability
- FreeSWITCH ESL event listener with automatic reconnection (`php artisan freeswitch:listen`)
- Exponential backoff on ESL disconnect (1s → 30s max)
- SIGINT/SIGTERM signal handling for graceful shutdown
- Real-time call event processing and CDR creation
- Persistent event log for full call lifecycle replay
- Call trace API for debugging any call by UUID
- Gateway status polling and caching (`php artisan nizam:gateway-status`)
- Broadcast events via WebSocket channels per tenant
- Automatic webhook dispatch on matching events

### Audit Logging
- Automatic create/update/delete tracking on all domain models
- Old and new values stored per change
- User and IP tracking for accountability
- Applied to: Extension, Tenant, DID, RingGroup, IVR, TimeCondition, Webhook, DeviceProfile

### Module Framework
- `NizamModule` interface for plug-in extensibility
- Hooks for: dialplan contributions, event subscriptions, permission extensions
- Module registry with lifecycle management (register → boot)
- Module skeleton generator (`php artisan make:nizam-module {name}`)
- Migration isolation per module via `migrationsPath()` hook
- Error isolation per module

### Bridges & Gateways
- FusionPBX-style gateway provisioning with generated FreeSWITCH gateway XML
- Shared generated gateway config mounted into FreeSWITCH external profile includes
- Bridge objects for reusable outbound targets
- Bridge destinations supported across DIDs, routing policies, time conditions, IVR timeout routing, and ring-group fallbacks
- Built-in SIP mock registrar for repeatable local trunk-registration testing without real carrier credentials

### WebRTC Support
- Dedicated WSS (WebSocket Secure) SIP profile on port 7443
- DTLS-SRTP for encrypted media transport
- Opus codec prioritized for high-quality WebRTC audio
- ICE/STUN/TURN support for NAT traversal
- Per-extension WebRTC configuration API endpoint
- System-wide STUN/TURN server settings
- SIP.js-ready credential endpoint

### Security
- SIP passwords stored as plaintext for webphone/sip.js integration
- Webhook secrets encrypted at rest
- API rate limiting (60 requests/minute per user or IP)
- Tenant isolation middleware on all scoped routes
- Role-based authorization policies (TenantPolicy, ExtensionPolicy, DidPolicy, RingGroupPolicy, IvrPolicy, TimeConditionPolicy, WebhookPolicy, DeviceProfilePolicy, UserPolicy, RecordingPolicy, CallDetailRecordPolicy, CallEventLogPolicy, CallPolicy)
- Granular permission system with per-user permission assignment
- Admin user management API (CRUD for users, grant/revoke permissions)
- Fail-safe routing defaults

---

## API

NIZAM is API-first — the UI is just an API client. All operations are accessible via REST API with consistent JSON responses via API Resources.

### Authentication

Register, login, and obtain bearer tokens via the Auth API. All other endpoints require authentication via [Laravel Sanctum](https://laravel.com/docs/sanctum) bearer tokens.

```bash
# Register
curl -X POST http://localhost:8080/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Admin","email":"admin@example.com","password":"password","password_confirmation":"password"}'

# Login
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'

# Use token in subsequent requests
curl -H "Authorization: Bearer YOUR_TOKEN" http://localhost:8080/api/tenants
```

### Endpoints

#### Health Check (unauthenticated)
| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/health` | Platform health: app status, ESL connectivity, FreeSWITCH uptime, gateway status |

#### Auth
| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/auth/register` | Register a new user |
| `POST` | `/api/auth/login` | Login and get token |
| `POST` | `/api/auth/logout` | Logout (revoke token) |
| `GET` | `/api/auth/me` | Get authenticated user |
| `GET` | `/api/auth/tokens` | List API tokens |
| `POST` | `/api/auth/tokens` | Create named API token |
| `DELETE` | `/api/auth/tokens/{id}` | Revoke API token |

#### Tenants
| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/tenants` | List tenants (admin: all, user: own tenant only) |
| `POST` | `/api/tenants` | Create tenant (admin only) |
| `GET` | `/api/tenants/{id}` | Get tenant |
| `PUT` | `/api/tenants/{id}` | Update tenant (admin only) |
| `DELETE` | `/api/tenants/{id}` | Delete tenant (admin only) |
| `GET` | `/api/tenants/{id}/settings` | Get tenant settings |
| `PUT` | `/api/tenants/{id}/settings` | Merge-update tenant settings (admin only) |
| `GET` | `/api/tenants/{id}/stats` | Get tenant dashboard statistics |

#### Extensions
| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/tenants/{id}/extensions` | List extensions |
| `POST` | `/api/tenants/{id}/extensions` | Create extension |
| `GET` | `/api/tenants/{id}/extensions/{id}` | Get extension (includes voicemail PIN) |
| `PUT` | `/api/tenants/{id}/extensions/{id}` | Update extension |
| `DELETE` | `/api/tenants/{id}/extensions/{id}` | Delete extension |

#### DIDs, Ring Groups, IVRs, Time Conditions, CDRs, Device Profiles, Bridges
All follow the same CRUD pattern under `/api/tenants/{id}/...`:
- `/dids` — Inbound number routing with layered gateway-aware precedence
- `/ring-groups` — Ring group management with cause-aware fallback routing
- `/ivrs` — IVR menu management
- `/time-conditions` — Time-based routing
- `/bridges` — Reusable outbound bridge targets (`gateway` or `raw`)
- `/cdrs` — Call detail records (read-only: index + show)
- `/cdrs/export` — CDR CSV export with filters (max 10,000 records)
- `/device-profiles` — Device provisioning profiles

#### Webhooks
| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/tenants/{id}/webhooks` | List webhooks |
| `POST` | `/api/tenants/{id}/webhooks` | Create webhook |
| `GET` | `/api/tenants/{id}/webhooks/{id}` | Get webhook |
| `PUT` | `/api/tenants/{id}/webhooks/{id}` | Update webhook |
| `DELETE` | `/api/tenants/{id}/webhooks/{id}` | Delete webhook |

#### Call Operations
| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/tenants/{id}/calls/originate` | Originate a call (internal or gateway-backed with `gateway_id`) |
| `GET` | `/api/tenants/{id}/calls/status` | Get active call status |

#### Call Events & Trace
| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/tenants/{id}/call-events` | List call events (filterable by `call_uuid`, `event_type`, `from`, `to`) |
| `GET` | `/api/tenants/{id}/call-events/{uuid}/trace` | Full lifecycle trace for a specific call UUID |

#### Recordings
| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/tenants/{id}/recordings` | List recordings (filterable by `call_uuid`, `caller_id_number`, `destination_number`, `date_from`, `date_to`) |
| `GET` | `/api/tenants/{id}/recordings/{id}` | Get recording metadata |
| `GET` | `/api/tenants/{id}/recordings/{id}/download` | Download recording file |
| `DELETE` | `/api/tenants/{id}/recordings/{id}` | Delete recording |

#### User Management (Admin Only)
| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/users` | List all users (filterable by `tenant_id`, `role`) |
| `POST` | `/api/users` | Create user |
| `GET` | `/api/users/{id}` | Get user |
| `PUT` | `/api/users/{id}` | Update user |
| `DELETE` | `/api/users/{id}` | Delete user |
| `GET` | `/api/users/{id}/permissions` | List user's permissions |
| `POST` | `/api/users/{id}/permissions/grant` | Grant permissions to user |
| `POST` | `/api/users/{id}/permissions/revoke` | Revoke permissions from user |
| `GET` | `/api/permissions` | List all available permissions |

### Rate Limiting

All authenticated API endpoints are rate-limited to **60 requests per minute** per user (or per IP for unauthenticated endpoints like health). Rate limit headers are included in all responses:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
```

### Event Bus

```
FreeSWITCH → ESL → Event Processor → Redis → WebSocket/API
                                    ↘ CDR Creation
                                    ↘ Call Event Log (persistent)
                                    ↘ Webhook Dispatch
```

Real-time streaming of call lifecycle events. Events are:
- Persisted to `call_events` table for replay and debugging
- Dispatched to matching webhooks via queued jobs
- Broadcast on tenant-scoped private WebSocket channels (`private-tenant.{id}.calls`)
- Available via Server-Sent Events (SSE) at `GET /api/tenants/{id}/call-events/stream`

**Normalized Event Types:**

| Event Type | Source | Description |
|-----------|--------|-------------|
| `call.started` | `CHANNEL_CREATE` | Call initiated |
| `call.answered` | `CHANNEL_ANSWER` | Call answered |
| `call.bridge` | `CHANNEL_BRIDGE` | Call legs bridged (includes `other_leg_uuid`) |
| `call.hangup` | `CHANNEL_HANGUP_COMPLETE` | Call ended (includes `hangup_cause`, `duration`, `billsec`) |
| `call.missed` | `CHANNEL_HANGUP_COMPLETE` | Missed call (hangup cause = `NO_ANSWER`) |
| `voicemail.received` | `CUSTOM vm::maintenance` | New voicemail message |
| `registration.registered` | `CUSTOM sofia::register` | SIP device registered |
| `registration.unregistered` | `CUSTOM sofia::unregister` | SIP device unregistered |

---

## Artisan Commands

| Command | Description |
|---------|-------------|
| `php artisan freeswitch:listen` | Start ESL event listener with auto-reconnection |
| `php artisan freeswitch:listen --max-retries=5` | ESL listener with limited reconnection attempts |
| `php artisan nizam:gateway-status` | Poll and cache FreeSWITCH gateway/registration status |
| `php artisan nizam:sync-permissions` | Sync core + module permissions to database |
| `php artisan make:nizam-module {name}` | Generate a module skeleton with all required hooks |

---

## Technology Stack

| Component | Technology |
|-----------|-----------|
| Media Engine | FreeSWITCH |
| Backend Framework | Laravel 12+ |
| Database | PostgreSQL 16 |
| Cache & Events | Redis 7 |
| API Auth | Laravel Sanctum |
| WebSocket | Laravel Reverb (planned) |
| Deployment | Docker / bare-metal |

---

## Quick Start

### Option A — One-line VPS installer (Ubuntu 22.04 / Debian 12)

Installs everything on a fresh VPS with zero user interaction and prints the URL and admin credentials on completion:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/md-riaz/NIZAM/main/install.sh)
```

> Installs: PHP 8.3, PostgreSQL 16, Redis 7, FreeSWITCH 1.10, nginx, Supervisor, UFW.  
> FreeSWITCH is installed from packages on Debian 12 (~5 min) or compiled from source on Ubuntu 22.04 (~30 min).

See [docs/installation-bare-metal.md](docs/installation-bare-metal.md) for the step-by-step equivalent.

---

### Option B — Docker (recommended for development)

#### Prerequisites

- Docker & Docker Compose
- Git
- `make` (optional, but recommended — run `make help` to see all shortcuts)

#### Setup

```bash
# 1. Clone
git clone https://github.com/md-riaz/NIZAM.git
cd NIZAM

# 2. Copy environment
cp .env.example .env

# 3. Start all 8 services
# The app container will auto-generate APP_KEY on first boot if missing,
# and will build frontend assets automatically when public/build is absent.
docker compose up -d --build

# 4. Run migrations
docker compose exec app php artisan migrate

# 5. (Optional) Seed demo data / bootstrap first admin
# For production-like installs, set ADMIN_EMAIL and ADMIN_PASSWORD in .env first.
docker compose exec app php artisan db:seed
```

Behavior:
- non-production with blank admin env vars: seeds demo login `admin@nizam.local` / `password`
- production with blank admin env vars: seeds demo data but does **not** create a login user
- any environment with `ADMIN_EMAIL` + `ADMIN_PASSWORD` set: creates or updates that admin user idempotently

Or use the **one-step shortcut** (handles steps 2–4 automatically):

```bash
make setup
```

The API will be available at `http://localhost:8091/api/v1` by default.

> **Health check:** `curl http://localhost:8091/api/v1/health`

#### Docker Services

| Service | Container | Port | Description |
|---------|-----------|------|-------------|
| **app** | `nizam-app` | — | PHP-FPM application |
| **nginx** | `nizam-nginx` | `8091` | Web server (reverse proxy) |
| **postgres** | `nizam-postgres` | `5432` | PostgreSQL database |
| **redis** | `nizam-redis` | `6379` | Cache and queue broker |
| **freeswitch** | `nizam-freeswitch` | `5060` (SIP), `7443` (WSS), `8021` (ESL) | Media engine (SIP + WebRTC) |
| **queue** | `nizam-queue` | — | Queue worker (webhook delivery, async jobs) |
| **scheduler** | `nizam-scheduler` | — | Periodic task runner |
| **esl-listener** | `nizam-esl-listener` | — | FreeSWITCH event listener |
| **sip-mock** | `nizam-sip-mock` | `5070/udp` (internal) | Local SIP registrar for repeatable gateway registration tests |

#### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_ENV` | `local` | Application environment |
| `APP_KEY` | — | Auto-generated on first container boot if missing |
| `APP_PORT` | `8091` | Published Docker port for the web UI and API |
| `APP_URL` | `http://localhost:8091` | Public URL of the application |
| `ADMIN_NAME` | `Administrator` | Bootstrap admin display name used by `db:seed` |
| `ADMIN_EMAIL` | blank | Required in production if you want `db:seed` to create the first admin |
| `ADMIN_PASSWORD` | blank | Required in production if you want `db:seed` to create the first admin |
| `ADMIN_TENANT_NAME` | `Demo Company` | Tenant name created by the bootstrap seeder |
| `ADMIN_TENANT_SLUG` | `demo-company` | Tenant slug created by the bootstrap seeder |
| `ADMIN_TENANT_DOMAIN` | `demo.nizam.local` | Tenant domain created by the bootstrap seeder |
| `DB_CONNECTION` | `pgsql` | Database driver |
| `DB_HOST` | `127.0.0.1` | Database host |
| `DB_DATABASE` | `nizam` | Database name |
| `DB_USERNAME` | `nizam` | Database user |
| `DB_PASSWORD` | `secret` | Database password |
| `FREESWITCH_HOST` | `127.0.0.1` | FreeSWITCH ESL host |
| `FREESWITCH_ESL_PORT` | `8021` | FreeSWITCH ESL port |
| `FREESWITCH_ESL_PASSWORD` | `ClueCon` | FreeSWITCH ESL password — **change in production** |
| `NIZAM_XML_CURL_URL` | `http://nginx/freeswitch/xml-curl` | URL FreeSWITCH uses to fetch dialplan from NIZAM |
| `REDIS_HOST` | `127.0.0.1` | Redis host |
| `REDIS_PASSWORD` | blank | Leave blank for local Docker. Set a real password in production |
| `QUEUE_CONNECTION` | `database` | Queue driver. Use `redis` in production if Redis auth/config is in place |

### Option C — Local dev (no Docker)

```bash
composer install
cp .env.example .env
php artisan key:generate
# Set DB_CONNECTION=sqlite for zero-config local testing
php artisan migrate
php artisan serve      # API at http://localhost:8000/api/v1
```

---

## Project Structure

```
NIZAM/
├── app/
│   ├── Console/Commands/       # Artisan commands (freeswitch:listen, nizam:gateway-status, nizam:sync-permissions, make:nizam-module)
│   ├── Events/                 # Event classes (CallEvent)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/            # REST API controllers (13 controllers)
│   │   │   │   ├── AuthController.php
│   │   │   │   ├── TenantController.php
│   │   │   │   ├── ExtensionController.php
│   │   │   │   ├── CallController.php
│   │   │   │   ├── CallEventController.php
│   │   │   │   ├── HealthController.php
│   │   │   │   ├── WebhookController.php
│   │   │   │   └── ...
│   │   │   ├── FreeswitchXmlController.php
│   │   │   └── ProvisioningController.php
│   │   ├── Middleware/          # Custom middleware (EnsureTenantAccess)
│   │   ├── Requests/           # Form request validation (16 classes)
│   │   └── Resources/          # API resource transformers (10 classes)
│   ├── Jobs/                   # Queue jobs (DeliverWebhook)
│   ├── Models/                 # Eloquent models (12 models, all UUID primary keys)
│   ├── Modules/                # Module framework
│   │   ├── Contracts/          # NizamModule interface
│   │   └── ModuleRegistry.php  # Module lifecycle management
│   ├── Observers/              # Model observers (ExtensionObserver)
│   ├── Policies/               # Authorization policies (TenantPolicy, ExtensionPolicy)
│   ├── Providers/              # Service providers
│   ├── Traits/                 # Shared traits (Auditable)
│   └── Services/               # Business logic services
│       ├── DialplanCompiler.php
│       ├── EslConnectionManager.php
│       ├── EventProcessor.php
│       ├── ProvisioningService.php
│       └── WebhookDispatcher.php
├── config/
│   └── nizam.php               # NIZAM configuration
├── database/
│   ├── factories/              # Model factories (10 factories)
│   ├── migrations/             # Database schema (16 migrations)
│   └── seeders/                # Demo data seeder
├── docker/
│   ├── app/                    # PHP-FPM Dockerfile
│   ├── nginx/                  # Nginx configuration
│   └── freeswitch/             # FreeSWITCH container & config
├── routes/
│   ├── api.php                 # API routes (auth, CRUD, calls, events, health)
│   └── web.php                 # Web routes (xml-curl, provisioning)
├── docker-compose.yml          # Container orchestration (6 services)
└── tests/                      # PHPUnit tests (330 tests, 641 assertions)
```

---

## Credential Handling

| Field | Storage | Reason |
|-------|---------|--------|
| Extension `password` | **Plaintext** | SIP credentials stored as plaintext so webphone clients (e.g., sip.js) and the FreeSWITCH directory can use them directly. Included in API responses. |
| Extension `voicemail_pin` | **Plaintext** | Needs to be displayed in API responses and dashboard templates. |
| Webhook `secret` | **Encrypted** (Laravel `encrypted` cast) | HMAC signing secrets must be protected at rest. Hidden from API serialization. |

---

## Architectural Principles

1. **Media and business logic must be separated** — FreeSWITCH handles media, NIZAM handles logic
2. **Database is the source of truth** — No manual XML configuration files
3. **Dialplan is compiled output** — Generated dynamically from database state
4. **API-first always** — Every operation is available via REST API
5. **Multi-tenant by design** — Domain isolation from day one
6. **Modules are plug-in packages** — Extensible via `NizamModule` interface
7. **Observability is mandatory** — Event logging, audit trails, CDR tracking, call trace by UUID
8. **Security by default** — Webhook secret encryption, rate limiting, tenant isolation, audit logging

---

## Future Roadmap

- [ ] Call Queues (ACD)
- [ ] SMS Integration (Bandwidth/Twilio)
- [ ] Billing Module
- [ ] AI Call Analysis
- [ ] Contact Center Features
- [ ] Visual Flow Builder UI
- [ ] Smarter hangup-cause / strategy-aware fallback behavior
- [ ] Richer bridge types and carrier templates
- [ ] External Module SDK
- [ ] API Marketplace

---

## Positioning

NIZAM combines:

- **FreeSWITCH's** runtime media power
- **Wazo's** structured control plane thinking
- **Laravel's** developer ecosystem

More structured than FusionPBX. Simpler to operate than full Wazo microservices. More media-capable than Asterisk-based stacks. Designed for SaaS-ready deployment.

---

## Documentation

| Guide | Description |
|-------|-------------|
| [API Reference](docs/api-reference.md) | Full REST endpoint reference with request/response examples |
| [Environment Bootstrap](docs/environment-bootstrap.md) | Docker setup, FreeSWITCH config, production checklist, Makefile reference |
| [Bare-Metal Installation](docs/installation-bare-metal.md) | Ubuntu/Debian install without Docker (PHP, PostgreSQL, Redis, FreeSWITCH, nginx, supervisor) |
| [`install.sh`](install.sh) | Automated VPS installer — one command, zero interaction, prints URL + credentials |
| [WebRTC Setup](docs/webrtc-setup.md) | WSS/DTLS configuration, SIP.js integration, certificate setup |
| [Module Development](docs/module-development.md) | NizamModule interface and module authoring guide |
| [Deployment & Scaling](docs/deployment-scaling.md) | Production deployment, horizontal scaling, backup/restore |

---

## License

MIT License. See [LICENSE](LICENSE) for details.
