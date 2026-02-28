<?php

namespace Modules\PbxProvisioning\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PbxProvisioningServiceProvider extends ServiceProvider
{
    protected string $name = 'PbxProvisioning';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));

        Route::middleware('api')
            ->group(module_path($this->name, 'routes/api.php'));
    }

    public function register(): void {}
}
