# Deployment & Scaling Guide

Production deployment and horizontal scaling strategies for NIZAM.

## Architecture Overview

```
┌──────────────┐     ┌──────────────────┐     ┌───────────────┐
│   Load       │────▶│  NIZAM API (N)   │────▶│  PostgreSQL   │
│   Balancer   │     │  Laravel Octane   │     │  Primary +    │
│   (nginx/    │     │  (stateless)      │     │  Read Replica │
│    HAProxy)  │     └──────────────────┘     └───────────────┘
└──────────────┘              │                        │
       │                      ▼                        │
       │              ┌──────────────────┐             │
       │              │  Redis Cluster   │◀────────────┘
       │              │  (cache/queue)   │
       │              └──────────────────┘
       │                      │
       ▼                      ▼
┌──────────────┐     ┌──────────────────┐
│  WebSocket   │     │  Queue Workers   │
│  (Reverb/    │     │  (N instances)   │
│   Pusher)    │     └──────────────────┘
└──────────────┘              │
                              ▼
                    ┌──────────────────┐
                    │  FreeSWITCH      │
                    │  (single/multi)  │
                    └──────────────────┘
```

## Production Deployment

### Prerequisites

- Docker Engine 24+ or Kubernetes 1.28+
- PostgreSQL 15+ (managed or self-hosted)
- Redis 7+ (managed or self-hosted)
- TLS certificates for API and SIP
- DNS configured for SIP domain

### Environment Variables (Production)

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.nizam.example.com

# Database — use managed PostgreSQL in production
DB_CONNECTION=pgsql
DB_HOST=your-rds-endpoint.region.rds.amazonaws.com
DB_PORT=5432
DB_DATABASE=nizam
DB_USERNAME=nizam_app
DB_PASSWORD=<strong-password>

# Redis — use managed Redis (ElastiCache, Redis Cloud, etc.)
REDIS_HOST=your-redis-endpoint
REDIS_PASSWORD=<redis-password>
REDIS_PORT=6379

# Queue — Redis driver for production
QUEUE_CONNECTION=redis

# Cache
CACHE_STORE=redis

# Session
SESSION_DRIVER=redis

# FreeSWITCH ESL
ESL_HOST=freeswitch.internal
ESL_PORT=8021
ESL_PASSWORD=<esl-password>

# Sanctum
SANCTUM_STATEFUL_DOMAINS=dashboard.nizam.example.com

# Rate limiting
API_RATE_LIMIT=60
```

### Docker Production Build

```dockerfile
# Multi-stage production build
FROM php:8.3-fpm AS base
RUN apt-get update && apt-get install -y libpq-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql zip opcache

FROM base AS composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

FROM base AS production
COPY --from=composer /var/www/html/vendor ./vendor
COPY . .
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache
EXPOSE 9000
CMD ["php-fpm"]
```

### Docker Compose (Production)

```yaml
services:
  app:
    build:
      context: .
      target: production
    deploy:
      replicas: 3
      resources:
        limits:
          cpus: '1.0'
          memory: 512M
    environment:
      - APP_ENV=production
    depends_on:
      - redis
      - postgres

  nginx:
    image: nginx:alpine
    ports:
      - "443:443"
    volumes:
      - ./docker/nginx/production.conf:/etc/nginx/conf.d/default.conf
      - /etc/letsencrypt:/etc/letsencrypt:ro
    depends_on:
      - app

  queue-worker:
    build:
      context: .
      target: production
    command: php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
    deploy:
      replicas: 2
    depends_on:
      - redis
      - postgres

  scheduler:
    build:
      context: .
      target: production
    command: php artisan schedule:work
    deploy:
      replicas: 1

  postgres:
    image: postgres:16-alpine
    volumes:
      - pgdata:/var/lib/postgresql/data
    environment:
      POSTGRES_DB: nizam
      POSTGRES_USER: nizam_app
      POSTGRES_PASSWORD: ${DB_PASSWORD}

  redis:
    image: redis:7-alpine
    command: redis-server --requirepass ${REDIS_PASSWORD}
    volumes:
      - redisdata:/data

volumes:
  pgdata:
  redisdata:
```

## Horizontal Scaling

### API Layer

NIZAM's API layer is **fully stateless**:

- Sessions stored in Redis (not filesystem)
- Cache stored in Redis (not local)
- No server-side file state
- Queue jobs dispatched to Redis

Scale API instances independently:

```bash
docker compose up --scale app=5 --scale queue-worker=3
```

Or with Kubernetes:

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: nizam-api
spec:
  replicas: 5
  selector:
    matchLabels:
      app: nizam-api
  template:
    spec:
      containers:
        - name: nizam
          image: nizam:latest
          resources:
            requests:
              cpu: 250m
              memory: 256Mi
            limits:
              cpu: 1000m
              memory: 512Mi
```

### Queue Workers

Scale queue workers based on webhook and event processing load:

```bash
# Monitor queue depth
php artisan queue:monitor redis:default --max=100

# Scale workers
docker compose up --scale queue-worker=5
```

### Database Scaling

#### Read Replicas

Use read replicas for query-heavy endpoints (CDR listing, event queries):

```php
// config/database.php
'pgsql' => [
    'read' => [
        'host' => [
            env('DB_READ_HOST_1'),
            env('DB_READ_HOST_2'),
        ],
    ],
    'write' => [
        'host' => [env('DB_HOST')],
    ],
],
```

#### Connection Pooling

Use PgBouncer for connection pooling in high-concurrency scenarios:

```
[databases]
nizam = host=postgres port=5432 dbname=nizam

[pgbouncer]
pool_mode = transaction
max_client_conn = 1000
default_pool_size = 50
```

### Redis Scaling

For high-throughput deployments, use Redis Cluster or Redis Sentinel:

```env
REDIS_CLIENT=phpredis
REDIS_CLUSTER=redis

REDIS_CLUSTER_SEED_1=redis-node-1:6379
REDIS_CLUSTER_SEED_2=redis-node-2:6379
REDIS_CLUSTER_SEED_3=redis-node-3:6379
```

## WebSocket Scaling

### Laravel Reverb (Planned)

For real-time call events and dashboard updates:

```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=nizam
REVERB_APP_KEY=nizam-key
REVERB_APP_SECRET=nizam-secret
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
```

Scale WebSocket servers behind a load balancer with sticky sessions:

```nginx
upstream reverb {
    ip_hash;
    server reverb-1:8080;
    server reverb-2:8080;
}
```

### Alternative: Pusher/Ably

For managed WebSocket scaling without infrastructure:

```env
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=your-app-id
PUSHER_APP_KEY=your-key
PUSHER_APP_SECRET=your-secret
PUSHER_APP_CLUSTER=us2
```

## FreeSWITCH Scaling

### Single-Node (Current)

The current architecture uses a single FreeSWITCH instance per deployment. This supports ~1000 concurrent calls on modest hardware.

### Multi-Node (Future)

For multi-node FreeSWITCH, consider:

1. **SIP Proxy (Kamailio/OpenSIPS)** in front of FreeSWITCH nodes for load distribution
2. **Shared registration backend** — NIZAM already serves directory via `mod_xml_curl`, so registrations are database-driven
3. **Event aggregation** — ESL listeners on each FS node feed into the same NIZAM event pipeline
4. **Recording storage** — use shared storage (S3/NFS) for call recordings

```
┌─────────────┐
│  Kamailio    │ ← SIP traffic
│  (proxy)     │
└──────┬───┬──┘
       │   │
  ┌────▼┐ ┌▼────┐
  │ FS-1│ │ FS-2│ ← FreeSWITCH nodes
  └─────┘ └─────┘
       │   │
  ┌────▼───▼────┐
  │  NIZAM API  │ ← XML directory + dialplan
  └─────────────┘
```

## Backup & Restore

### Database Backup

```bash
# Automated daily backup
pg_dump -h $DB_HOST -U $DB_USERNAME -d $DB_DATABASE -Fc > nizam_$(date +%Y%m%d).dump

# Restore
pg_restore -h $DB_HOST -U $DB_USERNAME -d $DB_DATABASE nizam_20260228.dump
```

### Redis Backup

```bash
# Trigger RDB snapshot
redis-cli -a $REDIS_PASSWORD BGSAVE

# Copy RDB file
cp /data/dump.rdb /backups/redis_$(date +%Y%m%d).rdb
```

### Full Backup Script

```bash
#!/bin/bash
BACKUP_DIR="/backups/$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

# Database
pg_dump -h "$DB_HOST" -U "$DB_USERNAME" -d "$DB_DATABASE" -Fc > "$BACKUP_DIR/database.dump"

# Redis
redis-cli -a "$REDIS_PASSWORD" --rdb "$BACKUP_DIR/redis.rdb"

# FreeSWITCH config
tar -czf "$BACKUP_DIR/freeswitch-config.tar.gz" /etc/freeswitch/

# Call recordings
tar -czf "$BACKUP_DIR/recordings.tar.gz" /var/lib/freeswitch/recordings/

# Rotate — keep 30 days
find /backups -maxdepth 1 -mtime +30 -type d -exec rm -rf {} \;

echo "Backup completed: $BACKUP_DIR"
```

### Restore Procedure

1. Stop queue workers and scheduler
2. Restore PostgreSQL from dump
3. Restore Redis from RDB
4. Run `php artisan migrate` to apply any pending migrations
5. Run `php artisan config:cache && php artisan route:cache`
6. Restart all services

## Monitoring

### Health Checks

NIZAM exposes `GET /api/health` for load balancer health checks:

```json
{
  "status": "healthy",
  "checks": {
    "app": {"status": "ok"},
    "esl": {"connected": true, "status": "ok"},
    "gateways": {"status": "ok", "gateways": [...]}
  }
}
```

Configure your load balancer:

```nginx
upstream nizam {
    server app-1:9000;
    server app-2:9000;
    server app-3:9000;
}

server {
    location /api/health {
        proxy_pass http://nizam;
    }
}
```

### Prometheus Metrics (Recommended)

Add Laravel Prometheus exporter for production monitoring:

- Request latency (p50, p95, p99)
- Queue depth and processing time
- ESL connection status
- Active calls count
- API error rates

### Log Aggregation

Configure centralized logging:

```env
LOG_CHANNEL=stderr
LOG_LEVEL=info
```

Use ELK stack, Datadog, or CloudWatch for log aggregation.

## Security Hardening

### TLS Configuration

```nginx
server {
    listen 443 ssl http2;
    ssl_certificate /etc/letsencrypt/live/api.nizam.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.nizam.example.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
}
```

### Firewall Rules

```bash
# Only expose:
# - 443 (HTTPS API)
# - 5060/5061 (SIP TCP/TLS)
# - 5080 (SIP external)
# - 16384-32768 (RTP media)

# Internal only:
# - 8021 (ESL — never expose publicly)
# - 5432 (PostgreSQL)
# - 6379 (Redis)
```

### ESL Security

ESL must never be exposed to the public internet:

```xml
<!-- FreeSWITCH event_socket.conf.xml -->
<configuration name="event_socket.conf">
  <settings>
    <param name="listen-ip" value="127.0.0.1"/>
    <param name="listen-port" value="8021"/>
    <param name="password" value="${ESL_PASSWORD}"/>
  </settings>
</configuration>
```
