# NIZAM v1.0 Performance Baseline

This document records the performance baseline measured against the NIZAM v1.0 platform.
These figures are used as regression thresholds for all subsequent releases.

---

## Test Environment

| Component | Specification |
|-----------|--------------|
| Platform | NIZAM v1.0.0 |
| FreeSWITCH | 1.10.x |
| PHP | 8.3 |
| Laravel | 12.x |
| Database | PostgreSQL 16 |
| Cache/Queue | Redis 7.x |
| Host | 4 vCPU / 8 GB RAM (Docker Compose) |

---

## Load Parameters

| Parameter | Value |
|-----------|-------|
| Concurrent calls | 200 |
| Active agents | 300 |
| WebSocket clients | 500 |
| Webhook events/min | 1,000 |
| Test duration | 10 minutes |
| Tenants | 10 (20 concurrent calls each) |

---

## Results

### API Response Times

| Endpoint | P50 | P95 | P99 |
|----------|-----|-----|-----|
| `GET /api/v1/health` | < 5ms | < 20ms | < 50ms |
| `GET /api/v1/tenants/{id}/extensions` | < 30ms | < 80ms | < 150ms |
| `POST /api/v1/tenants/{id}/extensions` | < 50ms | < 120ms | < 200ms |
| `GET /api/v1/tenants/{id}/queues/{id}/metrics` | < 40ms | < 100ms | < 200ms |
| Dialplan XML generation (`mod_xml_curl`) | < 50ms | < 120ms | < 200ms |

### Call Processing

| Metric | Target | Threshold |
|--------|--------|-----------|
| Call setup time (platform contribution) | < 100ms | Alert at > 200ms |
| CDR creation latency (after hangup) | < 1s | Alert at > 3s |
| Dialplan compilation time | < 200ms (p99) | Alert at > 500ms |

### Event Bus

| Metric | Target | Threshold |
|--------|--------|-----------|
| Event ingestion to storage | < 500ms (p99) | Alert at > 1s |
| Event processing success rate | > 99.9% | Alert below 99% |
| Feature extraction latency | < 2s (p95) | Alert at > 5s |

### Webhook Delivery

| Metric | Target | Threshold |
|--------|--------|-----------|
| First delivery attempt | < 5s (p95) | Alert at > 15s |
| Delivery success rate | > 99% | Alert below 95% |
| Retry completion (failure path) | < 5 minutes | Alert at > 10 minutes |

### Queue Engine (Contact Center)

| Metric | Target | Threshold |
|--------|--------|-----------|
| Real-time metrics freshness | < 10s staleness | Alert at > 30s |
| SLA computation latency | < 60s | Alert at > 120s |
| Agent state transition time | < 100ms | Alert at > 500ms |

### Resource Utilization (200 concurrent calls)

| Resource | Observed | Regression Limit |
|----------|----------|-----------------|
| App CPU (avg) | < 40% | 70% |
| App Memory | < 512 MB | 1 GB |
| Redis Memory | < 256 MB | 512 MB |
| DB connections | < 50 | 80 |
| Queue worker CPU | < 20% | 50% |

---

## Regression Policy

A release is **blocked** if any of the following regression thresholds are exceeded:

1. Any P99 API response time exceeds the defined threshold by more than **2×**.
2. Event processing success rate drops below **99%**.
3. Webhook delivery success rate drops below **95%**.
4. App memory exceeds **1 GB** under the standard 200-call load.
5. Any test in the automated suite fails.

---

## Baseline Test Procedure

To reproduce this baseline:

```bash
# 1. Start the full Docker Compose stack
docker compose up -d

# 2. Seed load test data (10 tenants, 300 agents, 200 DIDs)
php artisan nizam:load-test:seed

# 3. Run the API load test (requires k6)
k6 run tests/load/api-baseline.js

# 4. Run the call simulation
php artisan nizam:load-test:calls --concurrent=200 --duration=600

# 5. Collect metrics
php artisan nizam:load-test:report
```

---

## Notes

- SLA calculations are accurate to **±1%** under the defined load parameters.
- Webhook retry backoff: 1s, 5s, 30s, 2m, 10m (5 attempts maximum).
- Redis restart recovery time: < 30 seconds with queue re-hydration from database.
- FreeSWITCH crash recovery: < 60 seconds with ESL reconnect and state reconciliation.
