<?php

namespace App\Providers;

use App\Listeners\ArchiveCallRecording;
use App\Models\Agent;
use App\Models\Extension;
use App\Models\Gateway;
use App\Models\Holiday;
use App\Models\HolidayCalendar;
use App\Models\Queue;
use App\Models\QueueEntry;
use App\Models\Schedule;
use App\Models\ScheduleBreak;
use App\Models\ScheduleException;
use App\Models\ScheduleRule;
use App\Modules\Contracts\NizamModule as NizamModuleContract;
use App\Modules\Media\MediaArchiveModule;
use App\Modules\Messaging\MessagingModule;
use App\Modules\ModuleRegistry;
use App\Modules\Voicemail\VoicemailModule;
use App\Observers\AgentObserver;
use App\Services\Storage\LocalFileSystemDriver;
use App\Services\Storage\StorageDriver;
use App\Observers\ExtensionObserver;
use App\Observers\GatewayObserver;
use App\Observers\HolidayCalendarObserver;
use App\Observers\HolidayObserver;
use App\Observers\QueueEntryObserver;
use App\Observers\QueueObserver;
use App\Observers\ScheduleBreakObserver;
use App\Observers\ScheduleExceptionObserver;
use App\Observers\ScheduleObserver;
use App\Observers\ScheduleRuleObserver;
use App\Policies\CallPolicy;
use App\Events\CallDeliveryPushRequested;
use App\Events\CallDetailRecordCreated;
use App\Listeners\EnrichCallDetailRecord;
use App\Listeners\HandleCallDeliveryPushRequested;
use App\Services\Call\FreeSwitchOfferCommandDispatcher;
use App\Services\Call\OfferCommandDispatcher;
use App\Services\EslConnectionManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
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
        $this->app->singleton(EslConnectionManager::class, fn () => EslConnectionManager::fromConfig());
        $this->app->bind(OfferCommandDispatcher::class, FreeSwitchOfferCommandDispatcher::class);
        $this->app->singleton(StorageDriver::class, fn () => new LocalFileSystemDriver);
        $this->app->singleton(VoicemailModule::class, fn () => new VoicemailModule($this->app->make(\App\Modules\Voicemail\VoicemailEventService::class)));
        $this->app->singleton(MediaArchiveModule::class, fn () => new MediaArchiveModule(app(StorageDriver::class)));
        $this->app->singleton(MessagingModule::class, fn () => new MessagingModule);

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

            $this->registerBuiltInAppLocalModules($registry);

            return $registry;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fail-safe to prevent tests from wiping the production/development database.
        // Even if putenv() fails, or env variables drift, this stops execution
        // if RefreshDatabase tries to run on postgres during tests.
        if ($this->app->environment('testing') && config('database.default') !== 'sqlite') {
            throw new \RuntimeException(sprintf(
                'Tests are configured to use %s instead of sqlite. ' .
                'Aborting to protect the database.',
                config('database.default')
            ));
        }

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        Gateway::observe(GatewayObserver::class);
        \App\Models\Did::observe(\App\Observers\DidObserver::class);
        \App\Models\RingGroup::observe(\App\Observers\RingGroupObserver::class);
        \App\Models\Ivr::observe(\App\Observers\IvrObserver::class);
        \App\Models\TimeCondition::observe(\App\Observers\TimeConditionObserver::class);
        \App\Models\CallRoutingPolicy::observe(\App\Observers\CallRoutingPolicyObserver::class);
        \App\Models\Team::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\User::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\Schedule::observe(\App\Observers\ScheduleObserver::class);
        \App\Models\ScheduleRule::observe(\App\Observers\ScheduleRuleObserver::class);
        \App\Models\ScheduleBreak::observe(\App\Observers\ScheduleBreakObserver::class);
        \App\Models\ScheduleException::observe(\App\Observers\ScheduleExceptionObserver::class);
        \App\Models\HolidayCalendar::observe(\App\Observers\HolidayCalendarObserver::class);
        \App\Models\Holiday::observe(\App\Observers\HolidayObserver::class);
        \App\Models\Extension::observe(\App\Observers\ExtensionObserver::class);
        \App\Models\Queue::observe(\App\Observers\QueueObserver::class);
        \App\Models\QueueEntry::observe(\App\Observers\QueueEntryObserver::class);
        \App\Models\Agent::observe(\App\Observers\AgentObserver::class);
        \App\Models\TeamMember::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\QueueMember::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\Recording::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\Flow::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\FlowVersion::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\FlowNode::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\FlowEdge::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\FlowCompiledArtifact::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\Organization::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\Bridge::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\DeviceProfile::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\SipProfile::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\EndpointBinding::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\DeviceRegistrationSnapshot::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\Webhook::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\WebhookDeliveryAttempt::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\CallDeliveryAttempt::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\CallSession::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\CallEventLog::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\OrganizationDialplanManifest::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\QueueMetric::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\WallboardAgentProjection::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\WallboardQueueProjection::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\AnalyticsEvent::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\UsageRecord::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\Alert::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\AlertPolicy::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\BlockedDestination::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\CdrEnrichment::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\CallTraceEvent::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\Permission::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\AuditLog::observe(\App\Observers\ScheduleChildObserver::class);
        \App\Models\PushNotificationLog::observe(\App\Observers\ScheduleChildObserver::class);

        Event::listen(
            CallDetailRecordCreated::class,
            EnrichCallDetailRecord::class,
        );

        Event::listen(
            CallDetailRecordCreated::class,
            ArchiveCallRecording::class,
        );

        Event::listen(
            CallDeliveryPushRequested::class,
            HandleCallDeliveryPushRequested::class,
        );

        // Register non-model call authorization gates
        $callPolicy = new CallPolicy;
        Gate::define('originate', fn ($user) => $callPolicy->before($user, 'originate') ?? $callPolicy->originate($user));
        Gate::define('viewStatus', fn ($user) => $callPolicy->before($user, 'viewStatus') ?? $callPolicy->viewStatus($user));
        Gate::define('callControl', fn ($user) => $callPolicy->before($user, 'callControl') ?? $callPolicy->callControl($user));

        // Platform admin gate - for system-level operations (logs, health, etc.)
        Gate::define('platform-admin', fn ($user) => $user->role === 'superadmin' && $user->organization_id === null);

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

    /**
     * Register built-in app-local modules with explicit config-driven enabled state.
     *
     * These modules live in app/Modules but still flow through the same registry
     * bootstrap path as nwidart modules, which keeps event wiring deterministic.
     */
    public function registerBuiltInAppLocalModules(ModuleRegistry $registry): void
    {
        foreach ($this->builtInAppLocalModules() as $name => $class) {
            $registry->register($this->app->make($class), $this->builtInAppLocalModuleIsEnabled($name));
        }
    }

    /**
     * @return array<string, class-string<NizamModuleContract>>
     */
    public function builtInAppLocalModules(): array
    {
        return [
            'voicemail' => VoicemailModule::class,
            'media-archive' => MediaArchiveModule::class,
            'messaging' => MessagingModule::class,
        ];
    }

    public function builtInAppLocalModuleIsEnabled(string $moduleName): bool
    {
        return (bool) config("telephony.app_local_modules.{$moduleName}.enabled", false);
    }
}
