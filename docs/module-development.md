# Module Development Guide

NIZAM's module framework allows extending the platform with custom functionality without modifying core code.

---

## Module Interface

Every module must implement the `NizamModule` interface:

```php
<?php

namespace App\Modules\Contracts;

interface NizamModule
{
    public function name(): string;
    public function description(): string;
    public function version(): string;
    public function register(): void;
    public function boot(): void;
    public function dialplanContributions(string $tenantDomain, string $destination): array;
    public function subscribedEvents(): array;
    public function handleEvent(string $eventType, array $data): void;
    public function permissions(): array;
    public function migrationsPath(): ?string;
}
```

---

## Creating a Module

### 1. Create the Module Class

```php
<?php

namespace App\Modules;

use App\Modules\Contracts\NizamModule;

class CallRecordingModule implements NizamModule
{
    public function name(): string
    {
        return 'call-recording';
    }

    public function description(): string
    {
        return 'Automatic call recording with storage management';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function register(): void
    {
        // Bind services, register config, etc.
    }

    public function boot(): void
    {
        // Run after all modules are registered
    }

    public function dialplanContributions(string $tenantDomain, string $destination): array
    {
        // Return XML fragments keyed by priority (lower = earlier)
        return [
            5 => '<action application="set" data="record_file_name=/recordings/${uuid}.wav"/>',
            6 => '<action application="record_session" data="${record_file_name}"/>',
        ];
    }

    public function subscribedEvents(): array
    {
        return ['call.hangup'];
    }

    public function handleEvent(string $eventType, array $data): void
    {
        if ($eventType === 'call.hangup') {
            // Process recording after call ends
            // e.g., move file, update database, notify
        }
    }

    public function permissions(): array
    {
        return [
            'recordings.view',
            'recordings.download',
            'recordings.delete',
        ];
    }

    public function migrationsPath(): ?string
    {
        return __DIR__.'/migrations';
    }
}
```

### 2. Register the Module

In a service provider (e.g., `AppServiceProvider` or a dedicated provider):

```php
use App\Modules\ModuleRegistry;
use App\Modules\CallRecordingModule;

public function boot(): void
{
    $registry = app(ModuleRegistry::class);
    $registry->register(new CallRecordingModule());
}
```

---

## Module Hooks

### Dialplan Contributions

Modules can inject dialplan XML fragments at specific priorities:

```php
public function dialplanContributions(string $tenantDomain, string $destination): array
{
    // Priority determines order (lower = earlier in dialplan)
    return [
        10 => '<action application="set" data="my_var=value"/>',
    ];
}
```

The `ModuleRegistry::collectDialplanContributions()` method gathers and sorts all contributions by priority.

### Event Subscriptions

Modules declare which events they want to receive:

```php
public function subscribedEvents(): array
{
    return ['call.started', 'call.hangup', 'registration.registered'];
}
```

When an event occurs, `ModuleRegistry::dispatchEvent()` calls `handleEvent()` on all subscribing modules. Errors in one module don't affect others (error isolation).

### Permission Extensions

Modules can introduce new permissions:

```php
public function permissions(): array
{
    return ['fax.send', 'fax.receive', 'fax.view'];
}
```

Use `ModuleRegistry::collectPermissions()` to gather all module permissions for role/policy management.

### Migration Isolation

Modules can define their own database migrations in a separate directory:

```php
public function migrationsPath(): ?string
{
    return __DIR__.'/migrations';
}
```

Migrations are automatically loaded by `AppServiceProvider` when the module is registered. Each module's migrations run alongside core migrations but are kept in separate directories for clean organization:

```
app/Modules/
├── CallRecordingModule.php
└── migrations/
    ├── 2026_01_01_000001_create_recordings_table.php
    └── 2026_01_01_000002_add_recording_settings_table.php
```

Return `null` if the module has no migrations.

---

## Module Lifecycle

1. **Register** — `register()` called when module is added to registry. Use for service bindings.
2. **Boot** — `boot()` called after all modules are registered. Use for cross-module setup.
3. **Runtime** — Dialplan contributions and event handlers called as needed.

---

## Error Isolation

Module event handlers are wrapped in try/catch. If a module throws an exception during event handling, the error is logged and other modules continue processing:

```
[ERROR] Module event handler failed {"module":"call-recording","event":"call.hangup","error":"..."}
```

This ensures a buggy module cannot bring down the entire event pipeline.
