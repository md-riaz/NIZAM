<?php

namespace App\Providers;

use App\Models\Extension;
use App\Modules\ModuleRegistry;
use App\Observers\ExtensionObserver;
use App\Policies\CallPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Nwidart\Modules\Exceptions\ModuleNotFoundException;
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

            $moduleConfigs = config('nizam.modules', []);

            // Resolve load order based on dependencies
            $moduleClasses = [];
            foreach ($moduleConfigs as $name => $moduleConfig) {
                if (! is_array($moduleConfig) || ! isset($moduleConfig['class'])) {
                    continue;
                }
                $moduleClasses[$name] = $moduleConfig['class'];
            }

            $orderedClasses = ModuleRegistry::resolveDependencies($moduleClasses);

            // Register modules in resolved order.
            // nwidart is the single source of truth for activation state.
            foreach ($orderedClasses as $class) {
                $module = $this->app->make($class);
                $name = $module->name();
                $enabled = $this->nwidartIsEnabled($name, $moduleConfigs[$name] ?? []);

                $registry->register($module, $enabled);
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
     * Determine if a NIZAM module should be enabled.
     *
     * nwidart/laravel-modules is the single source of truth for module activation.
     * The module's StudlyCase name (e.g. PbxRouting for "pbx-routing") is used
     * to look up the activation state in modules_statuses.json via the nwidart
     * facade. Falls back to the local config only when the module is not yet
     * registered with nwidart (e.g. during initial scaffolding).
     *
     * @param  array<string, mixed>  $moduleConfig
     */
    public function nwidartIsEnabled(string $nizamAlias, array $moduleConfig = []): bool
    {
        try {
            return NwidartModule::isEnabled(Str::studly($nizamAlias));
        } catch (ModuleNotFoundException) {
            // Module not yet registered with nwidart; respect local config as fallback.
            return $moduleConfig['enabled'] ?? true;
        }
    }
}
