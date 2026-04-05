# Tech

## Stack
- PHP 8.2+
- Laravel 12
- PostgreSQL
- Redis
- FreeSWITCH
- Laravel Sanctum for auth
- nwidart/laravel-modules for modular features
- Spatie Media Library for media handling
- Vite + React + TypeScript frontend
- Tailwind CSS 4, React Query, React Hook Form, Zod, Radix UI
- PHPUnit 11 for tests
- Laravel Pint for PHP formatting
- Docker Compose + Makefile for local/dev ops

## Build and test
Prefer existing project commands before inventing new ones.

Primary commands:
- `make setup` — bootstrap containers and run migrations
- `make up` / `make down` — start or stop the stack
- `make logs` / `make logs-app` — inspect runtime logs
- `make test` — run test suite
- `make lint` — run code quality checks
- `make fix` — apply automated fixes
- `make openapi-validate` — validate OpenAPI docs
- `composer test` or `php artisan test` — PHP test execution
- `npm run build` — frontend production build
- `npm run dev` — frontend dev server

## Working rules for assistants
- Prefer Laravel conventions: Form Requests, Policies, Services, Jobs, Resources, Eloquent relationships
- Keep tenant-aware behavior explicit; do not bypass tenant scoping casually
- Reuse existing service classes and observers before introducing new abstractions
- Prefer tests in `tests/Feature` for HTTP and integration behavior, and `tests/Unit` for service/domain logic
- Use existing module infrastructure for modular telecom capabilities instead of scattering logic across unrelated areas
- Treat FreeSWITCH integration as infrastructure boundary code: configuration, event ingestion, dialplan compilation, and XML generation should stay deterministic and testable
