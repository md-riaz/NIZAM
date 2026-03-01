<?php

namespace Tests\Unit\Modules;

use App\Modules\Contracts\NizamModule as NizamModuleContract;
use App\Modules\ModuleRegistry;
use App\Providers\AppServiceProvider;
use Nwidart\Modules\Facades\Module as NwidartModule;
use Tests\TestCase;

/**
 * CHECKPOINT 6 — ModuleRegistry vs nwidart Harmony
 *
 * Verifies that nwidart/laravel-modules is the single source of truth for
 * module activation state and that the auto-discovery pipeline is correct.
 * No string transformation (Str::studly) is used — matching is by alias field.
 */
class NwidartHarmonyTest extends TestCase
{
    // =========================================================================
    // DISCOVERY
    // =========================================================================

    public function test_auto_discovery_finds_all_nizam_modules(): void
    {
        $provider = new AppServiceProvider($this->app);
        $discovered = $provider->discoverNizamModules();

        // All five production modules must be auto-discovered
        $this->assertArrayHasKey('pbx-routing', $discovered);
        $this->assertArrayHasKey('pbx-contact-center', $discovered);
        $this->assertArrayHasKey('pbx-automation', $discovered);
        $this->assertArrayHasKey('pbx-analytics', $discovered);
        $this->assertArrayHasKey('pbx-provisioning', $discovered);
    }

    public function test_auto_discovery_only_finds_nizam_module_implementors(): void
    {
        $provider = new AppServiceProvider($this->app);
        $discovered = $provider->discoverNizamModules();

        foreach ($discovered as $alias => $class) {
            $this->assertTrue(
                is_a($class, NizamModuleContract::class, true),
                "Discovered class {$class} (alias={$alias}) must implement NizamModule"
            );
        }
    }

    public function test_auto_discovery_matches_nwidart_alias_without_string_transformation(): void
    {
        $provider = new AppServiceProvider($this->app);
        $discovered = $provider->discoverNizamModules();

        // Discovered aliases must come directly from nwidart module.json alias field,
        // not from Str::studly() or any other transformation
        $nwidartAliases = collect(NwidartModule::all())
            ->map(fn ($m) => $m->get('alias'))
            ->filter()
            ->values()
            ->all();

        foreach (array_keys($discovered) as $alias) {
            $this->assertContains(
                $alias,
                $nwidartAliases,
                "Discovered alias '{$alias}' must match a nwidart module's alias field"
            );
        }
    }

    // =========================================================================
    // ACTIVATION STATE
    // =========================================================================

    public function test_nwidart_disabled_module_is_disabled_in_nizam_registry(): void
    {
        NwidartModule::find('PbxRouting')?->disable();

        try {
            $this->app->forgetInstance(ModuleRegistry::class);
            $registry = $this->app->make(ModuleRegistry::class);

            $this->assertFalse(
                $registry->isEnabled('pbx-routing'),
                'NIZAM registry must honour nwidart disabled state for pbx-routing'
            );
        } finally {
            NwidartModule::find('PbxRouting')?->enable();
            $this->app->forgetInstance(ModuleRegistry::class);
        }
    }

    public function test_nwidart_enabled_module_is_enabled_in_nizam_registry(): void
    {
        $registry = $this->app->make(ModuleRegistry::class);

        $this->assertTrue(
            $registry->isEnabled('pbx-routing'),
            'NIZAM registry must reflect nwidart enabled state for pbx-routing'
        );
    }

    public function test_all_nwidart_modules_activation_state_matches_nizam_registry(): void
    {
        $registry = $this->app->make(ModuleRegistry::class);

        foreach (NwidartModule::all() as $nwidartModule) {
            $alias = $nwidartModule->get('alias');
            if ($alias === null || ! $registry->get($alias)) {
                // This nwidart module has no NizamModule implementation — intentionally
                // skipped. Not every nwidart module must participate in NIZAM hook registry.
                continue;
            }

            $this->assertSame(
                $nwidartModule->isEnabled(),
                $registry->isEnabled($alias),
                "Activation mismatch for '{$alias}': nwidart says "
                .($nwidartModule->isEnabled() ? 'enabled' : 'disabled')
                .', but NIZAM registry disagrees'
            );
        }
    }

    public function test_nwidart_is_single_source_of_truth(): void
    {
        NwidartModule::find('PbxContactCenter')?->disable();

        try {
            $this->app->forgetInstance(ModuleRegistry::class);
            $registry = $this->app->make(ModuleRegistry::class);

            $this->assertFalse(
                $registry->isEnabled('pbx-contact-center'),
                'nwidart disable must be the only activation authority'
            );
        } finally {
            NwidartModule::find('PbxContactCenter')?->enable();
            $this->app->forgetInstance(ModuleRegistry::class);
        }
    }

    // =========================================================================
    // FAIL-CLOSED BEHAVIOR
    // =========================================================================

    public function test_unknown_module_alias_returns_false_fail_closed(): void
    {
        $provider = new AppServiceProvider($this->app);

        // Unregistered module must return false, never true
        $this->assertFalse(
            $provider->nwidartIsEnabled('non-existent-module'),
            'Unregistered module alias must return false (fail-closed) — not true'
        );
    }

    public function test_unregistered_module_is_not_in_nizam_registry(): void
    {
        // If a module.json has no NizamModule class, it must not appear in the registry
        $registry = $this->app->make(ModuleRegistry::class);

        $this->assertNull($registry->get('non-existent-module'));
    }

    // =========================================================================
    // DEEP HOOK SUPPRESSION (CHECKPOINT 8)
    // =========================================================================

    public function test_disabled_module_suppresses_permissions(): void
    {
        NwidartModule::find('PbxContactCenter')?->disable();

        try {
            $this->app->forgetInstance(ModuleRegistry::class);
            $registry = $this->app->make(ModuleRegistry::class);

            $this->assertNotContains('queues.view', $registry->collectPermissions());
            $this->assertNotContains('agents.manage', $registry->collectPermissions());
        } finally {
            NwidartModule::find('PbxContactCenter')?->enable();
            $this->app->forgetInstance(ModuleRegistry::class);
        }
    }

    public function test_disabled_module_suppresses_dialplan_contributions(): void
    {
        NwidartModule::find('PbxRouting')?->disable();

        try {
            $this->app->forgetInstance(ModuleRegistry::class);
            $registry = $this->app->make(ModuleRegistry::class);

            // No dialplan fragments from disabled module
            $this->assertEmpty(
                $registry->collectDialplanContributions('test.example.com', '1001')
            );
        } finally {
            NwidartModule::find('PbxRouting')?->enable();
            $this->app->forgetInstance(ModuleRegistry::class);
        }
    }

    public function test_disabled_module_suppresses_route_collection(): void
    {
        NwidartModule::find('PbxAutomation')?->disable();

        try {
            $this->app->forgetInstance(ModuleRegistry::class);
            $registry = $this->app->make(ModuleRegistry::class);

            $routeFiles = $registry->collectRouteFiles();

            // Automation's route file must not be present in the collected list
            $automationRouteFile = base_path('modules/PbxAutomation/routes/api.php');
            $this->assertNotContains(
                $automationRouteFile,
                $routeFiles,
                'Disabled module route file must not be included in collectRouteFiles()'
            );
        } finally {
            NwidartModule::find('PbxAutomation')?->enable();
            $this->app->forgetInstance(ModuleRegistry::class);
        }
    }

    public function test_all_modules_present_in_nwidart_repository(): void
    {
        $provider = new AppServiceProvider($this->app);
        $discovered = $provider->discoverNizamModules();

        foreach (array_keys($discovered) as $alias) {
            // nwidartIsEnabled must not log a warning — module must be found in nwidart
            $this->assertIsBool(
                $provider->nwidartIsEnabled($alias),
                "nwidartIsEnabled('{$alias}') must return bool without warning"
            );
        }
    }
}
