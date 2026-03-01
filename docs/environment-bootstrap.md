# Environment Bootstrap Guide

This guide walks through setting up a NIZAM development environment from scratch.

---

## Prerequisites

| Tool | Minimum Version | Purpose |
|------|----------------|---------|
| Docker | 24+ | Container runtime |
| Docker Compose | 2.20+ | Service orchestration |
| Git | 2.30+ | Source control |
| Make | any | Convenience shortcuts (`make help`) |

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

### 2. Generate APP_KEY

**The application key must be in `.env` before starting services.**  
Laravel's encryption layer requires it at boot time.

```bash
# Using make (recommended)
make setup          # handles everything in one step

# Or manually:
php artisan key:generate --show     # copy the output
# Edit .env and paste it as APP_KEY=base64:...
```

> If you don't have PHP installed locally, you can use Docker:
> ```bash
> docker run --rm -v "$PWD":/app -w /app php:8.3-alpine \
>   php artisan key:generate --show
> ```
> Paste the output into `.env` as `APP_KEY=base64:…`

### 3. Start Services

```bash
docker compose up -d
```

This starts 8 containers:

| Container | Service | Port |
|-----------|---------|------|
| `nizam-app` | PHP-FPM (Laravel) | — |
| `nizam-nginx` | Web server | `8080` |
| `nizam-postgres` | PostgreSQL | `5432` |
| `nizam-redis` | Redis | `6379` |
| `nizam-freeswitch` | FreeSWITCH | `5060` (SIP), `8021` (ESL) |
| `nizam-queue` | Queue worker | — |
| `nizam-scheduler` | Task scheduler | — |
| `nizam-esl-listener` | FreeSWITCH event listener | — |

### 4. Initialize Application

```bash
# Run database migrations
docker compose exec app php artisan migrate

# Seed demo data (optional)
docker compose exec app php artisan db:seed
```

Or use the Makefile shortcut:

```bash
make migrate
make seed
```

### 5. Verify

```bash
# Check health endpoint
curl http://localhost:8080/api/v1/health

# Using make
make health

# Expected response:
# {
#   "status": "healthy",
#   "checks": {
#     "app":      { "status": "ok" },
#     "database": { "status": "ok" },
#     "cache":    { "status": "ok" },
#     "esl":      { "connected": true,  "status": "ok" },
#     "gateways": { "status": "ok" }
#   }
# }
```

### 6. Register First User

```bash
curl -X POST http://localhost:8080/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Admin",
    "email": "admin@example.com",
    "password": "password",
    "password_confirmation": "password"
  }'
```

---

## Makefile Quick Reference

Run `make help` to list all targets:

```
make setup          # First-time setup (copies .env, generates key, builds, migrates)
make up             # Start all services
make down           # Stop all services
make logs           # Tail all logs
make health         # Check /api/v1/health
make shell          # Open shell inside app container
make migrate        # Run database migrations
make test           # Run test suite
make lint / fix     # Check / fix code style
make backup-db      # Dump PostgreSQL to ./backups/
```

---

## Local Development (Without Docker)

For full instructions see: [docs/installation-bare-metal.md](installation-bare-metal.md)

### Quick start

```bash
# Install PHP dependencies
composer install
cp .env.example .env
php artisan key:generate

# Configure for SQLite (easiest for local dev):
# DB_CONNECTION=sqlite  (comment out other DB_ lines)

php artisan migrate
php artisan serve
# API available at http://localhost:8000/api/v1
```

---

## FreeSWITCH Configuration

NIZAM integrates with FreeSWITCH via two mechanisms:

### mod_xml_curl (Dynamic Configuration)

FreeSWITCH fetches directory and dialplan XML from NIZAM at runtime.

In Docker, this is handled automatically via `NIZAM_XML_CURL_URL`.  
For bare-metal, edit `/etc/freeswitch/autoload_configs/xml_curl.conf.xml`:

```xml
<configuration name="xml_curl.conf" description="cURL XML Gateway">
  <bindings>
    <binding name="directory">
      <param name="gateway-url" value="http://127.0.0.1/freeswitch/xml-curl" bindings="directory"/>
    </binding>
    <binding name="dialplan">
      <param name="gateway-url" value="http://127.0.0.1/freeswitch/xml-curl" bindings="dialplan"/>
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

# Or via Docker
make test

# Specific test file
php artisan test tests/Feature/Api/ExtensionApiTest.php
make test-file F=tests/Feature/Api/ExtensionApiTest.php

# With coverage
php artisan test --coverage
```

### Code Style

```bash
# Check code style
vendor/bin/pint --test    # or: make lint

# Fix code style
vendor/bin/pint           # or: make fix
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
- [ ] Sync permissions with `php artisan nizam:sync-permissions`

---

## Backup & Restore

### Database Backup

```bash
# Via Docker
make backup-db

# Manual
docker compose exec postgres pg_dump -U nizam nizam > backup_$(date +%Y%m%d_%H%M%S).sql
```

### Database Restore

```bash
# Via make
make restore-db F=backups/nizam_20260101.sql.gz

# Manual
gunzip -c backup_20260101.sql.gz | docker compose exec -T postgres psql -U nizam nizam
```

### FreeSWITCH Config Backup

NIZAM generates all FreeSWITCH config dynamically from the database, so backing up the database is sufficient. The `docker/freeswitch/` directory contains the base config templates.

### Redis Cache

Redis is used for caching only (gateway status, rate limits). It does not need backup — caches rebuild automatically.

### Automated Backup Schedule

Add to your crontab or task scheduler:

```bash
# Daily database backup at 2:00 AM, keep 7 days
0 2 * * * cd /path/to/nizam && docker compose exec -T postgres pg_dump -U nizam nizam | gzip > backups/nizam_$(date +\%Y\%m\%d).sql.gz 2>> backups/backup.log && find backups/ -name "*.sql.gz" -mtime +7 -delete || echo "[$(date)] Backup failed" >> backups/backup.log
```
