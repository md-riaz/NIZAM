<?php

namespace Modules\PbxMediaPolicy\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PbxMediaPolicyServiceProvider extends ServiceProvider
{
    protected string $name = 'PbxMediaPolicy';

    public function boot(): void
    {
        Route::middleware('api')
            ->group(module_path($this->name, 'routes/api.php'));
    }

    public function register(): void {}
}
