<?php

namespace App\Modules;

use App\Modules\Contracts\NizamModule;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ModuleRegistry
{
    /** @var array<string, NizamModule> */
    protected array $modules = [];

    /** @var array<string, bool> */
    protected array $enabled = [];

    /** @var array<string, array<string, callable>> */
    protected array $policyHooks = [];

    /**
     * Register a module instance.
     *
     * @throws RuntimeException When module manifest is invalid
     */
    public function register(NizamModule $module): void
    {
        $this->validateManifest($module);

        $this->modules[$module->name()] = $module;
        $this->enabled[$module->name()] = true;
        $module->register();

        // Collect policy hooks declared by the module
        foreach ($module->policyHooks() as $hook => $callback) {
            $this->policyHooks[$hook][$module->name()] = $callback;
        }

        Log::info('Module registered', ['module' => $module->name(), 'version' => $module->version()]);
    }

    /**
     * Validate a module's manifest before registration.
     *
     * @throws RuntimeException When manifest is invalid
     */
    protected function validateManifest(NizamModule $module): void
    {
        $name = $module->name();
        if (empty($name) || ! is_string($name)) {
            throw new RuntimeException('Module manifest invalid: name must be a non-empty string');
        }

        $version = $module->version();
        if (empty($version) || ! is_string($version)) {
            throw new RuntimeException("Module '{$name}' manifest invalid: version must be a non-empty string");
        }

        if (isset($this->modules[$name])) {
            throw new RuntimeException("Module '{$name}' is already registered");
        }
    }

    /**
     * Enable a module by name.
     */
    public function enable(string $name): void
    {
        if (isset($this->modules[$name])) {
            // Check that all dependencies are enabled
            foreach ($this->modules[$name]->dependencies() as $dep) {
                if (! $this->isEnabled($dep)) {
                    Log::warning('Cannot enable module: dependency is disabled', [
                        'module' => $name,
                        'dependency' => $dep,
                    ]);

                    return;
                }
            }

            $this->enabled[$name] = true;
        }
    }

    /**
     * Disable a module by name. Also disables any modules that depend on it.
     */
    public function disable(string $name): void
    {
        if (isset($this->modules[$name])) {
            $this->enabled[$name] = false;

            // Cascade: disable any modules that depend on this one
            foreach ($this->modules as $otherName => $otherModule) {
                if ($otherName !== $name && $this->isEnabled($otherName)) {
                    if (in_array($name, $otherModule->dependencies())) {
                        $this->disable($otherName);
                    }
                }
            }
        }
    }

    /**
     * Check if a module is enabled.
     */
    public function isEnabled(string $name): bool
    {
        return $this->enabled[$name] ?? false;
    }

    /**
     * Boot all registered and enabled modules.
     */
    public function bootAll(): void
    {
        foreach ($this->modules as $name => $module) {
            if ($this->isEnabled($name)) {
                $module->boot();
            }
        }
    }

    /**
     * Collect migration paths from all enabled modules.
     *
     * @return array<string>
     */
    public function collectMigrationPaths(): array
    {
        $paths = [];

        foreach ($this->modules as $name => $module) {
            if (! $this->isEnabled($name)) {
                continue;
            }

            $path = $module->migrationsPath();
            if ($path && is_dir($path)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * Collect route files from all enabled modules.
     *
     * @return array<string>
     */
    public function collectRouteFiles(): array
    {
        $files = [];

        foreach ($this->modules as $name => $module) {
            if (! $this->isEnabled($name)) {
                continue;
            }

            $file = $module->routesFile();
            if ($file && file_exists($file)) {
                $files[] = $file;
            }
        }

        return $files;
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
     * Get all enabled modules.
     *
     * @return array<string, NizamModule>
     */
    public function enabled(): array
    {
        return array_filter($this->modules, fn (NizamModule $m) => $this->isEnabled($m->name()));
    }

    /**
     * Collect dialplan contributions from all enabled modules.
     *
     * @return array<int, string>
     */
    public function collectDialplanContributions(string $tenantDomain, string $destination): array
    {
        $contributions = [];

        foreach ($this->modules as $name => $module) {
            if (! $this->isEnabled($name)) {
                continue;
            }

            foreach ($module->dialplanContributions($tenantDomain, $destination) as $priority => $xml) {
                $contributions[$priority] = $xml;
            }
        }

        ksort($contributions);

        return $contributions;
    }

    /**
     * Dispatch an event to all enabled modules that subscribe to it.
     */
    public function dispatchEvent(string $eventType, array $data): void
    {
        foreach ($this->modules as $name => $module) {
            if (! $this->isEnabled($name)) {
                continue;
            }

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
     * Collect all permissions from all enabled modules.
     *
     * @return array<string>
     */
    public function collectPermissions(): array
    {
        $permissions = [];

        foreach ($this->modules as $name => $module) {
            if (! $this->isEnabled($name)) {
                continue;
            }

            $permissions = array_merge($permissions, $module->permissions());
        }

        return array_unique($permissions);
    }

    /**
     * Execute a named policy hook with the given arguments.
     *
     * @return array<string, mixed>
     */
    public function executePolicyHook(string $hook, array $args = []): array
    {
        $results = [];

        foreach (($this->policyHooks[$hook] ?? []) as $moduleName => $callback) {
            if (! $this->isEnabled($moduleName)) {
                continue;
            }

            try {
                $results[$moduleName] = $callback(...$args);
            } catch (\Throwable $e) {
                Log::error('Module policy hook failed', [
                    'module' => $moduleName,
                    'hook' => $hook,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Return all module manifests.
     *
     * @return array<string, array<string, mixed>>
     */
    public function manifests(): array
    {
        $manifests = [];

        foreach ($this->modules as $name => $module) {
            $manifests[$name] = [
                'name' => $module->name(),
                'version' => $module->version(),
                'description' => $module->description(),
                'dependencies' => $module->dependencies(),
                'enabled' => $this->isEnabled($name),
            ];
        }

        return $manifests;
    }

    /**
     * Resolve module registration order based on dependencies.
     * Returns module class names in the correct load order.
     *
     * @param  array<string, class-string<NizamModule>>  $moduleClasses  Keyed by module name
     * @return array<class-string<NizamModule>>
     *
     * @throws RuntimeException When circular or missing dependencies detected
     */
    public static function resolveDependencies(array $moduleClasses): array
    {
        // Build dependency graph from instances
        $instances = [];
        foreach ($moduleClasses as $name => $class) {
            $instances[$name] = app($class);
        }

        $resolved = [];
        $unresolved = [];

        foreach ($instances as $name => $instance) {
            if (! in_array($name, $resolved)) {
                static::resolve($name, $instances, $resolved, $unresolved);
            }
        }

        return array_map(fn (string $name) => $moduleClasses[$name], $resolved);
    }

    /**
     * Recursive dependency resolver (topological sort).
     *
     * @param  array<string, NizamModule>  $instances
     * @param  array<string>  $resolved
     * @param  array<string>  $unresolved
     *
     * @throws RuntimeException
     */
    protected static function resolve(string $name, array $instances, array &$resolved, array &$unresolved): void
    {
        $unresolved[] = $name;

        if (! isset($instances[$name])) {
            throw new RuntimeException("Module dependency not found: {$name}");
        }

        foreach ($instances[$name]->dependencies() as $dep) {
            if (! isset($instances[$dep])) {
                throw new RuntimeException("Module '{$name}' depends on unknown module '{$dep}'");
            }

            if (in_array($dep, $unresolved)) {
                throw new RuntimeException("Circular dependency detected: {$name} <-> {$dep}");
            }

            if (! in_array($dep, $resolved)) {
                static::resolve($dep, $instances, $resolved, $unresolved);
            }
        }

        $resolved[] = $name;
        $unresolved = array_values(array_diff($unresolved, [$name]));
    }
}
