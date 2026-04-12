# SIP Test Suite Design

## Goal
Add a local-development-only SIP test toolkit using `rtckit/php-sip` to verify authenticated REGISTER and basic INVITE flows against the running FreeSWITCH/Laravel stack.

## Recommended Approach: Lightweight CLI Scripts

Use `rtckit/php-sip` as a parsing/rendering library only. Use plain PHP sockets for UDP networking. Keep the SIP tests out of PHPUnit and the core Laravel runtime.

### Why this approach
- Fits the requirement for one-shot scripts.
- Works both from inside Docker and from the host machine.
- Avoids turning SIP timing/state problems into flaky PHPUnit tests.
- Keeps the Laravel app focused on XML-CURL generation and orchestration, not on acting as a SIP client.

## Architecture

### Dependency
- Add `rtckit/php-sip` to `backend/composer.json` under `require-dev`.

### File Structure
```text
backend/
  scripts/
    sip/
      bootstrap.php
      common.php
      register.php
      invite.php
```

### File Responsibilities

#### `bootstrap.php`
- Boot Laravel application/container.
- Load extension, tenant, and SIP profile settings from the database.
- Expose helpers for resolving credentials and SIP target defaults.

#### `common.php`
- UDP socket helpers.
- SIP digest auth helpers.
- Message rendering/parsing glue using `rtckit/php-sip`.
- Target resolution helpers:
  - host mode (`127.0.0.1:25060`)
  - in-network mode (`freeswitch:5060` or container IP)

#### `register.php`
- Perform unauthenticated REGISTER.
- Parse `401 Unauthorized` challenge.
- Perform authenticated REGISTER.
- Print pass/fail transcript.

#### `invite.php`
- Perform basic INVITE for:
  - self-call (`1001 -> 1001`)
  - internal call (`1001 -> 1002`)
- Print response ladder (`100 Trying`, `180 Ringing`, `200 OK`, failures).

## Usage

### In-network (recommended for reliable proof)
```bash
docker exec app php scripts/sip/register.php 1001
docker exec app php scripts/sip/invite.php 1001 1001
docker exec app php scripts/sip/invite.php 1001 1002
```

### Host-side black-box
```bash
php scripts/sip/register.php 1001 --host 127.0.0.1 --port 25060
php scripts/sip/invite.php 1001 1001 --host 127.0.0.1 --port 25060
```

## Scope

### Included in first version
- Authenticated REGISTER verification.
- Basic INVITE verification.
- Dual target modes (host + in-network).
- Clear text output for debugging.

### Excluded from first version
- RTP/media validation.
- REFER/transfer.
- SUBSCRIBE/NOTIFY.
- Load testing.
- CI/CD integration.
- PHPUnit integration.

## Test Behavior Expectations

### Register script
Expected happy path:
1. Send REGISTER.
2. Receive `401 Unauthorized`.
3. Send authenticated REGISTER.
4. Receive `200 OK`.

### Invite script
Expected happy path:
- Self-call: route into direct `user/` bridge path.
- Internal non-self call: route into orchestrated or internal delivery path depending on system behavior.

## Error Handling
- Distinguish clearly between:
  - no SIP response
  - auth failure
  - lookup failure
  - unexpected response code
- Print raw first-line SIP responses for quick debugging.
- Optional verbose mode can print full SIP messages.

## Why not PHPUnit first
- SIP flows are socket/timing sensitive.
- One-shot scripts are easier to reason about and debug.
- Local developers need direct protocol visibility more than assertion-heavy framework tests at this stage.

## Future Extension Path
Once the scripts are stable, they can later be wrapped by:
- an Artisan command layer, or
- a CI harness that runs the scripts against a Docker Compose telephony stack.

## Recommendation
Proceed with:
1. `rtckit/php-sip` as `require-dev`
2. `scripts/sip/register.php`
3. `scripts/sip/invite.php`
4. shared helpers in `common.php`
5. local-only usage first

## Phase 1 Usage Notes

### In-network verification
```bash
docker exec app php /var/www/html/scripts/sip/register.php 1001
```

### Host-side verification
```bash
cd backend && php scripts/sip/register.php 1001 --host
```

### Expected result
- First response: `401 Unauthorized`
- Second response: `200 OK`
- Final line: `REGISTER verification passed`
