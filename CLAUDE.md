# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Local reference repositories

- Use `/root/projects/NIZAM/reference/fspbx` as local reference clone for `https://github.com/nemerald-voip/fspbx.git`.
- Use `/root/projects/NIZAM/reference/fusionpbx` as local reference clone for `https://github.com/fusionpbx/fusionpbx.git`.
- Reference repos are for comparison and research. Do not modify them unless explicitly asked.

## Runtime and command rules

- Run repo commands in Docker containers, not host machine.
- Use root Compose file: `docker compose -f docker-compose.yml ...`
- Main services:
  - `app` — Laravel/PHP/composer/artisan runtime
  - `frontend` — Vite/React runtime
  - `queue`, `scheduler`, `xml-cdr-watcher` — async/background workers
  - `nginx`, `postgres`, `redis`, `freeswitch` — infra/telephony stack
- `docker-compose.yml` includes `docker-compose.app.yml` plus telephony compose, so use root file unless you specifically need lower-level app-only inspection.

## Common commands

### Start stack

```bash
docker compose -f docker-compose.yml up -d
```

### Backend (Laravel / PHP)

```bash
# artisan

docker compose -f docker-compose.yml exec -T app php artisan about
docker compose -f docker-compose.yml exec -T app php artisan route:list

# tests

docker compose -f docker-compose.yml exec -T app php artisan test
docker compose -f docker-compose.yml exec -T app php artisan test tests/Feature/Api/DidApiTest.php
docker compose -f docker-compose.yml exec -T app php artisan test --filter=DeviceProfileApiTest

# formatting / lint

docker compose -f docker-compose.yml exec -T app ./vendor/bin/pint
docker compose -f docker-compose.yml exec -T app php -l app/Services/Call/OutboundOriginateService.php

# composer

docker compose -f docker-compose.yml exec -T app composer install
docker compose -f docker-compose.yml exec -T app composer dump-autoload

# migrations / seeders

docker compose -f docker-compose.yml exec -T app php artisan migrate
docker compose -f docker-compose.yml exec -T app php artisan db:seed
```

### Frontend (React / Vite)

```bash
# dev/build

docker compose -f docker-compose.yml exec -T frontend npm run dev
docker compose -f docker-compose.yml exec -T frontend npm run build
```

### Useful telephony / platform commands

```bash
docker compose -f docker-compose.yml exec -T app php artisan freeswitch:listen
docker compose -f docker-compose.yml exec -T app php artisan nizam:gateway-status
docker compose -f docker-compose.yml exec -T app php artisan nizam:sync-permissions
```

## Architecture overview

### Big picture

NIZAM is API-first telephony control plane:

- FreeSWITCH handles SIP/media/runtime execution.
- Laravel backend is source of truth for business state, routing, provisioning, permissions, and call orchestration.
- React frontend is admin client for backend API; it does not own domain logic.
- Dynamic telephony config is compiled/generated from DB state rather than edited manually.

README mental model is accurate: business state lives in Laravel; FreeSWITCH stays as stateless media core where possible.

### Backend structure

- `backend/app/Http/Controllers/Api` and `Api/Admin` expose JSON API.
- `backend/app/Http/Requests` holds validation/authorization rules for write paths.
- `backend/app/Http/Resources` shape API responses; frontend usually follows these closely.
- `backend/app/Models` holds Eloquent domain models.
- `backend/app/Services/*` contains most real domain behavior. Important clusters:
  - `Services/Call` — originate, delivery target resolution, caller-ID logic, live call behavior
  - `Services/Routing` — PBX destination/routing behavior
  - `Services/Flow/*` — call flow editor/compiler/runtime node support
  - `Services/Team` — team/ring behavior
  - `Services/Cdr`, `Interaction`, `Presence`, `Media`, `Push` — event/logging/support systems
- `backend/app/Observers`, `Events`, `Listeners`, `Jobs` wire side effects, projection, audit, async processing.
- `backend/modules/*` contains PBX feature modules (`PbxRouting`, `PbxContactCenter`, `PbxProvisioning`, etc.). Composer autoloads them directly.
- `backend/tests/Feature` covers API/integration behavior; `backend/tests/Unit` covers services, policies, listeners, observers, and module behavior.

### Frontend structure

- `frontend/src/pages/admin` is main admin surface; most CRUD work lands here.
- `frontend/src/components/ui` is shared UI primitive layer.
- `frontend/src/components/scaffolds` contains page shells like `PageHeader` and delete dialogs.
- `frontend/src/context` holds auth and active organization context.
- `frontend/src/lib/api.ts` and related hooks are API access layer.
- `frontend/src/types/models.ts` mirrors backend API resource shapes with Zod schemas/types. When backend resources change, this file usually must change too.
- `frontend/src/layouts/SuperadminLayout.tsx` owns admin navigation structure.
- `frontend/src/app.tsx` wires routes.

### Multi-tenant model

- Organization context matters almost everywhere.
- Backend API is mostly organization-scoped under `/api/v1/organizations/{organization}/...`.
- Superadmin/global admin surfaces exist, but most PBX resources are active-organization scoped.
- Frontend route availability and nav items depend on active organization and user role.

### Telephony and routing model

- DIDs/phone numbers map inbound traffic to destinations.
- Call flows, routing services, ring groups/teams, bridges, IVRs, time conditions, and extensions all feed telephony behavior.
- FreeSWITCH runtime config is generated/compiled from Laravel state; changes often require tracing model -> service -> compiler/projection path, not only controller/model edits.
- Event-driven pieces matter: queue workers, scheduler, ESL listener, gateway polling, webhook dispatch, XML/CDR watcher.

### Practical coding guidance for this repo

- For backend changes, inspect controller + request + resource + model + service together; domain behavior rarely lives in only one layer.
- For frontend CRUD changes, inspect page component + `types/models.ts` + corresponding backend resource/request/controller together.
- For telephony bugs, check whether issue is:
  1. stored business state,
  2. API/resource serialization,
  3. service-layer resolution/compilation,
  4. worker/listener/runtime behavior in Docker services.
- Prefer targeted test runs first (`php artisan test <file>` or `--filter`) because backend suite is large.
- Use Docker verification commands in final checks; do not rely on host-installed PHP/Node tooling.
