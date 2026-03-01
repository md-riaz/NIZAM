<?php

namespace App\Providers;

use App\Models\Extension;
use App\Modules\Contracts\NizamModule as NizamModuleContract;
use App\Modules\ModuleRegistry;
use App\Observers\ExtensionObserver;
use App\Policies\CallPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Nwidart\Modules\Facades\Module as NwidartModule;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class, function () {
            $registry = new ModuleRegistry;

            // Auto-discover NizamModule implementations from nwidart-registered modules.
            // nwidart is the single authority for both discovery and activation state —
            // no separate class mapping in config is required.
            $moduleClasses = $this->discoverNizamModules();
            $orderedClasses = ModuleRegistry::resolveDependencies($moduleClasses);

            foreach ($orderedClasses as $class) {
                $module = $this->app->make($class);
                $registry->register($module, $this->nwidartIsEnabled($module->name()));
            }

            return $registry;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        Extension::observe(ExtensionObserver::class);

        // Register non-model call authorization gates
        $callPolicy = new CallPolicy;
        Gate::define('originate', fn ($user) => $callPolicy->before($user, 'originate') ?? $callPolicy->originate($user));
        Gate::define('viewStatus', fn ($user) => $callPolicy->before($user, 'viewStatus') ?? $callPolicy->viewStatus($user));
        Gate::define('callControl', fn ($user) => $callPolicy->before($user, 'callControl') ?? $callPolicy->callControl($user));

        // Boot all NIZAM modules (telecom hooks: dialplan, policy, events)
        // Routes and migrations are handled by nwidart/laravel-modules ServiceProviders
        $registry = $this->app->make(ModuleRegistry::class);
        $registry->bootAll();
    }

    /**
     * Discover all NizamModule implementations registered with nwidart.
     *
     * Iterates every module known to nwidart (enabled and disabled) and looks
     * for a NizamModule class at the conventional path Modules\{Name}\{Name}Module.
     * Modules without a NizamModule implementation are silently skipped — this is
     * expected for any nwidart module that does not participate in NIZAM's telecom
     * hook registry (e.g. pure UI or infrastructure modules).
     * Modules whose module.json is missing the 'alias' field are skipped with a
     * warning — alias is required to bridge nwidart identity to NIZAM naming.
     *
     * @return array<string, class-string<NizamModuleContract>> Keyed by NIZAM alias
     */
    public function discoverNizamModules(): array
    {
        $discovered = [];

        foreach (NwidartModule::all() as $nwidartModule) {
            $name = $nwidartModule->getName();                   // e.g. PbxRouting
            $class = "Modules\\{$name}\\{$name}Module";         // conventional path

            if (! class_exists($class) || ! is_a($class, NizamModuleContract::class, true)) {
                continue; // not a NIZAM telecom module — intentionally skipped
            }

            $alias = $nwidartModule->get('alias');
            if (! $alias) {
                Log::warning('NIZAM module discovery skipped: module.json is missing alias field', [
                    'module' => $name,
                    'class' => $class,
                ]);

                continue;
            }

            $discovered[$alias] = $class;
        }

        return $discovered;
    }

    /**
     * Determine if a NIZAM module should be enabled.
     *
     * Looks up the module in nwidart's registry by its alias field — the canonical
     * source of truth. Matching by alias avoids any string transformation guesswork
     * (no Str::studly() needed).
     *
     * Fail-closed: if the module is not found in nwidart, it is treated as disabled
     * and a warning is logged. In telecom systems, an unregistered module must not
     * silently inject dialplan fragments, fire policy hooks, or handle call events.
     *
     * NOTE: activation changes take effect after application restart (or opcache
     * flush in production). Dynamic hot-toggling is intentionally not supported —
     * ModuleRegistry is a boot-time singleton. Use `php artisan module:enable|disable`
     * followed by a process restart for the change to apply.
     */
    public function nwidartIsEnabled(string $nizamAlias): bool
    {
        foreach (NwidartModule::all() as $nwidartModule) {
            if ($nwidartModule->get('alias') === $nizamAlias) {
                return $nwidartModule->isEnabled();
            }
        }

        Log::warning('NIZAM module not found in nwidart registry — treating as disabled', [
            'alias' => $nizamAlias,
        ]);

        return false; // fail-closed: unregistered modules must not execute telecom hooks
    }
}
