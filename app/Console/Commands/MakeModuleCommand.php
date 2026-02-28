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

        $basePath = base_path("modules/{$kebabName}");

        if (is_dir($basePath)) {
            $this->error("Module directory already exists: modules/{$kebabName}");

            return self::FAILURE;
        }

        // Create directory structure
        $directories = [
            $basePath.'/src',
            $basePath.'/database/migrations',
            $basePath.'/config',
            $basePath.'/tests',
        ];

        foreach ($directories as $dir) {
            if (! mkdir($dir, 0755, true) && ! is_dir($dir)) {
                $this->error("Failed to create directory: {$dir}");

                return self::FAILURE;
            }
        }

        // Generate module class
        file_put_contents(
            "{$basePath}/src/{$studlyName}Module.php",
            $this->generateModuleClass($studlyName, $kebabName, $snakeName)
        );

        // Generate config file
        file_put_contents(
            "{$basePath}/config/{$snakeName}.php",
            $this->generateConfig($studlyName)
        );

        // Generate service provider
        file_put_contents(
            "{$basePath}/src/{$studlyName}ServiceProvider.php",
            $this->generateServiceProvider($studlyName, $kebabName, $snakeName)
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

        $this->info("Module skeleton created at: modules/{$kebabName}/");
        $this->newLine();
        $this->line('  <comment>Files created:</comment>');
        $this->line("    src/{$studlyName}Module.php       — Module implementation");
        $this->line("    src/{$studlyName}ServiceProvider.php — Laravel service provider");
        $this->line("    config/{$snakeName}.php           — Module configuration");
        $this->line('    database/migrations/              — Module migrations directory');
        $this->line('    tests/                            — Module tests directory');
        $this->line('    composer.json                     — Package manifest');
        $this->line('    README.md                         — Module documentation');
        $this->newLine();
        $this->line('  <comment>Next steps:</comment>');
        $this->line('    1. Register the module in AppServiceProvider:');
        $this->line("       \$registry->register(new \\Modules\\{$studlyName}\\{$studlyName}Module());");
        $this->line('    2. Implement your dialplan contributions, event handlers, and permissions');
        $this->line('    3. Add migrations to database/migrations/');

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
        // switch (\$eventType) {
        //     case 'call.hangup':
        //         // Process hangup event
        //         break;
        // }
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
}

PHP;
    }

    protected function generateConfig(string $studlyName): string
    {
        return <<<'PHP'
<?php

return [
    'enabled' => env('MODULE_'.strtoupper('PLACEHOLDER').'_ENABLED', true),
];

PHP;
    }

    protected function generateServiceProvider(string $studlyName, string $kebabName, string $snakeName): string
    {
        return <<<PHP
<?php

namespace Modules\\{$studlyName};

use App\\Modules\\ModuleRegistry;
use Illuminate\\Support\\ServiceProvider;

class {$studlyName}ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        \$this->mergeConfigFrom(__DIR__ . '/../config/{$snakeName}.php', '{$snakeName}');
    }

    public function boot(): void
    {
        /** @var ModuleRegistry \$registry */
        \$registry = \$this->app->make(ModuleRegistry::class);
        \$registry->register(new {$studlyName}Module());
    }
}

PHP;
    }

    protected function generateReadme(string $studlyName, string $kebabName): string
    {
        return <<<MD
# {$studlyName} Module

A NIZAM module for {$studlyName} functionality.

## Installation

Register the module in your `AppServiceProvider` or use the auto-discovery service provider.

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
            "Modules\\\\{$studlyName}\\\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "Modules\\\\{$studlyName}\\\\{$studlyName}ServiceProvider"
            ]
        }
    }
}

JSON;
    }
}
