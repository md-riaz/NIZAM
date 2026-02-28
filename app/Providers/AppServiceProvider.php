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
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class, function () {
            $registry = new ModuleRegistry;

            $moduleConfigs = config('modules', []);

            // Resolve load order based on dependencies
            $moduleClasses = [];
            foreach ($moduleConfigs as $name => $moduleConfig) {
                if (! is_array($moduleConfig) || ! isset($moduleConfig['class'])) {
                    continue;
                }
                $moduleClasses[$name] = $moduleConfig['class'];
            }

            $orderedClasses = ModuleRegistry::resolveDependencies($moduleClasses);

            // Register modules in resolved order
            foreach ($orderedClasses as $class) {
                $module = $this->app->make($class);
                $registry->register($module);

                $name = $module->name();
                if (! ($moduleConfigs[$name]['enabled'] ?? true)) {
                    $registry->disable($name);
                }
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

        // Boot all registered modules, load their migrations and routes
        $registry = $this->app->make(ModuleRegistry::class);
        $registry->bootAll();

        foreach ($registry->collectMigrationPaths() as $path) {
            $this->loadMigrationsFrom($path);
        }

        foreach ($registry->collectRouteFiles() as $routeFile) {
            Route::prefix('api')
                ->middleware('api')
                ->group($routeFile);
        }
    }
}
