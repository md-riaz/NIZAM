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
- FreeSWITCH `ext-rtp-ip`, `ext-sip-ip`, `rtp-ip`, `sip-ip`, and
  `aggressive-nat-detection` are configurable via environment variables
  (`EXT_RTP_IP`, `EXT_SIP_IP`, `RTP_IP`, `SIP_IP`, `AGGRESSIVE_NAT_DETECTION`,
  `LOCAL_NETWORK_ACL`) in the `config/telephony.php` `media` section.
- FreeSWITCH dynamically loads SIP profile configuration from NIZAM via
  `mod_xml_curl` (configuration section).
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

### SRTP / TLS / WebRTC

SRTP and SIP-TLS are **optional and not enforced** in v1.0. WebRTC endpoints
are supported via WebSocket transport on the `internal` profile with DTLS-SRTP.

**System behavior:**
- WebRTC is available via the `internal` SIP profile using WSS on port 7443
  with DTLS-SRTP and Opus codec support. See [WebRTC Setup](webrtc-setup.md) for details.
- Platform admins can now choose the active WebRTC TLS mode from the admin
  settings screen while keeping both certificate strategies visible:
  - **Trusted/public CA certificates** for production browser access.
  - **Self-signed / development certificates** for labs and controlled testing.
- NIZAM stores which mode is active and which certificate directory FreeSWITCH
  should use for WebSocket transport on the `internal` profile, similar to FusionPBX-style SIP profile
  selection.
- Certificate files must still be provisioned externally in the configured
  directory; NIZAM does not issue, renew, or rotate certificates.
- Per-tenant TLS/SRTP enforcement is not available; all tenants share the same
  media security posture.

**Why external trusted certificates are still required for production:**
- Browsers only trust WSS connections when the presented TLS certificate chains
  to a CA already trusted by the client device.
- A self-signed certificate can work for development, but every browser or OS
  must manually trust that certificate before WebRTC registration succeeds.
- Because trust is enforced by the browser, NIZAM cannot bypass this at the
  application layer.

**Recommendation:** For production WebRTC deployments, activate the trusted CA
mode, install valid browser-trusted certificates, and configure STUN and
optionally TURN servers for NAT traversal. Reserve self-signed mode for local,
staging, or controlled internal testing.

---

## SIP Trunking & Carrier Interoperability

### Caller ID / P-Asserted-Identity / Privacy

NIZAM stores outbound caller ID at the extension level but does **not** perform
automatic CID rewriting, P-Asserted-Identity injection, or privacy header
manipulation for carrier compliance.

**System behavior:**
- Caller ID numbers (effective and outbound) are automatically normalized to
  E.164 format using `DidNormalizationService::toE164()`, with the leading `+`
  stripped for the dialplan variables to ensure carrier compatibility.
- P-Asserted-Identity injection and Privacy headers are managed at the **Tenant level**.
- Settings are stored in the Tenant's `settings` JSON field (`outbound_caller_id_pai`
  and `outbound_caller_id_privacy`).
- If a tenant does not specify a PAI preference (set to `null`), it falls back to
  the global `OUTBOUND_CALLER_ID_PAI` setting in `config/telephony.php`.
- Anonymous call presentation is supported by setting privacy to `hide` or `full`
  at the tenant level.

**Recommendation:** Use `DidNormalizationService::toE164()` for E.164
formatting. Carrier-specific SIP header manipulation should be handled in
FreeSWITCH dialplan or an SBC.

### Emergency Calling / E911

NIZAM **does not support** emergency calling (E911, 112, 999, or equivalent)
in v1.0. The platform provides configuration to block emergency number patterns
to prevent accidental reliance on an untested path.

**System behavior:**
- Emergency number patterns can be defined in `config/telephony.php` under the
  `emergency` key.
- The `DialplanCompiler` does **not** automatically block these patterns; the
  configuration serves as a reference for operators implementing custom
  dialplan rules.
- No location (PIDF-LO) or PSAP routing is provided.

**Recommendation:** If the platform is used in a context where emergency
calling is legally required, deploy a dedicated E911 solution (e.g. Bandwidth,
Intrado) and route emergency patterns outside NIZAM.

### Inbound DID Normalization

NIZAM automatically normalizes inbound numbers to E.164 before routing to ensure
reliable matching regardless of carrier format.

**System behavior:**
- All DIDs store a `normalized_number` field (E.164) generated automatically
  on save.
- `NumberRoutingService::resolveInboundDid()` normalizes the inbound destination
  number using the tenant's default country code before querying the database.
- Matches are performed against both the raw `number` and the `normalized_number`.

---

## Contact Center

### Agent Login Model (Planned)

Agents are **permanently tied** to an extension in v1.0. Hotdesking (agent
logging into different extensions/devices) is a **planned feature for v1.1**.

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

NIZAM enforces per-tenant call rate limiting (`max_calls_per_minute`) and 
concurrent call limiting (`max_concurrent_calls`) in real-time.

**System behavior:**
- The `DialplanCompiler` automatically injects FreeSWITCH `limit` application 
  calls for every inbound and outbound call.
- Destination blocking is enforced via the `BlockedDestination` model. 
  Calls matching these patterns (Regex) are rejected with SIP 403 Forbidden.
- Global and tenant-specific blocking rules are supported.
- Toll fraud detection (anomalous patterns, high-cost destinations) is 
  partially addressed via these blocking rules but lacks automatic heuristics.

**Recommendation:** Implement rate-limit enforcement in the dialplan compiler
or SBC layer. Monitor CDR patterns for anomalous activity.

---

## Observability

### RTP Quality Metrics

NIZAM collects basic RTP quality metrics (jitter, packet loss, MOS) from
FreeSWITCH hangup variables in v1.0.

**System behavior:**
- CDRs include `mos_score`, `packet_loss`, `jitter`, and `latency` extracted
  from FreeSWITCH `variable_rtp_audio_in_*` channel variables.
- Quality metrics are only available for the *inbound* direction relative to
  each channel leg as reported by FreeSWITCH.
- One-way audio detection is **not** implemented or automated.
- Support teams can use these metrics in the CDR analytics view to identify
  problematic calls or endpoints.

**Recommendation:** For deep packet-level analysis or real-time MOS monitoring,
use external tools (Homer, VoIPmonitor).

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
