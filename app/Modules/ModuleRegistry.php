<?php

namespace App\Modules;

use App\Modules\Contracts\NizamModule;
use Illuminate\Support\Facades\Log;

class ModuleRegistry
{
    /** @var array<string, NizamModule> */
    protected array $modules = [];

    /**
     * Register a module instance.
     */
    public function register(NizamModule $module): void
    {
        $this->modules[$module->name()] = $module;
        $module->register();

        Log::info('Module registered', ['module' => $module->name(), 'version' => $module->version()]);
    }

    /**
     * Boot all registered modules.
     */
    public function bootAll(): void
    {
        foreach ($this->modules as $module) {
            $module->boot();
        }
    }

    /**
     * Collect migration paths from all modules for isolated migration loading.
     *
     * @return array<string>
     */
    public function collectMigrationPaths(): array
    {
        $paths = [];

        foreach ($this->modules as $module) {
            $path = $module->migrationsPath();
            if ($path && is_dir($path)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * Get a registered module by name.
     */
    public function get(string $name): ?NizamModule
    {
        return $this->modules[$name] ?? null;
    }

    /**
     * Get all registered modules.
     *
     * @return array<string, NizamModule>
     */
    public function all(): array
    {
        return $this->modules;
    }

    /**
     * Collect dialplan contributions from all modules.
     *
     * @return array<int, string>
     */
    public function collectDialplanContributions(string $tenantDomain, string $destination): array
    {
        $contributions = [];

        foreach ($this->modules as $module) {
            foreach ($module->dialplanContributions($tenantDomain, $destination) as $priority => $xml) {
                $contributions[$priority] = $xml;
            }
        }

        ksort($contributions);

        return $contributions;
    }

    /**
     * Dispatch an event to all modules that subscribe to it.
     */
    public function dispatchEvent(string $eventType, array $data): void
    {
        foreach ($this->modules as $module) {
            if (in_array($eventType, $module->subscribedEvents())) {
                try {
                    $module->handleEvent($eventType, $data);
                } catch (\Throwable $e) {
                    Log::error('Module event handler failed', [
                        'module' => $module->name(),
                        'event' => $eventType,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Collect all permissions from all modules.
     *
     * @return array<string>
     */
    public function collectPermissions(): array
    {
        $permissions = [];

        foreach ($this->modules as $module) {
            $permissions = array_merge($permissions, $module->permissions());
        }

        return array_unique($permissions);
    }
}
