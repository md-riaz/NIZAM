<?php

namespace Modules\PbxAutomation\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PbxAutomationServiceProvider extends ServiceProvider
{
    protected string $name = 'PbxAutomation';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));

        Route::middleware('api')
            ->group(module_path($this->name, 'routes/api.php'));
    }

    public function register(): void {}
}
