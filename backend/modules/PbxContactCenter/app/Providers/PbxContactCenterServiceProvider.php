<?php

namespace Modules\PbxContactCenter\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PbxContactCenterServiceProvider extends ServiceProvider
{
    protected string $name = 'PbxContactCenter';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));

        Route::middleware('api')
            ->group(module_path($this->name, 'routes/api.php'));
    }

    public function register(): void {}
}
