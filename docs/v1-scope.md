# NIZAM v1.0 Feature Scope

This document defines the **explicit feature boundary** for the NIZAM v1.0 release.
Any capability not listed as Included is either a roadmap item or unsupported.

---

## Included in v1.0

### Multi-Tenant SaaS Core
- Domain-based tenant isolation enforced at middleware, policy, and query level
- Per-tenant resource limits and suspension controls
- Cross-tenant access blocked by authorization policies
- Tenant lifecycle management (create, suspend, activate, delete)

### Extension Management
- SIP user provisioning with credential management
- Voicemail settings per extension
- Caller ID control (effective and outbound)
- Registration state tracking

### DID Routing
- DID → destination mapping (Extension, Ring Group, IVR, Time Condition, Voicemail)
- Fail-safe routing: unroutable destinations return `respond 404`
- Dynamic routing via `mod_xml_curl`

### Ring Groups
- Simultaneous and sequential strategies
- Configurable timeout with fallback routing

### IVR
- DTMF-driven digit-to-destination mapping
- Nested IVR menus
- Prompt audio support

### Policy Engine
- Time Condition routing (time-of-day, day-of-week, day-of-month, month)
- Tenant suspension policy (blocks routing immediately)
- Blacklist policy

### Event Bus (Versioned)
- Versioned event payloads (`schema_version: "1.0"`)
- Call lifecycle events: CHANNEL_CREATE, CHANNEL_ANSWER, CHANNEL_BRIDGE, CHANNEL_HANGUP_COMPLETE
- SIP registration events: `sofia::register`, `sofia::unregister`
- Immutable call event log for replay
- Redis-backed queue with configurable workers

### Webhooks (Signed + Retry)
- HMAC-SHA256 signed payloads
- Configurable retry with exponential backoff
- Per-tenant webhook configurations
- Delivery log with success/failure status

### Contact Center Core
- Call queue with configurable overflow and SLA thresholds
- Agent state machine (Available, Busy, Paused, Offline) with valid transition enforcement
- Pause reason tracking
- SLA calculation (accurate to ±1%)
- Real-time metrics per queue
- No ghost agents: automatic reconciliation on reconnect

### Modular System (nwidart + NizamModule hooks)
- nwidart as authoritative activation source
- Module enable/disable governs: routes, hooks, dialplan contributions, event listeners
- Dependency resolution with topological sort
- Cascading disable for dependent modules
- Module SDK documented

### Provisioning
- Device profile templates (vendor-agnostic)
- MAC-address-based provisioning endpoint
- Profile variables with tenant scoping

### Analytics Baseline (Non-ML)
- CDR (Call Detail Records) with per-tenant scoping
- Call event log with replay support
- Feature extraction from event stream
- Aggregated metrics per tenant and queue

### SwitchNode Abstraction (Single-Node)
- FreeSWITCH ESL listener with automatic reconnection and exponential backoff
- XML directory and dialplan endpoints via `mod_xml_curl`
- SwitchNode model representing a single FreeSWITCH instance

### API-First Control
- All operations available via REST API
- Sanctum token authentication
- Role-based authorization (admin bypass, tenant isolation)
- Rate limiting enforced
- API versioned under `/api/v1`
- OpenAPI specification published

### Module Enable/Disable Governance
- All modules disabled by default; opt-in activation
- nwidart `modules_statuses.json` as single source of truth
- No dual-config drift: ModuleRegistry defers to nwidart

---

## Explicitly Excluded from v1.0 (Roadmap)

The following capabilities are **not** part of v1.0 and are documented here to set clear expectations.

| Feature | Reason | Target |
|---------|--------|--------|
| AI / ML predictive routing | Requires training data and model infra not yet available | v2.x |
| Multi-node FreeSWITCH clustering | Single-node supported; clustering requires additional orchestration | v1.x |
| Skill-based routing | Foundational agent state model in v1; skill matching not fully stable | v1.x |
| External module marketplace | Requires signing, sandboxing, and trust infrastructure | v2.x |
| WebRTC softphone (built-in) | Third-party sip.js integration possible; native WebRTC not production-hardened | v1.x |
| Visual flow builder (UI) | API-first; no GUI in v1 scope | v2.x |
| SIP trunk management (carrier-side) | Out of scope for v1; trunk provisioning is manual | v1.x |

---

## Known Limitations (v1.0)

1. **Single FreeSWITCH node**: The platform manages one FreeSWITCH instance. High-availability clustering is not supported.
2. **No native WebRTC softphone**: Integrations via sip.js or similar are possible but unsupported.
3. **Non-ML analytics**: Anomaly detection is rule-based, not ML-powered.
4. **No visual flow builder**: All configuration is via API; no GUI is included.
5. **Provisioning is template-based**: Dynamic vendor firmware management is not included.
6. **SIP trunk management is manual**: Carrier-side trunk provisioning requires manual FreeSWITCH configuration.
7. **No built-in SIP registrar monitoring**: External Prometheus/Grafana required for deep SIP layer metrics.

---

## Scope Governance

Any feature that is not listed under **Included** above must go through the standard
[versioning and release process](versioning-and-releases.md) before inclusion.

Experimental features must **not** be exposed in the production API without explicit version tagging.
