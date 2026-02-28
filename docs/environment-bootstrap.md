# Environment Bootstrap Guide

This guide walks through setting up a NIZAM development environment from scratch.

---

## Prerequisites

| Tool | Minimum Version | Purpose |
|------|----------------|---------|
| Docker | 24+ | Container runtime |
| Docker Compose | 2.20+ | Service orchestration |
| Git | 2.30+ | Source control |

For local development without Docker:
- PHP 8.2+
- Composer 2.x
- PostgreSQL 16 (or SQLite for quick testing)
- Redis 7 (optional, for queue/cache)

---

## Docker Setup (Recommended)

### 1. Clone and Configure

```bash
git clone https://github.com/md-riaz/NIZAM.git
cd NIZAM
cp .env.example .env
```

### 2. Start Services

```bash
docker compose up -d
```

This starts 6 containers:

| Container | Service | Port |
|-----------|---------|------|
| `nizam-app` | PHP-FPM (Laravel) | — |
| `nizam-nginx` | Web server | `8080` |
| `nizam-postgres` | PostgreSQL | `5432` |
| `nizam-redis` | Redis | `6379` |
| `nizam-freeswitch` | FreeSWITCH | `5060` (SIP), `8021` (ESL) |
| `nizam-queue` | Queue worker | — |

### 3. Initialize Application

```bash
# Generate application key (required for encryption)
docker compose exec app php artisan key:generate

# Run database migrations
docker compose exec app php artisan migrate

# Seed demo data (optional)
docker compose exec app php artisan db:seed
```

### 4. Start Event Listener

```bash
# In a separate terminal — connects to FreeSWITCH ESL
docker compose exec app php artisan nizam:esl-listen
```

### 5. Verify

```bash
# Check health endpoint
curl http://localhost:8080/api/health

# Expected response (when FreeSWITCH is running):
# {"status":"healthy","checks":{"app":{"status":"ok"},"esl":{"connected":true,...},"gateways":{...}}}
```

### 6. Register First User

```bash
curl -X POST http://localhost:8080/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Admin",
    "email": "admin@example.com",
    "password": "password",
    "password_confirmation": "password"
  }'
```

---

## Local Development (Without Docker)

### 1. Install Dependencies

```bash
composer install
cp .env.example .env
php artisan key:generate
```

### 2. Configure Database

For quick testing, SQLite works out of the box:

```env
DB_CONNECTION=sqlite
# DB_DATABASE is auto-set to database/database.sqlite
```

For PostgreSQL (recommended for production parity):

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=nizam
DB_USERNAME=nizam
DB_PASSWORD=secret
```

### 3. Run Migrations

```bash
php artisan migrate
php artisan db:seed  # optional
```

### 4. Start Development Server

```bash
php artisan serve
# API available at http://localhost:8000/api
```

---

## FreeSWITCH Configuration

NIZAM integrates with FreeSWITCH via two mechanisms:

### mod_xml_curl (Dynamic Configuration)

FreeSWITCH fetches directory and dialplan XML from NIZAM at runtime:

```xml
<!-- In freeswitch/autoload_configs/xml_curl.conf.xml -->
<configuration name="xml_curl.conf" description="cURL XML Gateway">
  <bindings>
    <binding name="directory">
      <param name="gateway-url" value="http://nizam-nginx/freeswitch/xml-curl" bindings="directory"/>
    </binding>
    <binding name="dialplan">
      <param name="gateway-url" value="http://nizam-nginx/freeswitch/xml-curl" bindings="dialplan"/>
    </binding>
  </bindings>
</configuration>
```

### mod_event_socket (ESL)

NIZAM listens for real-time events from FreeSWITCH:

```env
FREESWITCH_HOST=127.0.0.1      # or 'freeswitch' in Docker
FREESWITCH_ESL_PORT=8021
FREESWITCH_ESL_PASSWORD=ClueCon  # Change in production!
```

The ESL listener subscribes to:
- `CHANNEL_CREATE` — Call initiated
- `CHANNEL_ANSWER` — Call answered
- `CHANNEL_BRIDGE` — Call legs bridged
- `CHANNEL_HANGUP_COMPLETE` — Call ended
- `CUSTOM` — Registration events (`sofia::register`, `sofia::unregister`)

---

## Running Tests

```bash
# Full test suite (uses SQLite in-memory)
php artisan test

# Specific test file
php artisan test tests/Feature/Api/ExtensionApiTest.php

# With coverage
php artisan test --coverage
```

### Code Style

```bash
# Check code style
vendor/bin/pint --test

# Fix code style
vendor/bin/pint
```

---

## Production Checklist

- [ ] Set `APP_ENV=production` and `APP_DEBUG=false`
- [ ] Generate a strong `APP_KEY`
- [ ] Change `FREESWITCH_ESL_PASSWORD` from default
- [ ] Use Redis for `QUEUE_CONNECTION` and `CACHE_STORE`
- [ ] Configure proper PostgreSQL credentials
- [ ] Set up SSL/TLS termination (e.g., via nginx or load balancer)
- [ ] Run `php artisan config:cache` and `php artisan route:cache`
- [ ] Schedule `nizam:gateway-status` in cron for periodic health checks
- [ ] Monitor ESL listener process (systemd, supervisor, or Docker restart policy)
