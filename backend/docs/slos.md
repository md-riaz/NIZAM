# Service Level Objectives (SLOs)

## Overview
These SLOs define the expected performance and reliability targets for the NIZAM platform.
They are used to trigger alerts, guide capacity planning, and set customer expectations.

---

## 1. Event Processing

| SLO | Target | Measurement |
|-----|--------|-------------|
| Event lag (ingestion to storage) | < 500ms (p99) | Time from FreeSWITCH event to `call_events` table write |
| Event processing success rate | > 99.9% | Ratio of successfully processed events to total events received |
| Feature extraction latency | < 2s (p95) | Time to extract features from event stream into `analytics_events` |

## 2. Webhook Delivery

| SLO | Target | Measurement |
|-----|--------|-------------|
| Webhook delivery window | < 5s (p95) | Time from event occurrence to first delivery attempt |
| Webhook delivery success rate | > 99% | Ratio of successful deliveries (2xx) to total attempts |
| Webhook retry completion | < 5 minutes | Time to exhaust all retry attempts for a failed delivery |

## 3. SLA Computation

| SLO | Target | Measurement |
|-----|--------|-------------|
| Metrics aggregation delay | < 60s | Time from period end to metric record availability |
| Real-time metrics freshness | < 10s | Maximum staleness of real-time queue metrics |
| Health score computation | < 5s (p95) | Time to compute per-tenant health score |

## 4. Call Processing

| SLO | Target | Measurement |
|-----|--------|-------------|
| Dialplan compilation | < 200ms (p99) | Time to compile and return XML dialplan |
| Call setup time (platform contribution) | < 100ms | Platform overhead added to call setup |
| CDR creation | < 1s | Time from hangup event to CDR record creation |

## 5. API Availability

| SLO | Target | Measurement |
|-----|--------|-------------|
| API availability | > 99.9% | Successful responses (non-5xx) / total requests |
| API response time | < 200ms (p95) | Time from request received to response sent |
| Authentication latency | < 100ms (p99) | Token validation time |

## 6. Anomaly Detection

| SLO | Target | Measurement |
|-----|--------|-------------|
| Alert detection latency | < 2 minutes | Time from anomaly occurrence to alert creation |
| False positive rate | < 10% | Alerts that are resolved as false positive / total alerts |
| Alert routing delivery | < 30s | Time from alert creation to channel delivery |

---

## Monitoring & Review

- SLOs are reviewed monthly
- Breaches trigger post-incident reviews
- SLO targets may be adjusted based on capacity and customer needs
- Error budgets: When 99.9% target is breached, prioritize reliability over features

## Escalation on SLO Breach

See [Escalation Checklist](./escalation-checklist.md) for response procedures.
