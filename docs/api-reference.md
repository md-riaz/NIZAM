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

**Destination types:** `extension`, `ring_group`, `ivr`, `voicemail`, `time_condition`

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
- `voicemail.received` — New voicemail
- `registration.registered` — SIP device registered
- `registration.unregistered` — SIP device unregistered

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
