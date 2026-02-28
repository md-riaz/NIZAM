# API Reference

All NIZAM API endpoints return JSON responses and require authentication unless noted otherwise.

---

## Authentication

NIZAM uses [Laravel Sanctum](https://laravel.com/docs/sanctum) bearer tokens.

### Register

```http
POST /api/auth/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password",
  "password_confirmation": "password"
}
```

**Response** `201`:
```json
{
  "user": { "id": "uuid", "name": "John Doe", "email": "john@example.com" },
  "token": "1|abc123..."
}
```

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

## Tenants

Admin-only for create/update/delete. Regular users can only view their own tenant.

### List Tenants

```http
GET /api/tenants
Authorization: Bearer YOUR_TOKEN
```

### Create Tenant

```http
POST /api/tenants
Authorization: Bearer YOUR_TOKEN (admin)
Content-Type: application/json

{
  "name": "Acme Corp",
  "domain": "acme.example.com",
  "slug": "acme",
  "max_extensions": 100,
  "is_active": true
}
```

### Get / Update / Delete Tenant

```http
GET    /api/tenants/{id}
PUT    /api/tenants/{id}
DELETE /api/tenants/{id}
```

### Tenant Settings

Get and merge-update tenant settings (stored as JSON):

```http
GET /api/tenants/{id}/settings
Authorization: Bearer YOUR_TOKEN
```

**Response** `200`:
```json
{
  "data": { "timezone": "America/New_York", "recording_format": "wav" }
}
```

```http
PUT /api/tenants/{id}/settings
Authorization: Bearer YOUR_TOKEN (admin)
Content-Type: application/json

{
  "settings": { "recording_format": "mp3", "max_ring_time": 30 }
}
```

Settings are **merged** — existing keys are preserved unless explicitly overwritten.

### Tenant Statistics

Dashboard-style aggregate counts for all tenant resources:

```http
GET /api/tenants/{tenant_id}/stats
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

### Tenant Provisioning (Zero-Touch)

Create a tenant with automated onboarding — auto-generates domain, bootstraps default extension 1000.

```http
POST /api/tenants/provision
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "name": "Acme Corp",
  "domain": "acme.nizam.local",
  "slug": "acme-corp",
  "max_extensions": 50,
  "max_concurrent_calls": 20,
  "max_dids": 10,
  "max_ring_groups": 5
}
```

Only `name` is required. Domain and slug are auto-generated if not provided. Tenant starts in `trial` status.

### Usage Metering

#### Get Usage Summary

```http
GET /api/tenants/{tenant_id}/usage/summary?from=2026-02-01&to=2026-02-28
Authorization: Bearer YOUR_TOKEN
```

Returns aggregated usage metrics (call_minutes, concurrent_call_peak, recording_storage_bytes, active_devices, active_extensions) for the given date range.

#### Collect Usage Snapshot

```http
POST /api/tenants/{tenant_id}/usage/collect
Authorization: Bearer YOUR_TOKEN
```

Records a point-in-time snapshot of current resource usage for the tenant.

### Admin Dashboard

System-wide observability endpoint (admin-only):

```http
GET /api/admin/dashboard
Authorization: Bearer YOUR_TOKEN
```

Returns total tenants by status, per-tenant resource counts, and aggregate system metrics.

### External Number Lookup

Tenants can configure an external number lookup URL in their settings for CNAM/caller-ID enrichment:

```http
PUT /api/tenants/{tenant_id}/settings
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "settings": {
    "number_lookup_url": "https://your-api.com/lookup"
  }
}
```

When configured, NIZAM will send GET requests to this URL with `?number=+15551234567` and headers `X-Tenant-Id` and `X-Tenant-Domain`.

---

## Extensions

All extension endpoints are tenant-scoped.

### List Extensions

```http
GET /api/tenants/{tenant_id}/extensions
Authorization: Bearer YOUR_TOKEN
```

### Create Extension

```http
POST /api/tenants/{tenant_id}/extensions
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

**Security:** Since SIP credentials are transmitted in API responses, always enforce HTTPS in production and restrict API access via Sanctum token authentication and tenant-scoped middleware.

### Get / Update / Delete Extension

```http
GET    /api/tenants/{tenant_id}/extensions/{id}
PUT    /api/tenants/{tenant_id}/extensions/{id}
DELETE /api/tenants/{tenant_id}/extensions/{id}
```

---

## DIDs (Inbound Numbers)

```http
GET    /api/tenants/{tenant_id}/dids
POST   /api/tenants/{tenant_id}/dids
GET    /api/tenants/{tenant_id}/dids/{id}
PUT    /api/tenants/{tenant_id}/dids/{id}
DELETE /api/tenants/{tenant_id}/dids/{id}
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

**Destination types:** `extension`, `ring_group`, `ivr`, `voicemail`, `time_condition`, `call_routing_policy`, `call_flow`

---

## Call Routing Policies

Policy-driven routing: DID → policy → outcome. Conditions are AND-evaluated at runtime.

```http
GET    /api/tenants/{tenant_id}/call-routing-policies
POST   /api/tenants/{tenant_id}/call-routing-policies
GET    /api/tenants/{tenant_id}/call-routing-policies/{id}
PUT    /api/tenants/{tenant_id}/call-routing-policies/{id}
DELETE /api/tenants/{tenant_id}/call-routing-policies/{id}
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

**Match/No-Match Destination Types:** `extension`, `ring_group`, `ivr`, `voicemail`, `call_flow`

Policies are returned ordered by `priority` (ascending). When a DID routes to a policy, conditions are evaluated top-down. If all conditions match, the call routes to `match_destination`. Otherwise, it routes to `no_match_destination`.

---

## Call Flows

Composable call flow graphs. Each flow is a sequence of nodes that are compiled into FreeSWITCH dialplan actions.

```http
GET    /api/tenants/{tenant_id}/call-flows
POST   /api/tenants/{tenant_id}/call-flows
GET    /api/tenants/{tenant_id}/call-flows/{id}
PUT    /api/tenants/{tenant_id}/call-flows/{id}
DELETE /api/tenants/{tenant_id}/call-flows/{id}
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
| `record` | `path` | Record the call |
| `webhook` | `url` | Make an HTTP request to an external URL |
| `api_call` | (varies) | Call an external API |
| `branch` | (varies) | Conditional branching |

Each node has an `id` (unique within the flow), a `type`, a `data` object, and an optional `next` pointer to the next node ID.

---

## Webhook Delivery Attempts

View the delivery history for any webhook. Each attempt is logged with status, response, and error details.

```http
GET /api/tenants/{tenant_id}/webhooks/{webhook_id}/delivery-attempts
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

Delivery attempts are created automatically when the `DeliverWebhook` job runs. Failed deliveries include `error_message` and `response_status`. The job retries up to 3 times with exponential backoff (10s, 60s, 300s).

---

## Ring Groups

```http
GET    /api/tenants/{tenant_id}/ring-groups
POST   /api/tenants/{tenant_id}/ring-groups
GET    /api/tenants/{tenant_id}/ring-groups/{id}
PUT    /api/tenants/{tenant_id}/ring-groups/{id}
DELETE /api/tenants/{tenant_id}/ring-groups/{id}
```

### Create Ring Group

```json
{
  "name": "Sales Team",
  "strategy": "simultaneous",
  "members": ["ext-uuid-1", "ext-uuid-2"],
  "ring_timeout": 30,
  "timeout_destination_type": "voicemail",
  "timeout_destination_id": "ext-uuid-1",
  "is_active": true
}
```

**Strategies:** `simultaneous`, `sequential`

---

## IVR Menus

```http
GET    /api/tenants/{tenant_id}/ivrs
POST   /api/tenants/{tenant_id}/ivrs
GET    /api/tenants/{tenant_id}/ivrs/{id}
PUT    /api/tenants/{tenant_id}/ivrs/{id}
DELETE /api/tenants/{tenant_id}/ivrs/{id}
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
  "timeout_destination_type": "extension",
  "timeout_destination_id": "ext-uuid",
  "is_active": true
}
```

---

## Time Conditions

```http
GET    /api/tenants/{tenant_id}/time-conditions
POST   /api/tenants/{tenant_id}/time-conditions
GET    /api/tenants/{tenant_id}/time-conditions/{id}
PUT    /api/tenants/{tenant_id}/time-conditions/{id}
DELETE /api/tenants/{tenant_id}/time-conditions/{id}
```

---

## CDRs (Call Detail Records)

Read-only. Created automatically when calls end.

```http
GET /api/tenants/{tenant_id}/cdrs
GET /api/tenants/{tenant_id}/cdrs/{id}
```

**Query Parameters** (for list endpoint):

| Parameter | Description |
|-----------|-------------|
| `direction` | Filter by direction (`inbound`, `outbound`, `local`) |
| `uuid` | Filter by call UUID |
| `hangup_cause` | Filter by hangup cause |
| `caller_id_number` | Filter by caller ID number |
| `destination_number` | Filter by destination number |
| `date_from` | Filter CDRs after this datetime |
| `date_to` | Filter CDRs before this datetime |

### Export CDRs as CSV

Stream CDRs as a downloadable CSV file. Supports the same filters as the list endpoint. Limited to 10,000 records.

```http
GET /api/tenants/{tenant_id}/cdrs/export
Authorization: Bearer YOUR_TOKEN
```

**Response**: Streamed CSV with headers: `uuid`, `caller_id_name`, `caller_id_number`, `destination_number`, `direction`, `start_stamp`, `answer_stamp`, `end_stamp`, `duration`, `billsec`, `hangup_cause`.

---

## Call Events & Trace

### List Call Events

```http
GET /api/tenants/{tenant_id}/call-events
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
GET /api/tenants/{tenant_id}/call-events/{call_uuid}/trace
Authorization: Bearer YOUR_TOKEN
```

**Response** `200`:
```json
{
  "call_uuid": "abc-123-def",
  "event_count": 4,
  "events": [
    { "event_type": "started", "occurred_at": "...", "payload": {...} },
    { "event_type": "answered", "occurred_at": "...", "payload": {...} },
    { "event_type": "bridge", "occurred_at": "...", "payload": {...} },
    { "event_type": "hangup", "occurred_at": "...", "payload": {...} }
  ]
}
```

### Real-Time Event Stream (SSE)

Subscribe to real-time call events via Server-Sent Events:

```http
GET /api/tenants/{tenant_id}/call-events/stream
Authorization: Bearer YOUR_TOKEN
```

**Query Parameters:**
| Parameter | Description |
|-----------|-------------|
| `call_uuid` | Filter stream to a specific call UUID |

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

The stream sends heartbeat comments every 15 seconds and auto-disconnects after 5 minutes (clients should reconnect using `Last-Event-ID`).

---

## Device Profiles

```http
GET    /api/tenants/{tenant_id}/device-profiles
POST   /api/tenants/{tenant_id}/device-profiles
GET    /api/tenants/{tenant_id}/device-profiles/{id}
PUT    /api/tenants/{tenant_id}/device-profiles/{id}
DELETE /api/tenants/{tenant_id}/device-profiles/{id}
```

---

## Webhooks

```http
GET    /api/tenants/{tenant_id}/webhooks
POST   /api/tenants/{tenant_id}/webhooks
GET    /api/tenants/{tenant_id}/webhooks/{id}
PUT    /api/tenants/{tenant_id}/webhooks/{id}
DELETE /api/tenants/{tenant_id}/webhooks/{id}
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
- `tenant.updated` — Tenant configuration changed

---

## Call Operations

### Originate Call

```http
POST /api/tenants/{tenant_id}/calls/originate
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "extension": "1001",
  "destination": "1002"
}
```

### Call Status

```http
GET /api/tenants/{tenant_id}/calls/status
Authorization: Bearer YOUR_TOKEN
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

Query parameters: `tenant_id`, `role`

**Response** `200`:
```json
{
  "data": [
    { "id": 1, "name": "John Doe", "email": "john@example.com", "role": "user", "tenant_id": "uuid" }
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
  "tenant_id": "tenant-uuid"
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
GET /api/tenants/{tenant}/recordings
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
GET /api/tenants/{tenant}/recordings/{id}
Authorization: Bearer {token}
```

### Download Recording

```http
GET /api/tenants/{tenant}/recordings/{id}/download
Authorization: Bearer {token}
```

Returns the recording file as a download.

### Delete Recording

```http
DELETE /api/tenants/{tenant}/recordings/{id}
Authorization: Bearer {token}
```

---

## Audit Logs

Read-only API for querying audit trail entries. All domain model changes (create, update, delete) are automatically logged.

### List Audit Logs

```http
GET /api/tenants/{tenant_id}/audit-logs
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
GET /api/tenants/{tenant_id}/audit-logs/{id}
Authorization: Bearer YOUR_TOKEN
```

**Response** `200`:
```json
{
  "data": {
    "id": "uuid",
    "tenant_id": "uuid",
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
| `403` | Forbidden — insufficient permissions or wrong tenant |
| `404` | Not found |
| `422` | Validation error |
| `429` | Rate limit exceeded |
| `500` | Server error |
