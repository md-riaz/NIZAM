<?php

namespace App\Providers;

use App\Models\Extension;
use App\Modules\ModuleRegistry;
use App\Observers\ExtensionObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class);
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

        // Boot all registered modules
        $this->app->make(ModuleRegistry::class)->bootAll();
    }
}
