# NIZAM

**NIZAM** — Open Communications Control Platform

> From Arabic: نظام (Nizām) — meaning *system, order, structure*.

NIZAM is an API-first, modular communications platform built on top of [FreeSWITCH](https://freeswitch.com), designed to provide structured automation, integration, and multi-organization telephony control — serving as a modern alternative to FusionPBX and Wazo.

---

## Vision

NIZAM separates concerns into distinct layers:

| Layer | Technology | Responsibility |
|-------|-----------|----------------|
| **Media Core** | FreeSWITCH | SIP signaling, RTP media, WebRTC (WSS/DTLS), call bridging, recording, conferencing |
| **Control Plane** | Laravel 12 | Business logic, organization management, routing, provisioning |
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
│  │ Organization │ │Extension │ │ Routing  │  ...       │
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
2. SIP Profiles are compiled to static XML on disk for deterministic loading
3. Dialplan and Directory compilers serve dynamic state over `mod_xml_curl`
4. No manual XML editing required

---

## Core Features

### Multi-Organization
- Domain-based organization isolation
- Per-organization resource limits
- Scoped authentication via Sanctum
- Role-based authorization (superadmin bypasses organization checks)

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
- Can target bridge and flow destinations in addition to local organization objects

### CDR & Recording
- Indexed call detail records
- UUID correlation with FreeSWITCH
- Recording path tracking
- Recording model with file indexing, download API, and deletion
- CDR CSV export with filtering (`GET /api/organizations/{id}/cdrs/export`)

### Device Provisioning
- Template-based device configs
- Vendor profiles (Polycom, Yealink, Grandstream) with MAC detection
- Auto-provisioning endpoint for phones (`GET /provision/{mac}`)
- Automatic device profile regeneration when extension fields change

### Webhooks
- Outbound event notifications for CRM/ERP integration
- Configurable event subscriptions per organization
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
- Broadcast events via WebSocket channels per organization
- Automatic webhook dispatch on matching events

### Audit Logging
- Automatic create/update/delete tracking on all domain models
- Old and new values stored per change
- User and IP tracking for accountability
- Applied to: Extension, Organization, DID, RingGroup, IVR, TimeCondition, Webhook, DeviceProfile

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
- Designed for real SIP gateway credentials and production-style trunk testing instead of a bundled mock registrar

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
- Organization isolation middleware on all scoped routes
- Role-based authorization policies (OrganizationPolicy, ExtensionPolicy, DidPolicy, RingGroupPolicy, IvrPolicy, TimeConditionPolicy, WebhookPolicy, DeviceProfilePolicy, UserPolicy, RecordingPolicy, CallDetailRecordPolicy, CallEventLogPolicy, CallPolicy)
- Granular permission system with per-user permission assignment
- Admin user management API (CRUD for users, grant/revoke permissions)
- Fail-safe routing defaults

---

## API

NIZAM is API-first — the UI is just an API client. All operations are accessible via REST API with consistent JSON responses via API Resources.

### Authentication

Login and obtain bearer tokens via the Auth API. Self-registration is not supported. Organizations are provisioned manually by Superadmin users, and users are created under an existing organization by an authorized administrator.

```bash
# Login
curl -X POST http://localhost:8231/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'

# Use token in subsequent requests
curl -H "Authorization: Bearer YOUR_TOKEN" http://localhost:8231/api/v1/organizations
```

### Endpoints

#### Health Check (unauthenticated)
| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/health` | Platform health: app status, ESL connectivity, FreeSWITCH uptime, gateway status |

#### Auth
| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/auth/login` | Login and get token |
| `POST` | `/api/auth/login` | Login and get token |
| `POST` | `/api/auth/logout` | Logout (revoke token) |
| `GET` | `/api/auth/me` | Get authenticated user |
| `GET` | `/api/auth/tokens` | List API tokens |
| `POST` | `/api/auth/tokens` | Create named API token |
| `DELETE` | `/api/auth/tokens/{id}` | Revoke API token |

#### Organizations
| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/organizations` | List organizations (superadmin: all, organization users: own organization only) |
| `POST` | `/api/organizations` | Create organization (superadmin only) |
| `GET` | `/api/organizations/{id}` | Get organization |
| `PUT` | `/api/organizations/{id}` | Update organization (superadmin only) |
| `DELETE` | `/api/organizations/{id}` | Delete organization (superadmin only) |
| `GET` | `/api/organizations/{id}/settings` | Get organization settings |
| `PUT` | `/api/organizations/{id}/settings` | Merge-update organization settings (superadmin only) |
| `GET` | `/api/organizations/{id}/stats` | Get organization dashboard statistics |

#### Extensions
| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/organizations/{id}/extensions` | List extensions |
| `POST` | `/api/organizations/{id}/extensions` | Create extension |
| `GET` | `/api/organizations/{id}/extensions/{id}` | Get extension (includes voicemail PIN) |
| `PUT` | `/api/organizations/{id}/extensions/{id}` | Update extension |
| `DELETE` | `/api/organizations/{id}/extensions/{id}` | Delete extension |

#### DIDs, Ring Groups, IVRs, Time Conditions, CDRs, Device Profiles, Bridges
All follow the same CRUD pattern under `/api/organizations/{id}/...`:
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
| `GET` | `/api/organizations/{id}/webhooks` | List webhooks |
| `POST` | `/api/organizations/{id}/webhooks` | Create webhook |
| `GET` | `/api/organizations/{id}/webhooks/{id}` | Get webhook |
| `PUT` | `/api/organizations/{id}/webhooks/{id}` | Update webhook |
| `DELETE` | `/api/organizations/{id}/webhooks/{id}` | Delete webhook |

#### Call Operations
| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/organizations/{id}/calls/originate` | Originate a call (internal or gateway-backed with `gateway_id`) |
| `GET` | `/api/organizations/{id}/calls/status` | Get active call status |

#### Call Events & Trace
| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/organizations/{id}/call-events` | List call events (filterable by `call_uuid`, `event_type`, `from`, `to`) |
| `GET` | `/api/organizations/{id}/call-events/{uuid}/trace` | Full lifecycle trace for a specific call UUID |

#### Recordings
| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/organizations/{id}/recordings` | List recordings (filterable by `call_uuid`, `caller_id_number`, `destination_number`, `date_from`, `date_to`) |
| `GET` | `/api/organizations/{id}/recordings/{id}` | Get recording metadata |
| `GET` | `/api/organizations/{id}/recordings/{id}/download` | Download recording file |
| `DELETE` | `/api/organizations/{id}/recordings/{id}` | Delete recording |

#### User Management (Admin Only)
| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/users` | List all users (filterable by `organization_id`, `role`) |
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
- Broadcast on organization-scoped private WebSocket channels (`private-organization.{id}.calls`)
- Available via Server-Sent Events (SSE) at `GET /api/organizations/{id}/call-events/stream`

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
- non-production with blank admin env vars: seeds two default logins:
  - **Platform Superadmin:** `admin@nizam.io` / `password` (has global cross-organization access)
  - **Organization Admin:** `admin@nizam.local` / `password` (scoped purely to the "Nizam Communications" demo organization)
- production with blank admin env vars: seeds demo structure but does **not** create default login users (for security)
- any environment with `ADMIN_EMAIL` + `ADMIN_PASSWORD` set in `.env`: securely creates or updates that exact platform admin user instead!

Or use the **one-step shortcut** (handles steps 2–4 automatically):

```bash
make setup
```

The API will be available at `http://localhost:8231/api/v1` by default.

> **Health check:** `curl http://localhost:8231/api/v1/health`

#### Docker Services

| Service | Container | Port | Description |
|---------|-----------|------|-------------|
| **app** | `app` | — | PHP-FPM application |
| **nginx** | `nginx` | `8231` | Web server (reverse proxy) |
| **postgres** | `postgres` | `5432` | PostgreSQL database |
| **redis** | `redis` | `6379` | Cache and queue broker |
| **freeswitch** | `freeswitch` | `5060` (SIP), `7443` (WSS) | Media engine (SIP + WebRTC) |
| **queue** | `queue` | — | Queue worker (webhook delivery, async jobs) |
| **scheduler** | `scheduler` | — | Periodic task runner |
| **esl-listener** | `esl-listener` | — | FreeSWITCH event listener |

#### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_ENV` | `local` | Application environment |
| `APP_KEY` | — | Set in `.env` before startup |
| `APP_PORT` | `8231` | Published Docker port for the web UI and API |
| `APP_URL` | `http://localhost:8231` | Public URL of the application |
| `ADMIN_NAME` | `Administrator` | Bootstrap admin display name used by `db:seed` |
| `ADMIN_EMAIL` | blank | Required in production if you want `db:seed` to create the first admin |
| `ADMIN_PASSWORD` | blank | Required in production if you want `db:seed` to create the first admin |
| `ADMIN_ORGANIZATION_NAME` | `Demo Company` | Organization name created by the bootstrap seeder |
| `ADMIN_ORGANIZATION_DOMAIN` | `demo.app.local` | Organization domain created by the bootstrap seeder |
| `DB_CONNECTION` | `pgsql` | Database driver |
| `DB_HOST` | `127.0.0.1` | Database host |
| `DB_DATABASE` | `communications` | Database name |
| `DB_USERNAME` | `communications` | Database user |
| `DB_PASSWORD` | `secret` | Database password |
| `SESSION_DRIVER` | `database` | Session driver used by both Docker and bare-metal defaults |
| `CACHE_STORE` | `database` | Cache store used by both Docker and bare-metal defaults |
| `QUEUE_CONNECTION` | `database` | Queue driver used by both Docker and bare-metal defaults |
| `FREESWITCH_HOST` | `127.0.0.1` | FreeSWITCH ESL host |
| `FREESWITCH_ESL_PORT` | `8021` | FreeSWITCH ESL port |
| `FREESWITCH_ESL_PASSWORD` | `ClueCon` | FreeSWITCH ESL password — **change in production** |
| `FREESWITCH_XML_CURL_URL` | `http://nginx/freeswitch/xml-curl` | URL FreeSWITCH uses to fetch dialplan and directory XML |
| `FREESWITCH_XML_CURL_ENDPOINT_INTERNAL` | `http://nginx/freeswitch/xml-curl` | Internal endpoint injected into the FreeSWITCH container |
| `FREESWITCH_LOG_PATH` | `/var/log/freeswitch/freeswitch.log` | FreeSWITCH log path used by the admin log viewer |
| `REDIS_HOST` | `127.0.0.1` | Redis host |
| `REDIS_PASSWORD` | blank | Leave blank for local Docker. Set a real password in production |

On first boot, Docker initializes PostgreSQL with `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` from `.env`. If you change those values later, you must either recreate the Postgres volume or update the database role and password inside the existing cluster.

#### Estimated Disk Footprint

The exact footprint depends mostly on call recordings and CDR retention, but the Docker stack is predictable enough to budget up front.

##### Base platform footprint before call recordings

| Component | What it stores | Typical size on disk |
|----------|----------------|----------------------|
| Laravel app image | PHP 8.3, Composer deps, Node build output, app code | ~0.8 to 1.5 GB |
| FreeSWITCH image | FreeSWITCH 1.10 build, sounds, modules, runtime libs | ~1.5 to 2.5 GB |
| PostgreSQL image + data volume | PostgreSQL engine plus schema and app data | ~0.3 GB image + 0.5 to 5 GB data |
| Redis image + data volume | Redis engine plus cache, sessions, queue state | ~0.05 GB image + 0.1 to 1 GB data |
| Nginx image | Reverse proxy only | ~0.05 GB |
| Laravel logs and app storage | `storage/logs`, exports, generated files | ~0.1 to 1 GB |
| FreeSWITCH logs and runtime files | FreeSWITCH logs, runtime state, generated XML | ~0.1 to 1 GB |
| Docker overhead and named volumes | local layer cache and volume metadata | ~0.5 to 2 GB |

That puts a fresh non-recording deployment at roughly:

- small lab or dev host: 4 to 7 GB
- small production host with history retained: 6 to 12 GB
- safer minimum host disk budget: 20 GB
- recommended production disk budget: 40 GB or more

##### Recordings are the real storage driver

If call recording is enabled, `storage/app` becomes the dominant consumer.

| Recording profile | Estimated extra disk |
|------------------|----------------------|
| light usage or short retention | 5 to 20 GB |
| moderate production | 20 to 100 GB |
| heavy recording retention | 100 GB+ |

Practical rule: size the base platform first, then add recording retention separately. If you keep long-term recordings, plan object storage or a dedicated archive policy instead of relying only on the app host disk.

##### Per-area breakdown for planning

- FreeSWITCH: usually 1.5 to 3.5 GB including image, sounds, modules, logs, and runtime data
- Laravel app: usually 1 to 2 GB including image layers, vendor dependencies, built frontend assets, and logs
- PostgreSQL: starts small, but CDRs, events, audit logs, queue tables, and organization data will steadily grow; 5 to 20 GB is a sensible early production budget
- Redis: generally small unless you retain a lot of transient data; usually under 1 GB
- Certbot and TLS material: usually well under 0.5 GB
- Recordings: unbounded relative to the rest of the stack, so treat them as a separate capacity plan

#### Deploy on the Current Host with Docker

If this host already has Docker and Docker Compose available, this is the shortest production-style path.

##### 1. Prepare the environment

```bash
cp .env.example .env
```

Set these before first boot:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://your-domain`
- `APP_PORT=8231` or another host port if needed
- `APP_KEY=base64:...`
- `DB_PASSWORD=` strong password
- `REDIS_PASSWORD=` strong password
- `FREESWITCH_ESL_PASSWORD=` strong password
- `FREESWITCH_SIP_PORT=5060` unless the host already uses it
- `FREESWITCH_EXTERNAL_SIP_PORT=5080` unless the host already uses it
- `FREESWITCH_WSS_PORT=7443` unless the host already uses it
- `FREESWITCH_RTP_PORT_RANGE=16384-16484` unless the host already uses it
- `ADMIN_EMAIL=` desired bootstrap admin email
- `ADMIN_PASSWORD=` desired bootstrap admin password

Generate an app key if needed:

```bash
docker run --rm -v "$PWD":/app -w /app php:8.3-alpine php artisan key:generate --show
```

##### 2. Build and start the stack

```bash
docker compose up -d --build
```

##### 3. Initialize the database

```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
```

##### 4. Verify the deployment

```bash
docker compose ps
curl http://localhost:8231/api/v1/health
```

##### 5. Useful operational checks

```bash
docker compose logs -f nginx
docker compose logs -f app
docker compose logs -f freeswitch
docker compose logs -f esl-listener
```

##### Notes for real production hosts

- The compose stack publishes SIP, WSS, and RTP directly from the FreeSWITCH container, so the host firewall and cloud security group need to allow the required ports.
- If the host already uses `5060`, `5080`, `7443`, or the RTP range, set `FREESWITCH_SIP_PORT`, `FREESWITCH_EXTERNAL_SIP_PORT`, `FREESWITCH_WSS_PORT`, or `FREESWITCH_RTP_PORT_RANGE` in `.env` before starting the stack.
- Keep `8021` internal only. FreeSWITCH ESL should not be exposed publicly.
- `postgres` and `redis` are bound to `127.0.0.1` in the compose file, which is good for a single-host deployment.
- For long-term production, recordings should be rotated or offloaded. They will outgrow the rest of the platform quickly.
- If this machine already runs another reverse proxy, map `APP_PORT` differently and terminate TLS there, or adapt the nginx service accordingly.

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
│   │   │   │   ├── OrganizationController.php
│   │   │   │   ├── ExtensionController.php
│   │   │   │   ├── CallController.php
│   │   │   │   ├── CallEventController.php
│   │   │   │   ├── HealthController.php
│   │   │   │   ├── WebhookController.php
│   │   │   │   └── ...
│   │   │   ├── FreeswitchXmlController.php
│   │   │   └── ProvisioningController.php
│   │   ├── Middleware/          # Custom middleware (EnsureOrganizationAccess)
│   │   ├── Requests/           # Form request validation (16 classes)
│   │   └── Resources/          # API resource transformers (10 classes)
│   ├── Jobs/                   # Queue jobs (DeliverWebhook)
│   ├── Models/                 # Eloquent models (12 models, all UUID primary keys)
│   ├── Modules/                # Module framework
│   │   ├── Contracts/          # NizamModule interface
│   │   └── ModuleRegistry.php  # Module lifecycle management
│   ├── Observers/              # Model observers (ExtensionObserver)
│   ├── Policies/               # Authorization policies (OrganizationPolicy, ExtensionPolicy)
│   ├── Providers/              # Service providers
│   ├── Traits/                 # Shared traits (Auditable)
│   └── Services/               # Business logic services
│       ├── DialplanCompiler.php
│       ├── EslConnectionManager.php
│       ├── EventProcessor.php
│       ├── ProvisioningService.php
│       └── WebhookDispatcher.php
├── config/
│   └── telephony.php           # Telephony and FreeSWITCH runtime configuration
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
5. **Multi-organization by design** — Domain isolation from day one
6. **Modules are plug-in packages** — Extensible via `NizamModule` interface
7. **Observability is mandatory** — Event logging, audit trails, CDR tracking, call trace by UUID
8. **Security by default** — Webhook secret encryption, rate limiting, organization isolation, audit logging

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
