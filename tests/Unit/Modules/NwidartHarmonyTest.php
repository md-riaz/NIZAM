<?php

namespace Tests\Unit\Modules;

use App\Modules\ModuleRegistry;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Str;
use Nwidart\Modules\Facades\Module as NwidartModule;
use Tests\TestCase;

/**
 * CHECKPOINT 6 — ModuleRegistry vs nwidart Harmony
 *
 * Verifies that nwidart/laravel-modules is the single source of truth for
 * module activation state. Disabling a module via nwidart must prevent NIZAM
 * hook registration; re-enabling must restore it.
 */
class NwidartHarmonyTest extends TestCase
{
    public function test_nwidart_disabled_module_is_disabled_in_nizam_registry(): void
    {
        // Temporarily disable PbxRouting in nwidart
        NwidartModule::find('PbxRouting')?->disable();

        try {
            // Rebuild the registry singleton so it picks up the new nwidart state
            $this->app->forgetInstance(ModuleRegistry::class);
            $registry = $this->app->make(ModuleRegistry::class);

            $this->assertFalse(
                $registry->isEnabled('pbx-routing'),
                'NIZAM registry must honour nwidart disabled state for pbx-routing'
            );
        } finally {
            // Always restore so other tests are not affected
            NwidartModule::find('PbxRouting')?->enable();
            $this->app->forgetInstance(ModuleRegistry::class);
        }
    }

    public function test_nwidart_enabled_module_is_enabled_in_nizam_registry(): void
    {
        // PbxRouting is enabled by default in modules_statuses.json
        $registry = $this->app->make(ModuleRegistry::class);

        $this->assertTrue(
            $registry->isEnabled('pbx-routing'),
            'NIZAM registry must reflect nwidart enabled state for pbx-routing'
        );
    }

    public function test_all_nwidart_modules_activation_state_matches_nizam_registry(): void
    {
        $registry = $this->app->make(ModuleRegistry::class);

        $moduleConfigs = config('nizam.modules', []);

        foreach ($moduleConfigs as $alias => $config) {
            $studlyName = Str::studly($alias);
            $nwidartEnabled = NwidartModule::isEnabled($studlyName);

            $this->assertSame(
                $nwidartEnabled,
                $registry->isEnabled($alias),
                "Activation mismatch for '{$alias}': nwidart={$studlyName} says "
                .($nwidartEnabled ? 'enabled' : 'disabled')
                .', but NIZAM registry disagrees'
            );
        }
    }

    public function test_nwidart_is_single_source_of_truth_not_env_config(): void
    {
        // Disable via nwidart (simulates: php artisan module:disable PbxContactCenter)
        NwidartModule::find('PbxContactCenter')?->disable();

        try {
            $this->app->forgetInstance(ModuleRegistry::class);
            $registry = $this->app->make(ModuleRegistry::class);

            // Even if no env override is set, nwidart disabled = NIZAM disabled
            $this->assertFalse(
                $registry->isEnabled('pbx-contact-center'),
                'nwidart disable must override any config/env enabled flag'
            );

            // Hooks must not be collected
            $this->assertNotContains(
                'queues.view',
                $registry->collectPermissions(),
                'Disabled module permissions must not be collected'
            );
        } finally {
            NwidartModule::find('PbxContactCenter')?->enable();
            $this->app->forgetInstance(ModuleRegistry::class);
        }
    }

    public function test_app_service_provider_nwidart_is_enabled_returns_correct_state(): void
    {
        $provider = new AppServiceProvider($this->app);

        // Known-enabled module — nwidart returns true
        $this->assertTrue($provider->nwidartIsEnabled('pbx-routing'));

        // Unknown module — falls back to config default (true when no config)
        $this->assertTrue($provider->nwidartIsEnabled('non-existent-module'));

        // Unknown module with explicit false in fallback config
        $this->assertFalse($provider->nwidartIsEnabled('non-existent-module', ['enabled' => false]));
    }

    public function test_studly_case_conversion_matches_nwidart_module_names(): void
    {
        // Verify the StudlyCase conversion is correct for every configured module
        $moduleConfigs = config('nizam.modules', []);

        foreach ($moduleConfigs as $alias => $config) {
            $studlyName = Str::studly($alias);

            // Must not throw ModuleNotFoundException — module exists in nwidart
            $this->assertIsBool(
                NwidartModule::isEnabled($studlyName),
                "NwidartModule::isEnabled('{$studlyName}') must return bool for alias '{$alias}'"
            );
        }
    }
}
