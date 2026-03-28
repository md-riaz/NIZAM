# NIZAM API Reference (Current)

**Generated:** 2026-03-28  
**Version:** 1.0.1  
**Framework:** Laravel 12 + FreeSWITCH

> All API endpoints return JSON responses and require authentication via Laravel Sanctum bearer tokens unless noted otherwise.

---

## Table of Contents

1. [Authentication](#authentication)
2. [Health Check](#health-check)
3. [Tenants](#tenants)
4. [Extensions](#extensions)
5. [DIDs](#dids)
6. [Ring Groups](#ring-groups)
7. [IVRs](#ivrs)
8. [Time Conditions](#time-conditions)
9. [Call Routing Policies](#call-routing-policies)
10. [Call Flows](#call-flows)
11. [Call Sessions](#call-sessions)
12. [Agents](#agents)
13. [Queues](#queues)
14. [Queue Metrics & Wallboard](#queue-metrics--wallboard)
15. [Recordings](#recordings)
16. [CDRs](#cdrs)
17. [Webhooks](#webhooks)
18. [Call Events](#call-events)
19. [Call Control](#call-control)
20. [Device Profiles](#device-profiles)
21. [Gateways](#gateways)
22. [Codec Metrics](#codec-metrics)
23. [Holiday Calendars](#holiday-calendars)
24. [Schedules](#schedules)
25. [Teams](#teams)
26. [User Management](#user-management)
27. [Audit Logs](#audit-logs)
28. [Usage Metering](#usage-metering)
29. [Admin Dashboard](#admin-dashboard)
30. [Rate Limiting](#rate-limiting)
31. [SSL Management](#ssl-management)
32. [WebRTC Configuration](#webrtc-configuration)

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

**Response** `200`:
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

### Get Tenant

```http
GET /api/tenants/{id}
Authorization: Bearer YOUR_TOKEN
```

### Update Tenant

```http
PUT /api/tenants/{id}
Authorization: Bearer YOUR_TOKEN (admin)
Content-Type: application/json

{
  "name": "Updated Name",
  "domain": "updated.example.com",
  "is_active": true
}
```

### Delete Tenant

```http
DELETE /api/tenants/{id}
Authorization: Bearer YOUR_TOKEN (admin)
```

### Get Tenant Settings

```http
GET /api/tenants/{id}/settings
Authorization: Bearer YOUR_TOKEN
```

**Response** `200`:
```json
{
  "data": { "timezone": "Asia/Dhaka", "recording_format": "wav" }
}
```

### Update Tenant Settings

```http
PUT /api/tenants/{id}/settings
Authorization: Bearer YOUR_TOKEN (admin)
Content-Type: application/json

{
  "settings": { "recording_format": "mp3", "max_ring_time": 30 }
}
```

Settings are **merged** — existing keys are preserved unless explicitly overwritten.

### Provision Tenant (Zero-Touch)

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

Only `name` is required. Domain and slug are auto-generated if not provided.

### Get Tenant Stats

```http
GET /api/tenants/{id}/stats
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
    "quotas": {
      "max_extensions": 50,
      "max_concurrent_calls": 20,
      "max_dids": 10,
      "max_ring_groups": 5
    }
  }
}
```

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

**Note:** Both `password` and `voicemail_pin` are stored as plaintext and included in API responses.

### Get Extension

```http
GET /api/tenants/{tenant_id}/extensions/{id}
Authorization: Bearer YOUR_TOKEN
```

### Update Extension

```http
PUT /api/tenants/{tenant_id}/extensions/{id}
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "directory_first_name": "Updated",
  "is_active": false
}
```

### Delete Extension

```http
DELETE /api/tenants/{tenant_id}/extensions/{id}
Authorization: Bearer YOUR_TOKEN
```

### WebRTC Configuration

Get the complete connection suite for WebRTC clients (SIP.js, etc.):

```http
GET /api/tenants/{tenant_id}/extensions/{id}/webrtc-config
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

**Destination types:** `extension`, `ring_group`, `ivr`, `voicemail`, `time_condition`, `call_routing_policy`, `flow`, `bridge`

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

## IVRs

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

### Evaluate Policy

```http
POST /api/tenants/{tenant_id}/call-routing-policies/{policy_id}/evaluate
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
  "context": { "tenant_id": "uuid", "did": "+15551234567", "caller_id": "5559876543" },
  "decision": { "decision": "allow" }
}
```

---

## Call Flows

Composable call flow graphs. Each flow is a sequence of nodes that are compiled into FreeSWITCH dialplan actions.

```http
GET    /api/tenants/{tenant_id}/flows
POST   /api/tenants/{tenant_id}/flows
GET    /api/tenants/{tenant_id}/flows/{id}
PUT    /api/tenants/{tenant_id}/flows/{id}
DELETE /api/tenants/{tenant_id}/flows/{id}
POST   /api/tenants/{tenant_id}/flows/{id}/publish
```

### Create Flow

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

### Publish Flow

```http
POST /api/tenants/{tenant_id}/flows/{id}/publish
Authorization: Bearer YOUR_TOKEN
```

---

## Call Sessions

View active and completed call sessions with full trace events.

```http
GET    /api/tenants/{tenant_id}/calls
GET    /api/tenants/{tenant_id}/calls/{callSession}
GET    /api/tenants/{tenant_id}/calls/{callSession}/analyze
```

### List Call Sessions

```http
GET /api/tenants/{tenant_id}/calls
Authorization: Bearer YOUR_TOKEN
```

### Get Call Session

```http
GET /api/tenants/{tenant_id}/calls/{callSession}
Authorization: Bearer YOUR_TOKEN
```

**Response** `200`:
```json
{
  "data": {
    "id": "uuid",
    "tenant_id": "uuid",
    "call_uuid": "abc-123",
    "caller_id_number": "+15551234567",
    "destination_number": "1001",
    "direction": "inbound",
    "start_stamp": "2026-01-15T10:30:00Z",
    "answer_stamp": "2026-01-15T10:30:05Z",
    "end_stamp": "2026-01-15T10:30:35Z",
    "duration": 35,
    "billsec": 30,
    "hangup_cause": "NORMAL_CLEARING",
    "trace_events": [...]
  }
}
```

### Analyze Call Session

```http
GET /api/tenants/{tenant_id}/calls/{callSession}/analyze
Authorization: Bearer YOUR_TOKEN
```

Returns computed replay timeline and node metrics for the call session.

---

## Agents

Contact center agent management.

```http
GET    /api/tenants/{tenant_id}/agents
POST   /api/tenants/{tenant_id}/agents
GET    /api/tenants/{tenant_id}/agents/{id}
PUT    /api/tenants/{tenant_id}/agents/{id}
DELETE /api/tenants/{tenant_id}/agents/{id}
POST   /api/tenants/{tenant_id}/agents/{id}/state
```

### Change Agent State

```http
POST /api/tenants/{tenant_id}/agents/{id}/state
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "state": "ready",
  "note": "Available for calls"
}
```

**States:** `ready`, `busy`, `break`, `not_ready`, `logged_out`

---

## Queues

Contact center queue management.

```http
GET    /api/tenants/{tenant_id}/queues
POST   /api/tenants/{tenant_id}/queues
GET    /api/tenants/{tenant_id}/queues/{id}
PUT    /api/tenants/{tenant_id}/queues/{id}
DELETE /api/tenants/{tenant_id}/queues/{id}
GET    /api/tenants/{tenant_id}/queues/{id}/members
POST   /api/tenants/{tenant_id}/queues/{id}/members
DELETE /api/tenants/{tenant_id}/queues/{id}/members/{agent}
```

### Add Queue Member

```http
POST /api/tenants/{tenant_id}/queues/{id}/members
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "agent_id": "agent-uuid",
  "weight": 1,
  "paused": false
}
```

### Remove Queue Member

```http
DELETE /api/tenants/{tenant_id}/queues/{id}/members/{agent}
Authorization: Bearer YOUR_TOKEN
```

---

## Queue Metrics & Wallboard

Real-time queue performance monitoring.

```http
GET    /api/tenants/{tenant_id}/queues/{id}/metrics/realtime
POST   /api/tenants/{tenant_id}/queues/{id}/metrics/aggregate
GET    /api/tenants/{tenant_id}/queues/{id}/metrics/history
GET    /api/tenants/{tenant_id}/wallboard
GET    /api/tenants/{tenant_id}/agent-states
```

### Get Realtime Queue Metrics

```http
GET /api/tenants/{tenant_id}/queues/{id}/metrics/realtime
Authorization: Bearer YOUR_TOKEN
```

**Response** `200`:
```json
{
  "data": {
    "queue_id": "uuid",
    "current_size": 5,
    "current_wait_time": 30,
    "agents_ready": 3,
    "agents_busy": 2,
    "calls_answered": 150,
    "calls_abandoned": 12,
    "service_level": 95.5
  }
}
```

### Get Wallboard

```http
GET /api/tenants/{tenant_id}/wallboard
Authorization: Bearer YOUR_TOKEN
```

Returns aggregated metrics for all queues in the tenant.

---

## Recordings

```http
GET    /api/tenants/{tenant_id}/recordings
GET    /api/tenants/{tenant_id}/recordings/{id}
GET    /api/tenants/{tenant_id}/recordings/{id}/download
DELETE /api/tenants/{tenant_id}/recordings/{id}
```

### List Recordings

```http
GET /api/tenants/{tenant_id}/recordings
Authorization: Bearer YOUR_TOKEN
```

**Query Parameters:**
| Parameter | Description |
|-----------|-------------|
| `call_uuid` | Filter by call UUID |
| `caller_id_number` | Filter by caller ID number |
| `destination_number` | Filter by destination number |
| `date_from` | Filter recordings after this datetime |
| `date_to` | Filter recordings before this datetime |

### Download Recording

```http
GET /api/tenants/{tenant_id}/recordings/{id}/download
Authorization: Bearer YOUR_TOKEN
```

Returns the recording file as a download.

---

## CDRs (Call Detail Records)

Read-only. Created automatically when calls end.

```http
GET /api/tenants/{tenant_id}/cdrs
GET /api/tenants/{tenant_id}/cdrs/{id}
GET /api/tenants/{tenant_id}/cdrs/export
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

```http
GET /api/tenants/{tenant_id}/cdrs/export
Authorization: Bearer YOUR_TOKEN
```

Streamed CSV with headers: `uuid`, `caller_id_name`, `caller_id_number`, `destination_number`, `direction`, `start_stamp`, `answer_stamp`, `end_stamp`, `duration`, `billsec`, `hangup_cause`.

---

## Webhooks

```http
GET    /api/tenants/{tenant_id}/webhooks
POST   /api/tenants/{tenant_id}/webhooks
GET    /api/tenants/{tenant_id}/webhooks/{id}
PUT    /api/tenants/{tenant_id}/webhooks/{id}
DELETE /api/tenants/{tenant_id}/webhooks/{id}
GET    /api/tenants/{tenant_id}/webhooks/{id}/delivery-attempts
GET    /api/tenants/{tenant_id}/webhooks/{id}/delivery-stats
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

### Get Delivery Attempts

```http
GET /api/tenants/{tenant_id}/webhooks/{id}/delivery-attempts
Authorization: Bearer YOUR_TOKEN
```

### Get Delivery Stats

```http
GET /api/tenants/{tenant_id}/webhooks/{id}/delivery-stats
Authorization: Bearer YOUR_TOKEN
```

---

## Call Events

```http
GET    /api/tenants/{tenant_id}/call-events
GET    /api/tenants/{tenant_id}/call-events/stream
GET    /api/tenants/{tenant_id}/call-events/{callUuid}/trace
GET    /api/tenants/{tenant_id}/call-events/replay/{eventId}
POST   /api/tenants/{tenant_id}/call-events/redispatch/{eventId}
```

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

```http
GET /api/tenants/{tenant_id}/call-events/{callUuid}/trace
Authorization: Bearer YOUR_TOKEN
```

### Event Replay

```http
GET /api/tenants/{tenant_id}/call-events/replay/{eventId}
Authorization: Bearer YOUR_TOKEN
```

### Real-Time Event Stream (SSE)

```http
GET /api/tenants/{tenant_id}/call-events/stream
Authorization: Bearer YOUR_TOKEN
```

**Query Parameters:**
| Parameter | Description |
|-----------|-------------|
| `call_uuid` | Filter stream to a specific call UUID |
| `event_types` | Comma-separated list of event types to filter |

### Re-dispatch Event

```http
POST /api/tenants/{tenant_id}/call-events/redispatch/{eventId}
Authorization: Bearer YOUR_TOKEN
```

---

## Call Control

```http
POST /api/tenants/{tenant_id}/calls/originate
GET  /api/tenants/{tenant_id}/calls/status
POST /api/tenants/{tenant_id}/calls/hangup
POST /api/tenants/{tenant_id}/calls/transfer
POST /api/tenants/{tenant_id}/calls/recording
POST /api/tenants/{tenant_id}/calls/hold
```

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

### Hangup Call

```http
POST /api/tenants/{tenant_id}/calls/hangup
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "uuid": "call-uuid-here",
  "cause": "NORMAL_CLEARING"
}
```

### Transfer Call

```http
POST /api/tenants/{tenant_id}/calls/transfer
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "uuid": "call-uuid-here",
  "destination": "1002",
  "leg": "aleg"
}
```

### Toggle Recording

```http
POST /api/tenants/{tenant_id}/calls/recording
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "uuid": "call-uuid-here",
  "action": "start"
}
```

### Hold / Unhold

```http
POST /api/tenants/{tenant_id}/calls/hold
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "uuid": "call-uuid-here",
  "action": "hold"
}
```

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

## Gateways
## Bridges

Manage reusable outbound bridge targets. Bridge objects let routing surfaces like policies, time conditions, IVRs, and DIDs send calls to a gateway-backed PSTN destination or a raw FreeSWITCH dial string.

**Bridge types:** `gateway`, `raw`

**Gateway bridge example:**

```json
{
  "name": "PSTN Out",
  "bridge_type": "gateway",
  "gateway_id": "<gateway-uuid>",
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


```http
GET    /api/tenants/{tenant_id}/gateways
POST   /api/tenants/{tenant_id}/gateways
GET    /api/tenants/{tenant_id}/gateways/{id}
PUT    /api/tenants/{tenant_id}/gateways/{id}
DELETE /api/tenants/{tenant_id}/gateways/{id}
```

---

## Codec Metrics

```http
GET /api/tenants/{tenant_id}/codec-metrics
Authorization: Bearer YOUR_TOKEN
```

---

## Holiday Calendars

```http
GET    /api/tenants/{tenant_id}/holiday-calendars
POST   /api/tenants/{tenant_id}/holiday-calendars
GET    /api/tenants/{tenant_id}/holiday-calendars/{id}
PUT    /api/tenants/{tenant_id}/holiday-calendars/{id}
DELETE /api/tenants/{tenant_id}/holiday-calendars/{id}
```

---

## Schedules

```http
GET    /api/tenants/{tenant_id}/schedules
POST   /api/tenants/{tenant_id}/schedules
GET    /api/tenants/{tenant_id}/schedules/{id}
PUT    /api/tenants/{tenant_id}/schedules/{id}
DELETE /api/tenants/{tenant_id}/schedules/{id}
```

---

## Teams

```http
GET    /api/tenants/{tenant_id}/teams
POST   /api/tenants/{tenant_id}/teams
GET    /api/tenants/{tenant_id}/teams/{id}
PUT    /api/tenants/{tenant_id}/teams/{id}
DELETE /api/tenants/{tenant_id}/teams/{id}
```

---

## User Management (Admin Only)

### List Users

```http
GET /api/users
Authorization: Bearer {token}
```

Query parameters: `tenant_id`, `role`

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

---

## Audit Logs

Read-only API for querying audit trail entries.

```http
GET /api/tenants/{tenant_id}/audit-logs
GET /api/tenants/{tenant_id}/audit-logs/{id}
```

### List Audit Logs

```http
GET /api/tenants/{tenant_id}/audit-logs
Authorization: Bearer YOUR_TOKEN
```

**Query Parameters:**
| Parameter | Description |
|-----------|-------------|
| `action` | Filter by action type (`created`, `updated`, `deleted`) |
| `auditable_type` | Filter by model type |
| `user_id` | Filter by user who performed the action |
| `from` | Filter logs after this datetime |
| `to` | Filter logs before this datetime |

---

## Usage Metering

### Get Usage Summary

```http
GET /api/tenants/{tenant_id}/usage/summary?from=2026-02-01&to=2026-02-28
Authorization: Bearer YOUR_TOKEN
```

### Collect Usage Snapshot

```http
POST /api/tenants/{tenant_id}/usage/collect
Authorization: Bearer YOUR_TOKEN
```

### Reconcile Call Minutes

```http
GET /api/tenants/{tenant_id}/usage/reconcile?from=2026-02-01&to=2026-02-28
Authorization: Bearer YOUR_TOKEN
```

---

## Admin Dashboard

System-wide observability endpoint (admin-only):

```http
GET /api/admin/dashboard
Authorization: Bearer YOUR_TOKEN
```

Returns total tenants by status, per-tenant resource counts, and aggregate system metrics.

---

## SSL Management

Manage system-wide SSL certificates via Certbot / Let's Encrypt (admin-only):

### Get SSL Settings
```http
GET /api/admin/ssl
Authorization: Bearer YOUR_TOKEN
```

### Update SSL Settings
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

### Request Certificate
```http
POST /api/admin/ssl/request
Authorization: Bearer YOUR_TOKEN
```
Triggers a manual Let's Encrypt certificate request/renewal.

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

---

## API Base URL

- **Development:** `http://localhost:8091/api/v1`
- **Production:** `https://api.nizam.example.com/api/v1`

---

*This documentation was auto-generated from the current codebase on 2026-03-28.*
