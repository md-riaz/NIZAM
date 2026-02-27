# NIZAM

**NIZAM** — Open Communications Control Platform

> From Arabic: نظام (Nizām) — meaning *system, order, structure*.

NIZAM is an API-first, modular communications platform built on top of [FreeSWITCH](https://freeswitch.com), designed to provide structured automation, integration, and multi-tenant telephony control — serving as a modern alternative to FusionPBX and Wazo.

---

## Vision

NIZAM separates concerns into distinct layers:

| Layer | Technology | Responsibility |
|-------|-----------|----------------|
| **Media Core** | FreeSWITCH | SIP signaling, RTP media, call bridging, recording, conferencing |
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

### Extensions
- SIP user management with credential encryption
- Voicemail settings and caller ID control

### Inbound Routing (DIDs)
- DID → Destination mapping
- Destination types: Extension, Ring Group, IVR, Time Condition, Voicemail

### Ring Groups
- Simultaneous and sequential strategies
- Configurable timeout with fallback routing

### IVR Menus
- Prompt upload support
- Digit-to-destination mapping
- Timeout routing

### Time Conditions
- Office hours logic with day/time rules
- Match and no-match destination routing

### CDR & Recording
- Indexed call detail records
- UUID correlation with FreeSWITCH
- Recording path tracking

### Device Provisioning
- Template-based device configs
- Vendor profiles (Polycom, Yealink, Grandstream) with MAC detection
- Auto-provisioning endpoint for phones (`GET /provision/{mac}`)

### Webhooks
- Outbound event notifications for CRM/ERP integration
- Configurable event subscriptions per tenant
- HMAC-SHA256 signed payloads for security
- Queued delivery with exponential backoff retry
- Events: `call.started`, `call.answered`, `call.missed`, `call.hangup`, `voicemail.received`, `device.registered`

### Event Bus
- FreeSWITCH ESL event listener (`php artisan nizam:esl-listen`)
- Real-time call event processing and CDR creation
- Broadcast events via WebSocket channels per tenant
- Automatic webhook dispatch on matching events

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

#### Auth
| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/auth/register` | Register a new user |
| `POST` | `/api/auth/login` | Login and get token |
| `POST` | `/api/auth/logout` | Logout (revoke token) |
| `GET` | `/api/auth/me` | Get authenticated user |

#### Tenants
| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/tenants` | List tenants |
| `POST` | `/api/tenants` | Create tenant |
| `GET` | `/api/tenants/{id}` | Get tenant |
| `PUT` | `/api/tenants/{id}` | Update tenant |
| `DELETE` | `/api/tenants/{id}` | Delete tenant |

#### Extensions
| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/tenants/{id}/extensions` | List extensions |
| `POST` | `/api/tenants/{id}/extensions` | Create extension |
| `GET` | `/api/tenants/{id}/extensions/{id}` | Get extension |
| `PUT` | `/api/tenants/{id}/extensions/{id}` | Update extension |
| `DELETE` | `/api/tenants/{id}/extensions/{id}` | Delete extension |

#### DIDs, Ring Groups, IVRs, Time Conditions, CDRs, Device Profiles
All follow the same CRUD pattern under `/api/tenants/{id}/...`:
- `/dids` — Inbound number routing
- `/ring-groups` — Ring group management
- `/ivrs` — IVR menu management
- `/time-conditions` — Time-based routing
- `/cdrs` — Call detail records (read-only)
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
| `POST` | `/api/tenants/{id}/calls/originate` | Originate a call |
| `GET` | `/api/tenants/{id}/calls/status` | Get active call status |

### Event Bus

```
FreeSWITCH → ESL → Event Processor → Redis → WebSocket/API
                                    ↘ CDR Creation
                                    ↘ Webhook Dispatch
```

Real-time streaming of call start, answer, hangup, and voicemail events. Events are automatically dispatched to matching webhooks and broadcast on tenant-scoped WebSocket channels.

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
| Deployment | Docker |

---

## Quick Start

### Prerequisites

- Docker & Docker Compose
- Git

### Setup

```bash
# Clone the repository
git clone https://github.com/md-riaz/NIZAM.git
cd NIZAM

# Copy environment file
cp .env.example .env

# Start services
docker compose up -d

# Run migrations
docker compose exec app php artisan migrate

# Generate application key
docker compose exec app php artisan key:generate

# Seed demo data (optional)
docker compose exec app php artisan db:seed

# Start ESL event listener (connects to FreeSWITCH)
docker compose exec app php artisan nizam:esl-listen
```

The API will be available at `http://localhost:8080/api`.

Demo credentials (after seeding): `admin@nizam.local` / `password`

### Local Development (without Docker)

```bash
# Install PHP dependencies
composer install

# Copy and configure environment
cp .env.example .env
php artisan key:generate

# Run migrations (uses SQLite by default for local dev)
php artisan migrate

# Start development server
php artisan serve
```

---

## Project Structure

```
NIZAM/
├── app/
│   ├── Console/Commands/       # Artisan commands (nizam:esl-listen)
│   ├── Events/                 # Event classes (CallEvent)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/            # REST API controllers
│   │   │   │   ├── AuthController.php
│   │   │   │   ├── TenantController.php
│   │   │   │   ├── ExtensionController.php
│   │   │   │   ├── CallController.php
│   │   │   │   ├── WebhookController.php
│   │   │   │   └── ...
│   │   │   ├── FreeswitchXmlController.php
│   │   │   └── ProvisioningController.php
│   │   ├── Requests/           # Form request validation (14 classes)
│   │   └── Resources/          # API resource transformers (8 classes)
│   ├── Jobs/                   # Queue jobs (DeliverWebhook)
│   ├── Models/                 # Eloquent models (9 models)
│   ├── Providers/              # Service providers
│   └── Services/               # Business logic services
│       ├── DialplanCompiler.php
│       ├── EslConnectionManager.php
│       ├── EventProcessor.php
│       ├── ProvisioningService.php
│       └── WebhookDispatcher.php
├── config/
│   └── nizam.php               # NIZAM configuration
├── database/
│   ├── factories/              # Model factories (9 factories)
│   ├── migrations/             # Database schema (13 migrations)
│   └── seeders/                # Demo data seeder
├── docker/
│   ├── app/                    # PHP-FPM Dockerfile
│   ├── nginx/                  # Nginx configuration
│   └── freeswitch/             # FreeSWITCH container & config
├── routes/
│   ├── api.php                 # API routes (auth, CRUD, calls)
│   └── web.php                 # Web routes (xml-curl, provisioning)
├── docker-compose.yml          # Container orchestration
└── tests/                      # PHPUnit tests (82 tests, 172 assertions)
```

---

## Architectural Principles

1. **Media and business logic must be separated** — FreeSWITCH handles media, NIZAM handles logic
2. **Database is the source of truth** — No manual XML configuration files
3. **Dialplan is compiled output** — Generated dynamically from database state
4. **API-first always** — Every operation is available via REST API
5. **Multi-tenant by design** — Domain isolation from day one
6. **Modules are plug-in packages** — Extensible via Laravel packages
7. **Observability is mandatory** — Events, logging, and CDR tracking

---

## Future Roadmap

- [ ] Call Queues (ACD)
- [ ] WebRTC Gateway
- [ ] SMS Integration (Bandwidth/Twilio)
- [ ] Billing Module
- [ ] AI Call Analysis
- [ ] Contact Center Features
- [ ] Visual Flow Builder UI
- [ ] Policy Engine
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

## License

MIT License. See [LICENSE](LICENSE) for details.
