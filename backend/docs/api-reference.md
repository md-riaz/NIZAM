# API Reference

All NIZAM API endpoints return JSON responses and require authentication unless noted otherwise.

---

## Authentication

NIZAM uses [Laravel Sanctum](https://laravel.com/docs/sanctum) bearer tokens.

### Registration

Self-registration is not supported.

Organizations are provisioned manually by Superadmin users, and users are created under an existing organization by an authorized administrator.

### Login

```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "password"
}
```

**Response** `200`:
```json
{
  "user": { "id": "uuid", "name": "John Doe", "email": "john@example.com" },
  "token": "2|def456..."
}
```

### Logout

```http
POST /api/auth/logout
Authorization: Bearer YOUR_TOKEN
```

### Current User

```http
GET /api/auth/me
Authorization: Bearer YOUR_TOKEN
```

### List API Tokens

```http
GET /api/auth/tokens
Authorization: Bearer YOUR_TOKEN
```

**Response** `200`:
```json
{
  "data": [
    { "id": 1, "name": "CLI Token", "abilities": ["*"], "last_used_at": null, "created_at": "..." }
  ]
}
```

### Create API Token

```http
POST /api/auth/tokens
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "name": "My CLI Token",
  "abilities": ["*"]
}
```

**Response** `201`:
```json
{
  "data": { "id": 2, "name": "My CLI Token", "abilities": ["*"] },
  "plainTextToken": "2|abc123..."
}
```

> The `plainTextToken` is only returned at creation time. Store it securely.

### Revoke API Token

```http
DELETE /api/auth/tokens/{tokenId}
Authorization: Bearer YOUR_TOKEN
```

**Response** `204` No Content.

---

## Health Check

**No authentication required.**

```http
GET /api/health
```

**Response** `200` (healthy):
```json
{
  "status": "healthy",
  "checks": {
    "app": { "status": "ok" },
    "esl": {
      "connected": true,
      "status": "ok",
      "freeswitch": { "uptime": "5d", "sessions": 12, "raw": "..." }
    },
    "gateways": {
      "status": "ok",
      "gateways": [{ "name": "external", "type": "profile", "status": "RUNNING" }],
      "registrations": { "count": 5, "entries": [] },
      "checked_at": "2026-02-28T03:00:00Z"
    }
  }
}
```

**Response** `503` (degraded — ESL not connected):
```json
{
  "status": "degraded",
  "checks": {
    "app": { "status": "ok" },
    "esl": { "connected": false, "status": "unreachable" },
    "gateways": { "status": "unknown", ... }
  }
}
```

---

## Organizations

Admin-only for create/update/delete. Regular users can only view their own organization.

### List Organizations

```http
GET /api/organizations
Authorization: Bearer YOUR_TOKEN
```

### Create Organization

```http
POST /api/organizations
Authorization: Bearer YOUR_TOKEN (admin)
Content-Type: application/json

{
  "name": "Acme Corp",
  "domain": "acme.example.com",
  "max_extensions": 100,
  "is_active": true
}
```

`default_schedule_id` and `default_holiday_calendar_id` are returned on organization resources after bootstrap/provisioning creates the organization defaults. They are not currently accepted by the create/update validation rules.

### Get / Update / Delete Organization

```http
GET    /api/organizations/{id}
PUT    /api/organizations/{id}
DELETE /api/organizations/{id}
```

**Organization resource fields include:**

```json
{
  "id": "uuid",
  "name": "Acme Corp",
  "domain": "acme.example.com",
  "default_schedule_id": "uuid-or-null",
  "default_holiday_calendar_id": "uuid-or-null",
  "settings": {},
  "status": "active",
  "max_extensions": 100,
  "max_concurrent_calls": 20,
  "max_dids": 10,
  "max_ring_groups": 5,
  "is_active": true,
  "created_at": "2026-04-12T10:00:00Z",
  "updated_at": "2026-04-12T10:00:00Z"
}
```

The default schedule and holiday calendar IDs point to bootstrap-created organization records when available.

### Organization Settings

Get and merge-update organization settings (stored as JSON):

```http
GET /api/organizations/{id}/settings
Authorization: Bearer YOUR_TOKEN
```

**Response** `200`:
```json
{
  "data": { "timezone": "America/New_York", "recording_format": "wav" }
}
```

```http
PUT /api/organizations/{id}/settings
Authorization: Bearer YOUR_TOKEN (admin)
Content-Type: application/json

{
  "settings": { "recording_format": "mp3", "max_ring_time": 30 }
}
```

Settings are **merged** — existing keys are preserved unless explicitly overwritten.

### Organization Statistics

Dashboard-style aggregate counts for all organization resources:

```http
GET /api/organizations/{organization_id}/stats
Authorization: Bearer YOUR_TOKEN
```

**Response** `200`:
```json
{
  "data": {
    "extensions_count": 25,
    "active_extensions_count": 22,
    "dids_count": 10,
    "ring_groups_count": 3,
    "ivrs_count": 2,
    "cdrs_total": 1540,
    "cdrs_today": 47,
    "recordings_count": 320,
    "recordings_total_size": 524288000,
    "device_profiles_count": 15,
    "webhooks_count": 4,
    "call_routing_policies_count": 3,
    "call_flows_count": 2,
    "quotas": {
      "max_extensions": 50,
      "max_concurrent_calls": 20,
      "max_dids": 10,
      "max_ring_groups": 5
    }
  }
}
```

### Organization Provisioning (Zero-Touch)

Create a organization with automated onboarding — bootstraps default business-hours, holiday, and main business-phone entrypoint defaults.

```http
POST /api/organizations
Authorization: Bearer YOUR_TOKEN (admin)
Content-Type: application/json

{
  "name": "Acme Corp",
  "domain": "acme.nizam.local",
  "max_extensions": 50,
  "max_concurrent_calls": 20,
  "max_dids": 10,
  "max_ring_groups": 5
}
```

`name` and `domain` are required by current validation. The response organization resource includes bootstrap-created `default_schedule_id` and `default_holiday_calendar_id` when provisioning succeeds.

### Usage Metering

#### Get Usage Summary

```http
GET /api/organizations/{organization_id}/usage/summary?from=2026-02-01&to=2026-02-28
Authorization: Bearer YOUR_TOKEN
```

Returns aggregated usage metrics (call_minutes, concurrent_call_peak, recording_storage_bytes, active_devices, active_extensions) for the given date range.

#### Collect Usage Snapshot

```http
POST /api/organizations/{organization_id}/usage/collect
Authorization: Bearer YOUR_TOKEN
```

Records a point-in-time snapshot of current resource usage for the organization.

#### Reconcile Call Minutes

```http
GET /api/organizations/{organization_id}/usage/reconcile?from=2026-02-01&to=2026-02-28
Authorization: Bearer YOUR_TOKEN
```

Compares CDR billable seconds (converted to minutes) against metered `call_minutes` usage records for the given date range. Returns `matched: true` when the totals agree within 0.01 minutes.

### Admin Dashboard

System-wide observability endpoint (admin-only):

```http
GET /api/admin/dashboard
Authorization: Bearer YOUR_TOKEN
```

Returns total organizations by status, per-organization resource counts, and aggregate system metrics.

### FreeSWITCH Modules Status

Platform-admin only live module visibility:

```http
GET /api/v1/admin/freeswitch/modules
Authorization: Bearer YOUR_TOKEN
```

**Success response** `200`:
```json
{
  "data": [
    { "name": "mod_sofia", "type": "endpoint", "status": "running" },
    { "name": "mod_conference", "type": "application", "status": "running" }
  ],
  "meta": {
    "source": "esl",
    "live": true
  }
}
```

**FreeSWITCH unavailable** `503`:
```json
{
  "data": [],
  "meta": {
    "source": "esl",
    "live": true,
    "error": "Unable to connect to FreeSWITCH ESL."
  }
}
```

Notes:
- Access is restricted to platform admins. Organization-scoped admins and regular users receive `403`.
- Module rows are normalized from live `show modules` output.
- Failure responses keep `data` empty and report the live-source metadata in `meta`.

### Supervisor Reports

All supervisor report endpoints are organization-scoped and require the same authenticated organization access used by other organization resources.

#### Call Summary

```http
GET /api/v1/organizations/{organization_id}/supervisor-reports/call-summary?date_from=2026-04-10&date_to=2026-04-10
Authorization: Bearer YOUR_TOKEN
```

Returns aggregated totals for the inclusive date range, including `totals.calls`, `totals.answered_calls`, `totals.missed_calls`, `totals.voicemail_calls`, duration totals, and `by_direction` counts.

#### Missed and Returned Calls

```http
GET /api/v1/organizations/{organization_id}/supervisor-reports/missed-returned-calls?date_from=2026-04-10&date_to=2026-04-10
Authorization: Bearer YOUR_TOKEN
```

Returns:
- `period`
- `returned_call_window_days`
- `summary.missed_calls`, `summary.returned_calls`, `summary.open_missed_calls`
- `items[]` with missed-call details plus `returned` and optional `returned_call`

You may optionally pass `window_days` to override the returned-call matching window.

#### Voicemails Needing Follow-Up

```http
GET /api/v1/organizations/{organization_id}/supervisor-reports/voicemails-needing-follow-up?date_from=2026-04-10&date_to=2026-04-10
Authorization: Bearer YOUR_TOKEN
```

Returns:
- `period`
- `returned_call_window_days`
- `summary.voicemails`, `summary.pending_follow_up`, `summary.needs_review`, `summary.needs_attention`
- `items[]` with voicemail event metadata, `follow_up_status`, optional `recording`, and optional `returned_call`

You may optionally pass `window_days` to override the returned-call matching window.

### SSL Management

Manage system-wide SSL certificates via Certbot / Let's Encrypt (admin-only):

#### Get SSL Settings
```http
GET /api/admin/ssl
Authorization: Bearer YOUR_TOKEN
```

#### Update SSL Settings
```http
PUT /api/admin/ssl
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "email": "admin@example.com",
  "is_enabled": true,
  "domains": ["nizam.example.com", "api.nizam.example.com"]
}
```

#### Request Certificate
```http
POST /api/admin/ssl/request
Authorization: Bearer YOUR_TOKEN
```
Triggers a manual Let's Encrypt certificate request/renewal.

### External Number Lookup

Organizations can configure an external number lookup URL in their settings for CNAM/caller-ID enrichment:

```http
PUT /api/organizations/{organization_id}/settings
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "settings": {
    "number_lookup_url": "https://your-api.com/lookup"
  }
}
```

When configured, NIZAM will send GET requests to this URL with `?number=+15551234567` and headers `X-Organization-Id` and `X-Organization-Domain`.

---

## Extensions

All extension endpoints are organization-scoped.

### List Extensions

```http
GET /api/organizations/{organization_id}/extensions
Authorization: Bearer YOUR_TOKEN
```

### Create Extension

```http
POST /api/organizations/{organization_id}/extensions
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "extension": "1001",
  "password": "sip-password-123",
  "directory_first_name": "John",
  "directory_last_name": "Doe",
  "effective_caller_id_name": "John Doe",
  "effective_caller_id_number": "1001",
  "voicemail_enabled": true,
  "voicemail_pin": "1234",
  "is_active": true
}
```

**Note:** Both `password` and `voicemail_pin` are stored as plaintext and included in API responses. `password` is accessible for webphone/sip.js integration. Webhook `secret` remains encrypted at rest and hidden from API responses.

**Security:** Since SIP credentials are transmitted in API responses, always enforce HTTPS in production and restrict API access via Sanctum token authentication and organization-scoped middleware.

### Get / Update / Delete Extension

```http
GET    /api/organizations/{organization_id}/extensions/{id}
PUT    /api/organizations/{organization_id}/extensions/{id}
DELETE /api/organizations/{organization_id}/extensions/{id}
```

### WebRTC Configuration

Get the complete connection suite for WebRTC clients (SIP.js, etc.):

```http
GET /api/organizations/{organization_id}/extensions/{id}/webrtc-config
Authorization: Bearer YOUR_TOKEN
```

**Response** `200`:
```json
{
  "data": {
    "enabled": true,
    "websocket_url": "wss://nizam.example.com:7443",
    "sip_uri": "sip:1001@acme.example.com",
    "sip_username": "1001",
    "sip_password": "encrypted-password",
    "sip_domain": "acme.example.com",
    "display_name": "John Doe",
    "ice_servers": [
      { "urls": "stun:stun.l.google.com:19302" }
    ],
    "codec_prefs": ["OPUS", "PCMU", "PCMA", "G722"]
  }
}
```

---

## DIDs (Inbound Numbers)

```http
GET    /api/organizations/{organization_id}/dids
POST   /api/organizations/{organization_id}/dids
GET    /api/organizations/{organization_id}/dids/{id}
PUT    /api/organizations/{organization_id}/dids/{id}
DELETE /api/organizations/{organization_id}/dids/{id}
```

### Create DID

```json
{
  "number": "+15551234567",
  "destination_type": "extension",
  "destination_id": "extension-uuid",
  "description": "Main line",
  "is_active": true
}
```

**Destination types:** `extension`, `ring_group`, `ivr`, `voicemail`, `time_condition`, `call_routing_policy`, `flow`, `bridge`

**Routing precedence:** NIZAM can store and resolve layered DID routes for the same number in this order:
1. gateway-registration-specific DID
2. gateway-specific DID
3. generic DID

---

## Call Routing Policies

Policy-driven routing: DID → policy → outcome. Conditions are AND-evaluated at runtime.

```http
GET    /api/organizations/{organization_id}/call-routing-policies
POST   /api/organizations/{organization_id}/call-routing-policies
GET    /api/organizations/{organization_id}/call-routing-policies/{id}
PUT    /api/organizations/{organization_id}/call-routing-policies/{id}
DELETE /api/organizations/{organization_id}/call-routing-policies/{id}
```

### Create Call Routing Policy

```json
{
  "name": "Business Hours Policy",
  "description": "Route based on business hours and caller ID",
  "conditions": [
    { "type": "time_of_day", "params": { "start": "09:00", "end": "17:00" } },
    { "type": "day_of_week", "params": { "days": ["mon", "tue", "wed", "thu", "fri"] } }
  ],
  "match_destination_type": "extension",
  "match_destination_id": "extension-uuid",
  "no_match_destination_type": "voicemail",
  "no_match_destination_id": "extension-uuid",
  "priority": 10,
  "is_active": true
}
```

**Condition Types:**

| Type | Params | Description |
|------|--------|-------------|
| `time_of_day` | `start`, `end` (HH:MM) | Match within a time range |
| `day_of_week` | `days` (array: mon, tue, etc.) | Match on specific days |
| `caller_id_pattern` | `pattern` (wildcard string) | Match caller ID with `*` wildcard |
| `blacklist` | `numbers` (array of E.164) | Reject if caller is in list |
| `geo_prefix` | `prefixes` (array of dial prefixes) | Match caller by geographic prefix |

**Match/No-Match Destination Types:** `extension`, `ring_group`, `ivr`, `voicemail`, `flow`, `bridge`

Policies are returned ordered by `priority` (ascending). When a DID routes to a policy, conditions are evaluated top-down. If all conditions match, the call routes to `match_destination`. Otherwise, it routes to `no_match_destination`.

**Supported destination types in policy routing:** `extension`, `ring_group`, `ivr`, `voicemail`, `flow`, `bridge`

---

## Call Flows

Composable call flow graphs. Each flow is a sequence of nodes that are compiled into FreeSWITCH dialplan actions.

```http
GET    /api/organizations/{organization_id}/call-flows
POST   /api/organizations/{organization_id}/call-flows
GET    /api/organizations/{organization_id}/call-flows/{id}
PUT    /api/organizations/{organization_id}/call-flows/{id}
DELETE /api/organizations/{organization_id}/call-flows/{id}
```

### Create Call Flow

```json
{
  "name": "Welcome Flow",
  "description": "Play greeting then bridge to extension",
  "nodes": [
    {
      "id": "start",
      "type": "play_prompt",
      "data": { "file": "welcome.wav" },
      "next": "bridge1"
    },
    {
      "id": "bridge1",
      "type": "bridge",
      "data": { "destination_type": "extension", "destination_id": "ext-uuid" },
      "next": null
    }
  ],
  "is_active": true
}
```

**Node Types:**

| Type | Data Fields | Description |
|------|-------------|-------------|
| `play_prompt` | `file` | Play an audio file |
| `collect_input` | `min_digits`, `max_digits`, `timeout`, `file` | Play prompt and collect DTMF digits |
| `bridge` | `destination_type`, `destination_id` | Bridge call to a destination |

> Compiled flows now execute in the organization dialplan context instead of transferring through `XML default`.
| `record` | `path` | Record the call |
| `webhook` | `url` | Make an HTTP request to an external URL |
| `api_call` | (varies) | Call an external API |
| `branch` | (varies) | Conditional branching |

Each node has an `id` (unique within the flow), a `type`, a `data` object, and an optional `next` pointer to the next node ID.

---

## Webhook Delivery Attempts

View the delivery history for any webhook. Each attempt is logged with status, response, and error details.

```http
GET /api/organizations/{organization_id}/webhooks/{webhook_id}/delivery-attempts
Authorization: Bearer YOUR_TOKEN
```

**Response** `200`:
```json
{
  "data": [
    {
      "id": "uuid",
      "webhook_id": "uuid",
      "event_type": "call.started",
      "payload": { "call_uuid": "abc-123", "caller": "+15551234567" },
      "response_status": 200,
      "attempt": 1,
      "success": true,
      "error_message": null,
      "delivered_at": "2026-02-28T10:00:00.000000Z",
      "created_at": "2026-02-28T10:00:00.000000Z"
    }
  ]
}
```

Delivery attempts are created automatically when the `DeliverWebhook` job runs. Failed deliveries include `error_message` and `response_status`. The job retries up to 3 times with exponential backoff (10s, 60s, 300s). After all retries are exhausted, a dead-letter entry is recorded.

### Webhook Delivery Stats

```http
GET /api/organizations/{organization_id}/webhooks/{webhook_id}/delivery-stats
Authorization: Bearer YOUR_TOKEN
```

**Response** `200`:
```json
{
  "data": {
    "total_attempts": 100,
    "successful": 95,
    "failed": 5,
    "success_rate": 95.0,
    "avg_latency_ms": 150.5,
    "recent_failures": []
  }
}
```

---

## Ring Groups

```http
GET    /api/organizations/{organization_id}/ring-groups
POST   /api/organizations/{organization_id}/ring-groups
GET    /api/organizations/{organization_id}/ring-groups/{id}
PUT    /api/organizations/{organization_id}/ring-groups/{id}
DELETE /api/organizations/{organization_id}/ring-groups/{id}
```

### Create Ring Group

```json
{
  "name": "Sales Team",
  "strategy": "simultaneous",
  "members": ["ext-uuid-1", "ext-uuid-2"],
  "ring_timeout": 30,
  "fallback_destination_type": "bridge",
  "fallback_destination_id": "bridge-uuid",
  "is_active": true
}
```

**Strategies:** `simultaneous`, `sequential`

**Fallback behavior:** ring groups now compile fallback routing into the dialplan. Fallback is executed directly when there are no active members, or after the member bridge fails on no-answer / unavailable style outcomes.

**Supported fallback destination types:** `extension`, `ring_group`, `ivr`, `time_condition`, `voicemail`, `flow`, `bridge`

---

## IVR Menus

```http
GET    /api/organizations/{organization_id}/ivrs
POST   /api/organizations/{organization_id}/ivrs
GET    /api/organizations/{organization_id}/ivrs/{id}
PUT    /api/organizations/{organization_id}/ivrs/{id}
DELETE /api/organizations/{organization_id}/ivrs/{id}
```

### Create IVR

```json
{
  "name": "Main Menu",
  "greet_long": "/sounds/greeting.wav",
  "greet_short": "/sounds/greeting_short.wav",
  "options": {
    "1": { "type": "extension", "id": "ext-uuid" },
    "2": { "type": "ring_group", "id": "rg-uuid" },
    "9": { "type": "ivr", "id": "sub-ivr-uuid" }
  },
  "timeout": 10,
  "max_failures": 3,
  "timeout_destination_type": "bridge",
  "timeout_destination_id": "bridge-uuid",
  "is_active": true
}
```

**Supported timeout destination types:** `extension`, `ring_group`, `ivr`, `voicemail`, `flow`, `bridge`

---

## Time Conditions

```http
GET    /api/organizations/{organization_id}/time-conditions
POST   /api/organizations/{organization_id}/time-conditions
GET    /api/organizations/{organization_id}/time-conditions/{id}
PUT    /api/organizations/{organization_id}/time-conditions/{id}
DELETE /api/organizations/{organization_id}/time-conditions/{id}
```

---

## CDRs (Call Detail Records)

Read-only. Created automatically when calls end and enriched asynchronously.

```http
GET /api/organizations/{organization_id}/cdrs
GET /api/organizations/{organization_id}/cdrs/{id}
```

**Query Parameters** (for list endpoint):

| Parameter | Description |
|-----------|-------------|
| `q` | Full-text search on caller, destination, or UUID |
| `call_type` | Filter by `inbound`, `outbound`, `internal`, `emergency` |
| `direction` | Filter by `inbound`, `outbound` |
| `quality_score_min` | Filter by minimum quality score (0-5) |
| `hangup_cause` | Filter by FreeSWITCH hangup cause |
| `start_date` | Filter CDRs after this ISO8601 datetime |
| `end_date` | Filter CDRs before this ISO8601 datetime |

### CDR Enrichment & Quality Metrics

Every CDR is enriched with:
- **Quality Metrics**: MOS (Mean Opinion Score), Jitter, Latency, and Packet Loss.
- **Geolocation**: Country and carrier information for external numbers.
- **Metadata**: SIP User Agent, remote media IP, and custom tags.

### CDR Analytics

Get high-level insights into call performance and trends.

#### Summary Analytics
```http
GET /api/organizations/{organization_id}/cdrs/analytics/summary
```
Returns: `total_calls`, `answered_calls`, `asr` (Answer Seizure Ratio), `acd` (Average Call Duration).

#### Volume Trends
```http
GET /api/organizations/{organization_id}/cdrs/analytics/volume?interval=day
```
Returns time-series data for call volume.

#### Quality Trends
```http
GET /api/organizations/{organization_id}/cdrs/analytics/quality
```
Returns MOS and packet loss trends over time.

#### Top Destinations
```http
GET /api/organizations/{organization_id}/cdrs/analytics/destinations
```
Returns top dialed numbers or countries.

### Export CDRs

#### Simple Export (CSV)
```http
GET /api/organizations/{organization_id}/cdrs/export?format=csv
```

#### Advanced Export (JSON/CSV)
```http
POST /api/organizations/{organization_id}/cdrs/export
Content-Type: application/json

{
  "format": "json",
  "filters": {
    "call_type": "outbound",
    "quality_score_min": 4.0
  }
}
```

---

## Call Events & Trace

### List Call Events

```http
GET /api/organizations/{organization_id}/call-events
Authorization: Bearer YOUR_TOKEN
```

**Query Parameters:**
| Parameter | Description |
|-----------|-------------|
| `call_uuid` | Filter by call UUID |
| `event_type` | Filter by event type (e.g., `started`, `hangup`) |
| `from` | Filter events after this datetime |
| `to` | Filter events before this datetime |

### Call Trace

Get the complete lifecycle of a specific call:

```http
GET /api/organizations/{organization_id}/call-events/{call_uuid}/trace
Authorization: Bearer YOUR_TOKEN
```

**Response** `200`:
```json
{
  "call_uuid": "abc-123-def",
  "event_count": 4,
  "events": [
    { "event_type": "call.created", "occurred_at": "...", "payload": {...} },
    { "event_type": "call.answered", "occurred_at": "...", "payload": {...} },
    { "event_type": "call.bridged", "occurred_at": "...", "payload": {...} },
    { "event_type": "call.hangup", "occurred_at": "...", "payload": {...} }
  ]
}
```

### Event Replay

Replay a specific stored event by its UUID for debugging or webhook retry:

```http
GET /api/organizations/{organization_id}/call-events/replay/{event_id}
Authorization: Bearer YOUR_TOKEN
```

**Response** `200`:
```json
{
  "id": "event-uuid",
  "call_uuid": "abc-123",
  "event_type": "call.created",
  "schema_version": "1.0",
  "payload": { "organization_id": "...", "call_uuid": "...", "metadata": {...} },
  "occurred_at": "2026-01-15T10:30:00.000Z"
}
```

### Real-Time Event Stream (SSE)

Subscribe to real-time call events via Server-Sent Events:

```http
GET /api/organizations/{organization_id}/call-events/stream
Authorization: Bearer YOUR_TOKEN
```

**Query Parameters:**
| Parameter | Description |
|-----------|-------------|
| `call_uuid` | Filter stream to a specific call UUID |
| `event_types` | Comma-separated list of event types to filter (e.g., `call.created,call.hangup`) |

**Headers:**
| Header | Description |
|--------|-------------|
| `Last-Event-ID` | Resume from a specific event ID after reconnection |

**Response** `200` (SSE stream):
```
id: 42
event: started
data: {"id":42,"call_uuid":"abc-123","event_type":"started","payload":{...},"occurred_at":"2026-01-15T10:30:00.000Z"}

id: 43
event: answered
data: {"id":43,"call_uuid":"abc-123","event_type":"answered","payload":{...},"occurred_at":"2026-01-15T10:30:05.000Z"}

: heartbeat
```

The stream sends heartbeat comments every 15 seconds and auto-disconnects after 5 minutes (clients should reconnect using `Last-Event-ID`). Maximum 50 concurrent connections per organization.

### ESL Listener Commands

| Command | Description |
|---|---|
| `php artisan freeswitch:listen` | Start ESL event listener with auto-reconnection |
| `php artisan freeswitch:listen --max-retries=5` | ESL listener with limited reconnection attempts |

---

## Device Profiles

```http
GET    /api/organizations/{organization_id}/device-profiles
POST   /api/organizations/{organization_id}/device-profiles
GET    /api/organizations/{organization_id}/device-profiles/{id}
PUT    /api/organizations/{organization_id}/device-profiles/{id}
DELETE /api/organizations/{organization_id}/device-profiles/{id}
```

---

## Gateways

Gateways support richer carrier registration and outbound routing fields.

```http
GET    /api/organizations/{organization_id}/gateways
POST   /api/organizations/{organization_id}/gateways
GET    /api/organizations/{organization_id}/gateways/{id}
PUT    /api/organizations/{organization_id}/gateways/{id}
DELETE /api/organizations/{organization_id}/gateways/{id}
```

**Extended fields:** `register`, `proxy`, `register_proxy`, `from_domain`, `extension`, `expire_seconds`, `retry_seconds`, `caller_id_in_from`, `profile`

**Example:**

```json
{
  "name": "Carrier A",
  "host": "sip.carrier.test",
  "proxy": "proxy.carrier.test:5060",
  "register_proxy": "reg.carrier.test:5060",
  "port": 5060,
  "username": "user1",
  "password": "secret",
  "realm": "carrier.test",
  "from_domain": "from.carrier.test",
  "extension": "8801555000000",
  "transport": "udp",
  "register": true,
  "expire_seconds": 600,
  "retry_seconds": 15,
  "caller_id_in_from": true,
  "profile": "external",
  "is_active": true
}
```

## Bridges

```http
GET    /api/organizations/{organization_id}/bridges
POST   /api/organizations/{organization_id}/bridges
GET    /api/organizations/{organization_id}/bridges/{id}
PUT    /api/organizations/{organization_id}/bridges/{id}
DELETE /api/organizations/{organization_id}/bridges/{id}
```

Bridge objects provide reusable outbound targets for DIDs, routing policies, time conditions, IVR timeout routing, and ring-group fallbacks.

**Bridge types:** `gateway`, `raw`

**Gateway bridge example:**

```json
{
  "name": "PSTN Out",
  "bridge_type": "gateway",
  "gateway_id": "gateway-uuid",
  "destination_template": "+15551234567",
  "is_active": true
}
```

**Raw bridge example:**

```json
{
  "name": "Direct Sofia",
  "bridge_type": "raw",
  "destination_template": "sofia/external/support@example.com",
  "is_active": true
}
```

---

## Webhooks

```http
GET    /api/organizations/{organization_id}/webhooks
POST   /api/organizations/{organization_id}/webhooks
GET    /api/organizations/{organization_id}/webhooks/{id}
PUT    /api/organizations/{organization_id}/webhooks/{id}
DELETE /api/organizations/{organization_id}/webhooks/{id}
```

### Create Webhook

```json
{
  "url": "https://your-app.com/webhook",
  "events": ["call.started", "call.hangup", "voicemail.received"],
  "secret": "your-hmac-secret",
  "is_active": true
}
```

**Webhook Payload Headers:**
```
Content-Type: application/json
X-Nizam-Signature: sha256=<hmac-hash>
X-Nizam-Event: call.hangup
```

**Normalized Event Types:**

| Event Type | Source | Description |
|-----------|--------|-------------|
| `call.created` | `CHANNEL_CREATE` | Call leg created in FreeSWITCH |
| `call.started` | `CHANNEL_CREATE` | Call initiated (application layer) |
| `call.answered` | `CHANNEL_ANSWER` | Call answered |
| `call.bridge` | `CHANNEL_BRIDGE` | Call legs bridged (includes `other_leg_uuid`) |
| `call.ended` | `CHANNEL_HANGUP` | Call leg ended (hangup cause available) |
| `call.completed`| `CHANNEL_HANGUP_COMPLETE` | Call sequence finished (final billing data) |
| `call.hangup` | `CHANNEL_HANGUP_COMPLETE` | Legacy event for call end |
| `call.missed` | `CHANNEL_HANGUP_COMPLETE` | Missed call (hangup cause = `NO_ANSWER`) |
| `voicemail.received` | `CUSTOM vm::maintenance` | New voicemail message |
| `registration.registered` | `CUSTOM sofia::register` | SIP device registered |
| `registration.unregistered` | `CUSTOM sofia::unregister` | SIP device unregistered |

**Available Events:**
- `call.started` — Call initiated
- `call.answered` — Call answered
- `call.bridge` — Call legs bridged
- `call.missed` — Missed call (NO_ANSWER)
- `call.hangup` — Call ended
- `recording.created` — New recording saved
- `voicemail.received` — New voicemail
- `device.registered` — SIP device registered
- `registration.registered` — SIP device registered (via ESL)
- `registration.unregistered` — SIP device unregistered (via ESL)
- `extension.created` — Extension created via API
- `extension.updated` — Extension updated via API
- `extension.deleted` — Extension deleted via API
- `did.created` — DID created via API
- `did.updated` — DID updated via API
- `did.deleted` — DID deleted via API
- `organization.updated` — Organization configuration changed

---

## Call Operations

### Originate Call

```http
POST /api/organizations/{organization_id}/calls/originate
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "extension": "1001",
  "destination": "1002"
}
```

**Gateway-backed originate:**

```json
{
  "extension": "1001",
  "destination": "+15551234567",
  "gateway_id": "gateway-uuid"
}
```

When `gateway_id` is present, NIZAM originates the call locally from the extension and bridges through `sofia/gateway/v_<gateway_id>/<destination>`.

### Call Status

```http
GET /api/organizations/{organization_id}/calls/status
Authorization: Bearer YOUR_TOKEN
```

### Hangup Call

```http
POST /api/organizations/{organization_id}/calls/hangup
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "uuid": "call-uuid-here",
  "cause": "NORMAL_CLEARING"
}
```

| Field | Required | Description |
|-------|----------|-------------|
| uuid | Yes | Call UUID to hang up |
| cause | No | Hangup cause (default: NORMAL_CLEARING) |

### Transfer Call

```http
POST /api/organizations/{organization_id}/calls/transfer
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "uuid": "call-uuid-here",
  "destination": "1002",
  "leg": "aleg"
}
```

| Field | Required | Description |
|-------|----------|-------------|
| uuid | Yes | Call UUID to transfer |
| destination | Yes | Transfer destination |
| leg | No | Transfer leg: aleg, bleg, both |

### Toggle Recording

```http
POST /api/organizations/{organization_id}/calls/recording
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "uuid": "call-uuid-here",
  "action": "start"
}
```

| Field | Required | Description |
|-------|----------|-------------|
| uuid | Yes | Call UUID |
| action | Yes | start or stop |

### Hold / Unhold

```http
POST /api/organizations/{organization_id}/calls/hold
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "uuid": "call-uuid-here",
  "action": "hold"
}
```

| Field | Required | Description |
|-------|----------|-------------|
| uuid | Yes | Call UUID |
| action | Yes | hold or unhold |

---

## Policy Evaluation API

Test a call routing policy against a given context without routing a real call.

```http
POST /api/organizations/{organization_id}/call-routing-policies/{policy_id}/evaluate
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "did": "+15551234567",
  "caller_id": "5559876543",
  "time": "2026-01-15T12:00:00Z",
  "metadata": {}
}
```

**Response** `200`:
```json
{
  "policy_id": "uuid",
  "policy_name": "Business Hours",
  "context": { "organization_id": "uuid", "did": "+15551234567", "caller_id": "5559876543" },
  "decision": { "decision": "allow" }
}
```

Possible decisions: `allow`, `redirect`, `reject`, `modify`.

---

## Event Re-dispatch

Re-dispatch a stored event to all matching webhooks. Required for debugging and webhook retries.

```http
POST /api/organizations/{organization_id}/call-events/redispatch/{event_id}
Authorization: Bearer YOUR_TOKEN
```

**Response** `200`:
```json
{
  "message": "Event re-dispatched to webhooks.",
  "event_id": "uuid",
  "event_type": "call.created"
}
```

---

## Rate Limiting

All authenticated endpoints are rate-limited to **60 requests per minute** per user (or per IP for unauthenticated endpoints).

Response headers:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
```

When rate limit is exceeded:
```http
HTTP/1.1 429 Too Many Requests
Retry-After: 30
```

---

## User Management (Admin Only)

### List Users

```http
GET /api/users
Authorization: Bearer {token}
```

Query parameters: `organization_id`, `role`

**Response** `200`:
```json
{
  "data": [
    { "id": 1, "name": "John Doe", "email": "john@example.com", "role": "user", "organization_id": "uuid" }
  ]
}
```

### Create User

```http
POST /api/users
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "password": "password123",
  "role": "user",
  "organization_id": "organization-uuid"
}
```

### Update User

```http
PUT /api/users/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Updated Name",
  "role": "admin"
}
```

### Delete User

```http
DELETE /api/users/{id}
Authorization: Bearer {token}
```

### View User Permissions

```http
GET /api/users/{id}/permissions
Authorization: Bearer {token}
```

**Response** `200`:
```json
{
  "permissions": ["extensions.view", "extensions.create"]
}
```

### Grant Permissions

```http
POST /api/users/{id}/permissions/grant
Authorization: Bearer {token}
Content-Type: application/json

{
  "permissions": ["extensions.view", "extensions.create", "dids.view"]
}
```

### Revoke Permissions

```http
POST /api/users/{id}/permissions/revoke
Authorization: Bearer {token}
Content-Type: application/json

{
  "permissions": ["extensions.create"]
}
```

### List Available Permissions

```http
GET /api/permissions
Authorization: Bearer {token}
```

**Response** `200`:
```json
{
  "permissions": [
    { "slug": "extensions.view", "description": "View extensions", "module": "core" },
    { "slug": "extensions.create", "description": "Create extensions", "module": "core" }
  ]
}
```

---

## Recordings

### List Recordings

```http
GET /api/organizations/{organization}/recordings
Authorization: Bearer {token}
```

Query parameters: `call_uuid`, `caller_id_number`, `destination_number`, `date_from`, `date_to`

**Response** `200`:
```json
{
  "data": [
    {
      "id": 1,
      "call_uuid": "uuid",
      "file_name": "uuid.wav",
      "file_size": 245000,
      "format": "wav",
      "duration": 30,
      "direction": "inbound",
      "caller_id_number": "+15551234567",
      "destination_number": "1001",
      "created_at": "2026-01-15T10:30:00Z"
    }
  ]
}
```

### Show Recording

```http
GET /api/organizations/{organization}/recordings/{id}
Authorization: Bearer {token}
```

### Download Recording

```http
GET /api/organizations/{organization}/recordings/{id}/download
Authorization: Bearer {token}
```

Returns the recording file as a download.

### Delete Recording

```http
DELETE /api/organizations/{organization}/recordings/{id}
Authorization: Bearer {token}
```

---

## Call Sessions

View active and completed call sessions with full trace events.

```http
GET    /api/organizations/{organization_id}/calls
GET    /api/organizations/{organization_id}/calls/{callSession}
GET    /api/organizations/{organization_id}/calls/{callSession}/analyze
```

### List Call Sessions

```http
GET /api/organizations/{organization_id}/calls
Authorization: Bearer YOUR_TOKEN
```

**Response** `200`:
```json
{
  "data": [
    {
      "id": "uuid",
      "organization_id": "uuid",
      "call_uuid": "abc-123",
      "caller_id_number": "+15551234567",
      "destination_number": "1001",
      "direction": "inbound",
      "start_stamp": "2026-01-15T10:30:00Z",
      "answer_stamp": "2026-01-15T10:30:05Z",
      "end_stamp": "2026-01-15T10:30:35Z",
      "duration": 35,
      "billsec": 30,
      "hangup_cause": "NORMAL_CLEARING"
    }
  ]
}
```

### Get Call Session

```http
GET /api/organizations/{organization_id}/calls/{callSession}
Authorization: Bearer YOUR_TOKEN
```

Returns the call session with its trace events.

### Analyze Call Session

```http
GET /api/organizations/{organization_id}/calls/{callSession}/analyze
Authorization: Bearer YOUR_TOKEN
```

Returns computed replay timeline and node metrics for the call session.

---

## Holiday Calendars

```http
GET    /api/organizations/{organization_id}/holiday-calendars
POST   /api/organizations/{organization_id}/holiday-calendars
GET    /api/organizations/{organization_id}/holiday-calendars/{id}
PUT    /api/organizations/{organization_id}/holiday-calendars/{id}
DELETE /api/organizations/{organization_id}/holiday-calendars/{id}
```

### Create Holiday Calendar

```json
{
  "name": "Company Holidays",
  "description": "Company-wide holidays",
  "is_active": true
}
```

---

## Schedules

```http
GET    /api/organizations/{organization_id}/schedules
POST   /api/organizations/{organization_id}/schedules
GET    /api/organizations/{organization_id}/schedules/{id}
PUT    /api/organizations/{organization_id}/schedules/{id}
DELETE /api/organizations/{organization_id}/schedules/{id}
```

### Create Schedule

```json
{
  "name": "Business Hours",
  "description": "Standard business hours",
  "timezone": "Asia/Dhaka",
  "is_active": true
}
```

---

## Teams

```http
GET    /api/organizations/{organization_id}/teams
POST   /api/organizations/{organization_id}/teams
GET    /api/organizations/{organization_id}/teams/{id}
PUT    /api/organizations/{organization_id}/teams/{id}
DELETE /api/organizations/{organization_id}/teams/{id}
```

### Create Team

```json
{
  "name": "Support Team",
  "description": "Customer support agents",
  "is_active": true
}
```

---

## Audit Logs

Read-only API for querying audit trail entries. All domain model changes (create, update, delete) are automatically logged.

### List Audit Logs

```http
GET /api/organizations/{organization_id}/audit-logs
Authorization: Bearer YOUR_TOKEN
```

**Query Parameters:**
| Parameter | Description |
|-----------|-------------|
| `action` | Filter by action type (`created`, `updated`, `deleted`) |
| `auditable_type` | Filter by model type (e.g., `App\Models\Extension`) |
| `user_id` | Filter by user who performed the action |
| `from` | Filter logs after this datetime |
| `to` | Filter logs before this datetime |

### Show Audit Log

```http
GET /api/organizations/{organization_id}/audit-logs/{id}
Authorization: Bearer YOUR_TOKEN
```

**Response** `200`:
```json
{
  "data": {
    "id": "uuid",
    "organization_id": "uuid",
    "user_id": 1,
    "action": "updated",
    "auditable_type": "App\\Models\\Extension",
    "auditable_id": "uuid",
    "old_values": { "name": "Old Name" },
    "new_values": { "name": "New Name" },
    "ip_address": "192.168.1.1",
    "user_agent": "Mozilla/5.0...",
    "created_at": "2026-01-15T10:30:00.000000Z"
  }
}
```

---

## Error Responses

All errors follow a consistent format:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "extension": ["The extension field is required."]
  }
}
```

| Status | Meaning |
|--------|---------|
| `401` | Unauthenticated — invalid or missing token |
| `403` | Forbidden — insufficient permissions or wrong organization |
| `404` | Not found |
| `422` | Validation error |
| `429` | Rate limit exceeded |
| `500` | Server error |
