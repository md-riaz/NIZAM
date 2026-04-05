# Structure

## Top-level layout
- `app/` — core Laravel application code
- `modules/` — pluggable PBX feature modules with their own routes and migrations
- `routes/` — core route entrypoints
- `database/` — migrations, factories, seeders
- `resources/` — frontend React/TypeScript app, CSS, Blade shell
- `tests/` — unit and feature tests
- `.kiro/steering/` — AI steering docs

## Important backend areas
- `app/Http/Controllers/Api/` — API controllers; most product behavior is exposed here
- `app/Http/Requests/` — validation layer for incoming API requests
- `app/Http/Resources/` — response shaping and API serialization
- `app/Models/` — Eloquent models for telecom, tenant, analytics, and system entities
- `app/Services/` — business logic and orchestration; check here before adding logic to controllers
- `app/Policies/` — authorization rules, especially tenant/resource access
- `app/Observers/` — model side effects and manifest rebuild triggers
- `app/Jobs/` and `app/Events/` — async and event-driven behavior
- `app/Domain/Flow/` — flow runtime and compiler-related domain code
- `app/Modules/` — module contracts and registry used by feature modules

## Modules
Each module under `modules/*` typically contains:
- `app/` — module classes/providers
- `routes/api.php` — module API routes
- `database/migrations/` — module-owned schema changes
- `module.json` — module metadata

Prefer adding isolated feature work to the appropriate module when the capability is modular by nature.

## Frontend
- `resources/js/app.tsx` — frontend entrypoint
- `resources/js/pages/` — page-level screens
- `resources/js/components/` — shared UI
- `resources/js/lib/` — API client, query client, utilities
- `resources/js/context/` — auth and tenant context
- `resources/views/app.blade.php` — Blade host for the SPA

## Testing layout
- `tests/Feature/Api/` — API behavior and end-to-end Laravel request flows
- `tests/Feature/Web/` — web/UI integration
- `tests/Unit/Services/` — service-layer behavior
- `tests/Unit/Models/`, `Policies/`, `Observers/`, `Modules/` — focused backend coverage

## Change guidance
- Put request validation in Form Requests, not controllers
- Put authorization in Policies or middleware, not inline conditionals
- Put business logic in Services/Domain code, not controllers or models unless it is true model behavior
- Put tenant-sensitive side effects in observers/jobs/services where they are already expected
- Follow existing naming and placement patterns before creating new directories or architectural styles
