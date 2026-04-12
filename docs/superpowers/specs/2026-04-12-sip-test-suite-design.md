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

#### `register.php` (Phase 1)
- Perform unauthenticated REGISTER.
- Parse `401 Unauthorized` challenge.
- Perform authenticated REGISTER.
- Print pass/fail transcript.

#### `invite.php` (Phase 2)
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

## Phase 1 Results
- `REGISTER` authenticated handshake is working.

## Phase 2 Usage Notes

### In-network INVITE pathfinder
```bash
# Test self-call (expects immediate ringing from direct bridge)
docker exec app php /var/www/html/scripts/sip/invite.php 1001 1001

# Test internal non-self call (expects trying/ringing from orchestration)
docker exec app php /var/www/html/scripts/sip/invite.php 1001 1002
```

### Host-side INVITE pathfinder
```bash
# Test from host machine
cd backend && php scripts/sip/invite.php 1001 1001 --host
```

### Expected result (Self-Call)
- Response Ladder:
  - `SIP/2.0 100 Trying`
  - `SIP/2.0 407 Proxy Authentication Required` (Initial challenge)
  - `SIP/2.0 100 Trying` (Authenticated retry)
  - `SIP/2.0 180 Ringing` (Success: extension found and bridged)
- Final line: `INVITE verification passed`
