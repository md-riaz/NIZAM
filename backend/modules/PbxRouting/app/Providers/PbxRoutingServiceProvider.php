<?php

namespace Modules\PbxRouting\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PbxRoutingServiceProvider extends ServiceProvider
{
    protected string $name = 'PbxRouting';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));

        Route::middleware('api')
            ->group(module_path($this->name, 'routes/api.php'));
    }

    public function register(): void {}
}
