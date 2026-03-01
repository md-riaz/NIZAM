# NIZAM v1.0 — Known Limitations

This document explicitly lists what NIZAM v1.0 **does not** support and describes
how the system behaves when it encounters each scenario. It is intended for
operators, integrators, and anyone evaluating the platform for production use.

> **Rule of thumb:** If a capability is not listed in [v1-scope.md](v1-scope.md)
> as *Included*, assume it is unsupported and check this document for the
> expected behavior.

---

## Media & Network

### NAT Traversal / SBC Behavior

NIZAM does not include a built-in SBC or automated NAT detection. Phones behind
symmetric NAT, SIP ALG routers, or carrier-grade NAT may experience one-way
audio or registration failures.

**System behavior:**
- FreeSWITCH `ext-rtp-ip` and `ext-sip-ip` must be configured manually in the
  FreeSWITCH deployment (see `config/nizam.php` `media` section for guidance).
- No automatic `rport` or `Contact` header rewriting is performed by NIZAM.
- There is no health check that detects "registered but no audio" patterns.

**Recommendation:** Deploy an external SBC (e.g. Odesbc, Orecx, or Kamailio)
in front of FreeSWITCH for NAT-heavy environments.

### DTMF Reliability

NIZAM's IVR and queue features rely on DTMF for digit collection. The default
DTMF mode is RFC 2833 (telephone-event). SIP INFO and inband detection are
**not** actively tested or guaranteed across all gateway types.

**System behavior:**
- The platform assumes RFC 2833 for all DTMF.
- If a carrier or phone uses SIP INFO or inband DTMF, IVR digit collection may
  fail silently.
- No automatic DTMF mode negotiation is performed.

**Recommendation:** Ensure all SIP trunks and endpoints are configured for
RFC 2833. Test IVR flows end-to-end after any gateway change.

### Fax / T.38

Fax is **not supported** in v1.0. The platform does not negotiate T.38 and does
not provide fax-to-email or fax storage.

**System behavior:**
- Inbound fax calls from carriers are treated as normal voice calls.
- If a carrier sends a T.38 re-INVITE, FreeSWITCH will reject it (default
  behavior) and the call may drop.
- No explicit fax detection or rejection logic is present.

**Recommendation:** If fax traffic is expected, route fax DIDs to a dedicated
fax server outside NIZAM, or configure FreeSWITCH directly for T.38 passthrough.

### SRTP / TLS

SRTP and SIP-TLS are **optional and not enforced** in v1.0. WebRTC endpoints
are not natively supported.

**System behavior:**
- FreeSWITCH TLS and SRTP configuration is external to NIZAM's application
  layer.
- Per-tenant TLS/SRTP enforcement is not available; all tenants share the same
  media security posture.
- No certificate management or rotation is provided.

**Recommendation:** For security-conscious deployments, configure FreeSWITCH
TLS profiles manually and use an SBC for WebRTC termination.

---

## SIP Trunking & Carrier Interoperability

### Caller ID / P-Asserted-Identity / Privacy

NIZAM stores outbound caller ID at the extension level but does **not** perform
automatic CID rewriting, P-Asserted-Identity injection, or privacy header
manipulation for carrier compliance.

**System behavior:**
- The `effective_caller_id_number` from the extension model is sent as-is.
- No E.164 formatting or carrier-specific header adaptation is applied
  automatically.
- Anonymous / restricted call presentation is not implemented.

**Recommendation:** Use `DidNormalizationService::toE164()` for E.164
formatting. Carrier-specific SIP header manipulation should be handled in
FreeSWITCH dialplan or an SBC.

### Emergency Calling / E911

NIZAM **does not support** emergency calling (E911, 112, 999, or equivalent)
in v1.0. The platform provides configuration to block emergency number patterns
to prevent accidental reliance on an untested path.

**System behavior:**
- Emergency number patterns can be defined in `config/nizam.php` under the
  `emergency` key.
- The `DialplanCompiler` does **not** automatically block these patterns; the
  configuration serves as a reference for operators implementing custom
  dialplan rules.
- No location (PIDF-LO) or PSAP routing is provided.

**Recommendation:** If the platform is used in a context where emergency
calling is legally required, deploy a dedicated E911 solution (e.g. Bandwidth,
Intrado) and route emergency patterns outside NIZAM.

### Inbound DID Normalization

Carriers deliver inbound numbers in varying formats (`+1`, `001`, `1`,
national, local). NIZAM provides `DidNormalizationService` to normalize numbers
to E.164 before routing, but normalization is **not applied automatically** to
all inbound calls.

**System behavior:**
- The DID `number` field stores whatever format was configured at creation time.
- `DidNormalizationService::toE164()` is available for explicit normalization.
- If a DID is stored as `+15551234567` but the carrier delivers `15551234567`,
  routing will fail unless the DID is stored in the matching format or
  normalization is applied in the lookup path.

**Recommendation:** Store all DIDs in E.164 format and normalize inbound
queries using `DidNormalizationService`.

---

## Contact Center

### Agent Login Model

Agents are **permanently tied** to an extension in v1.0. Hotdesking (agent
logging into different extensions/devices) is not supported.

**System behavior:**
- The `agents` table has a required `extension_id` foreign key.
- Changing an agent's extension requires an API update; there is no "login to
  device" flow.
- Queue membership and agent state are not affected by device registration
  status.

### After-Call Work (ACW) / Wrap-Up Timers

NIZAM supports ACW as a pause reason (`after_call_work`) on the Agent model,
and queues have a configurable `wrapup_seconds` timer. However, **automatic
ACW enforcement** (auto-pausing the agent after a call and auto-resuming after
the timer) is not implemented in v1.0.

**System behavior:**
- When a call ends, the agent transitions to `available` immediately unless the
  application explicitly sets the agent to `paused` with reason
  `after_call_work`.
- The `wrapup_seconds` field on the Queue model is informational and can be used
  by integrations to implement timer-based ACW.
- Without ACW enforcement, occupancy and SLA metrics may overstate agent
  availability.

### Blind / Attended Transfer Semantics

Call transfers are handled at the FreeSWITCH level. NIZAM does not provide
application-level transfer tracking or metrics attribution.

**System behavior:**
- Transfer events appear in the call event log as standard CHANNEL_BRIDGE /
  CHANNEL_HANGUP events.
- There is no distinction between blind and attended transfers in CDR or
  metrics.
- Transfer attribution (which agent initiated, which queue the call originated
  from) requires manual correlation via call UUID.

### Queue Fairness & Starvation

The `least_recent` strategy selects agents by longest time since last answered
call. However, edge cases around agent pause/resume, state drift, and clock
skew are **not** tested for starvation resistance beyond basic unit tests.

**System behavior:**
- `round_robin` wraps to the first agent when the last agent is reached.
- `least_recent` uses `MAX(answer_time)` per agent; agents who have never
  answered get priority.
- No fairness guarantees are made when agents rapidly toggle pause/available
  states.

---

## Data Retention & Compliance

### Recording Retention Policies

Per-tenant recording retention is enforced by the `nizam:prune-recordings`
artisan command, which is scheduled to run daily via the task scheduler.

**System behavior:**
- The `recording_retention_days` field defaults to `null` (no retention policy
  — recordings are kept indefinitely).
- When set, the `nizam:prune-recordings` command deletes recordings (and their
  backing files) older than the retention window.
- The scheduler container in `docker-compose.yml` runs `php artisan
  schedule:work`, which triggers the command at midnight UTC daily.
- Legal hold, export, and GDPR deletion requests must still be handled manually
  via the recordings API.
- Audit logs (`audit_logs` table) are tenant-scoped but not encrypted at rest.

**Operator tip:** Use `php artisan nizam:prune-recordings --dry-run` to preview
which recordings would be deleted before running for real. Pass `--tenant=<uuid>`
to restrict the run to a single tenant.

### PII / Sensitive Data in Logs

NIZAM automatically masks SIP passwords, Bearer tokens, API keys, and credit
card numbers in log output via `SensitiveDataSanitizerTap`.

**System behavior:**
- The `SensitiveDataSanitizerTap` is registered on the `single`, `daily`, and
  `stderr` log channels in `config/logging.php`.
- Audit logs (`audit_logs` table) are tenant-scoped but not encrypted at rest.
- FreeSWITCH log files (outside NIZAM) are not sanitized; keep FreeSWITCH log
  levels at `warning` or above in production to avoid leaking SIP credentials.

---

## Platform Operations & Lifecycle

### Backups and Disaster Recovery

NIZAM does not include built-in backup or disaster recovery tooling.

**System behavior:**
- No automated database backup, recording backup, or configuration export is
  provided.
- RPO/RTO targets are not defined at the application level.
- Restoring a platform requires: database restore + recording file restore +
  FreeSWITCH configuration restore + `modules_statuses.json` restore.

**Recommendation:** Implement database and file-level backups externally.
Document RPO/RTO targets for your deployment.

### Schema Migrations with Disabled Modules

Module migrations are managed by nwidart/laravel-modules. Migrations for
disabled modules are **not automatically run** when the module is disabled.

**System behavior:**
- If a module is disabled, its migrations may not run during `php artisan
  migrate`.
- Enabling a previously disabled module may require running its migrations
  manually (`php artisan module:migrate {Name}`).
- There is no "catch-up migration" mechanism for modules that were disabled
  during a platform upgrade.

**Recommendation:** Run `php artisan module:migrate` for all modules during
upgrades, regardless of enabled state, to keep schemas consistent.

### Configuration Caching

Laravel `config:cache` and `route:cache` can interact with dynamic module
routing.

**System behavior:**
- `config:cache` snapshots all configuration at cache time; runtime changes to
  `modules_statuses.json` will not take effect until the cache is cleared.
- `route:cache` may not include routes from disabled modules; enabling a module
  requires a route cache rebuild.
- These interactions are documented but not part of automated release gating.

---

## Security

### Credential Rotation

NIZAM does not provide a credential rotation mechanism for SIP passwords,
webhook signing secrets, or JWT secrets.

**System behavior:**
- Changing a SIP password requires updating the extension via API and
  reprovisioning the device.
- Webhook signing secrets are stored per-webhook; changing them requires an API
  update and coordination with the receiving endpoint.
- JWT / Sanctum token rotation requires re-authentication.

### Abuse Controls / Toll Fraud

Per-tenant call rate limiting is configurable via `max_calls_per_minute` on the
Tenant model. However, **enforcement is informational only** in v1.0.

**System behavior:**
- The `max_calls_per_minute` field is available for integrations to query and
  enforce.
- No automatic call blocking, international dialing restriction, or spend-cap
  enforcement is built into the dialplan compiler.
- Toll fraud detection (anomalous call patterns, high-cost destinations) is not
  implemented.

**Recommendation:** Implement rate-limit enforcement in the dialplan compiler
or SBC layer. Monitor CDR patterns for anomalous activity.

---

## Observability

### RTP Quality Metrics

NIZAM does not collect RTP quality metrics (jitter, packet loss, MOS) from
FreeSWITCH in v1.0.

**System behavior:**
- CDRs include `read_codec` and `write_codec` but no quality-of-experience
  data.
- One-way audio detection is not implemented.
- Support teams cannot diagnose "audio is bad" from NIZAM data alone.

**Recommendation:** Use FreeSWITCH `mod_rtcp` or external monitoring (Homer,
VoIPmonitor) for RTP quality analysis.

### Correlation IDs

Call events include `call_uuid` and `tenant_id`. Additional correlation
dimensions (node_id, gateway_id, queue_id, agent_id) are **partially**
available depending on the event type.

**System behavior:**
- `queue.call_answered` events include `queue_id` and `agent_id`.
- Generic call events (CHANNEL_CREATE, CHANNEL_HANGUP) include only `call_uuid`
  and `tenant_id`.
- There is no unified correlation ID that links across all subsystems
  (gateway → queue → agent → recording).
- Cross-system debugging requires manual correlation via `call_uuid`.

---

## What This Document Is Not

This is not a roadmap. Items listed here may or may not be addressed in future
releases. For planned features, see [v1-scope.md](v1-scope.md).

This document is updated with each release. If a limitation is resolved, it is
removed from this list and documented in the release notes.
