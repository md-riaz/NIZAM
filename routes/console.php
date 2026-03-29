<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| Run `php artisan schedule:work` (development) or configure a single cron
| entry `* * * * * php /var/www/html/artisan schedule:run >> /dev/null 2>&1`
| on the production server / scheduler container.
|
*/

// Enforce recording retention policies — runs daily at midnight UTC.
// Tenants without a recording_retention_days value are skipped automatically.
Schedule::command('nizam:prune-recordings')
    ->dailyAt('00:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        Log::error('Scheduled task nizam:prune-recordings failed.');
    });

// Automatically check and renew Let's Encrypt certificates — runs daily at 01:00 AM UTC.
Schedule::command('ssl:renew')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        Log::error('Scheduled task ssl:renew failed.');
    });
