<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeModuleCommand extends Command
{
    protected $signature = 'make:nizam-module {name : The module name (e.g., CallRecording)}';

    protected $description = 'Generate a NIZAM module skeleton with all required hooks';

    public function handle(): int
    {
        $name = $this->argument('name');
        $studlyName = Str::studly($name);
        $kebabName = Str::kebab($name);
        $snakeName = Str::snake($name);

        $basePath = base_path("modules/{$studlyName}");

        if (is_dir($basePath)) {
            $this->error("Module directory already exists: modules/{$studlyName}");

            return self::FAILURE;
        }

        // Create directory structure (nwidart convention)
        $directories = [
            $basePath.'/app',
            $basePath.'/app/Providers',
            $basePath.'/database/migrations',
            $basePath.'/config',
            $basePath.'/routes',
            $basePath.'/tests',
        ];

        foreach ($directories as $dir) {
            if (! is_dir($dir) && ! mkdir($dir, 0755, true)) {
                $this->error("Failed to create directory: {$dir}");

                return self::FAILURE;
            }
        }

        // Generate module class (NizamModule implementation for telecom hooks)
        file_put_contents(
            "{$basePath}/app/{$studlyName}Module.php",
            $this->generateModuleClass($studlyName, $kebabName, $snakeName)
        );

        // Generate ServiceProvider (nwidart convention)
        file_put_contents(
            "{$basePath}/app/Providers/{$studlyName}ServiceProvider.php",
            $this->generateServiceProvider($studlyName, $kebabName, $snakeName)
        );

        // Generate module.json (nwidart convention)
        file_put_contents(
            "{$basePath}/module.json",
            $this->generateModuleJson($studlyName, $kebabName)
        );

        // Generate config file
        file_put_contents(
            "{$basePath}/config/config.php",
            $this->generateConfig($studlyName)
        );

        // Generate routes file
        file_put_contents(
            "{$basePath}/routes/api.php",
            $this->generateRoutes($studlyName, $kebabName)
        );

        // Generate README
        file_put_contents(
            "{$basePath}/README.md",
            $this->generateReadme($studlyName, $kebabName)
        );

        // Generate composer.json
        file_put_contents(
            "{$basePath}/composer.json",
            $this->generateComposerJson($studlyName, $kebabName)
        );

        $this->info("Module skeleton created at: modules/{$studlyName}/");
        $this->newLine();
        $this->line('  <comment>Files created:</comment>');
        $this->line("    app/{$studlyName}Module.php           — NizamModule implementation (telecom hooks)");
        $this->line("    app/Providers/{$studlyName}ServiceProvider.php — Laravel service provider");
        $this->line('    module.json                           — nwidart module manifest');
        $this->line('    config/config.php                     — Module configuration');
        $this->line('    routes/api.php                        — Module API routes');
        $this->line('    database/migrations/                  — Module migrations directory');
        $this->line('    tests/                                — Module tests directory');
        $this->line('    composer.json                         — Package manifest');
        $this->line('    README.md                             — Module documentation');
        $this->newLine();
        $this->line('  <comment>Next steps:</comment>');
        $this->line("    1. Add to config/nizam.php 'modules' section:");
        $this->line("       '{$kebabName}' => [");
        $this->line("           'class' => \\Modules\\{$studlyName}\\{$studlyName}Module::class,");
        $this->line("           'enabled' => env('MODULE_".strtoupper(Str::snake($studlyName))."', true),");
        $this->line('       ],');
        $this->line('    2. Implement your dialplan contributions, event handlers, and permissions');
        $this->line('    3. Add migrations to database/migrations/');
        $this->line("    4. Enable the module: php artisan module:enable {$studlyName}");

        return self::SUCCESS;
    }

    protected function generateModuleClass(string $studlyName, string $kebabName, string $snakeName): string
    {
        return <<<PHP
<?php

namespace Modules\\{$studlyName};

use App\\Modules\\Contracts\\NizamModule;

class {$studlyName}Module implements NizamModule
{
    public function name(): string
    {
        return '{$kebabName}';
    }

    public function description(): string
    {
        return '{$studlyName} module for NIZAM';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function dependencies(): array
    {
        return [];
    }

    public function config(): array
    {
        return [];
    }

    public function register(): void
    {
        // Register bindings, merge config, etc.
    }

    public function boot(): void
    {
        // Post-registration boot logic
    }

    public function dialplanContributions(string \$tenantDomain, string \$destination): array
    {
        // Return XML fragments keyed by priority (lower = earlier in dialplan)
        // Example:
        // return [
        //     50 => '<action application="set" data="{$snakeName}_enabled=true"/>',
        // ];

        return [];
    }

    public function subscribedEvents(): array
    {
        // Return event types this module listens to
        // Example: return ['call.started', 'call.hangup'];

        return [];
    }

    public function handleEvent(string \$eventType, array \$data): void
    {
        // Handle events from subscribed types
    }

    public function permissions(): array
    {
        // Return permissions this module introduces
        // Example: return ['{$snakeName}.view', '{$snakeName}.manage'];

        return [];
    }

    public function migrationsPath(): ?string
    {
        return __DIR__ . '/../database/migrations';
    }

    public function routesFile(): ?string
    {
        return __DIR__ . '/../routes/api.php';
    }

    public function policyHooks(): array
    {
        return [];
    }
}

PHP;
    }

    protected function generateServiceProvider(string $studlyName, string $kebabName, string $snakeName): string
    {
        return <<<PHP
<?php

namespace Modules\\{$studlyName}\\Providers;

use Illuminate\\Support\\ServiceProvider;

class {$studlyName}ServiceProvider extends ServiceProvider
{
    protected string \$name = '{$studlyName}';

    public function boot(): void
    {
        \$this->loadMigrationsFrom(module_path(\$this->name, 'database/migrations'));
        \$this->loadRoutesFrom(module_path(\$this->name, 'routes/api.php'));
    }

    public function register(): void {}
}

PHP;
    }

    protected function generateModuleJson(string $studlyName, string $kebabName): string
    {
        return <<<JSON
{
    "name": "{$studlyName}",
    "alias": "{$kebabName}",
    "description": "{$studlyName} module for NIZAM",
    "keywords": [],
    "priority": 0,
    "providers": [
        "Modules\\\\{$studlyName}\\\\Providers\\\\{$studlyName}ServiceProvider"
    ],
    "files": []
}

JSON;
    }

    protected function generateConfig(string $studlyName): string
    {
        $envKey = strtoupper(Str::snake($studlyName));

        return <<<PHP
<?php

return [
    'enabled' => env('MODULE_{$envKey}_ENABLED', true),
];

PHP;
    }

    protected function generateRoutes(string $studlyName, string $kebabName): string
    {
        return <<<PHP
<?php

use Illuminate\\Support\\Facades\\Route;

/*
|--------------------------------------------------------------------------
| {$studlyName} Module API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('api')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::prefix('tenants/{tenant}')->middleware('tenant.access')->group(function () {
        // Add your module routes here
    });
});

PHP;
    }

    protected function generateReadme(string $studlyName, string $kebabName): string
    {
        return <<<MD
# {$studlyName} Module

A NIZAM module for {$studlyName} functionality.

## Installation

This module is managed by nwidart/laravel-modules.

Enable: `php artisan module:enable {$studlyName}`
Disable: `php artisan module:disable {$studlyName}`

## Configuration

Publish the config:

```bash
php artisan vendor:publish --tag={$kebabName}-config
```

## Hooks

- **Dialplan**: Contribute XML fragments to the compiled dialplan
- **Events**: Subscribe to call lifecycle and system events
- **Permissions**: Extend the RBAC system with module-specific permissions
- **Migrations**: Isolated database migrations in `database/migrations/`
- **Routes**: Module-owned API routes in `routes/api.php`

MD;
    }

    protected function generateComposerJson(string $studlyName, string $kebabName): string
    {
        return <<<JSON
{
    "name": "nizam/{$kebabName}",
    "description": "{$studlyName} module for NIZAM",
    "type": "nizam-module",
    "autoload": {
        "psr-4": {
            "Modules\\\\{$studlyName}\\\\": "app/",
            "Modules\\\\{$studlyName}\\\\Providers\\\\": "app/Providers/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "Modules\\\\{$studlyName}\\\\Providers\\\\{$studlyName}ServiceProvider"
            ]
        }
    }
}

JSON;
    }
}
