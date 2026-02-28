<?php

namespace Tests\Unit\Modules;

use App\Modules\Contracts\NizamModule;
use App\Modules\ModuleRegistry;
use Tests\TestCase;

class ModuleRegistryTest extends TestCase
{
    public function test_can_register_and_retrieve_module(): void
    {
        $registry = new ModuleRegistry;

        $module = $this->createMockModule('test-module', '1.0.0');
        $module->expects($this->once())->method('register');

        $registry->register($module);

        $this->assertSame($module, $registry->get('test-module'));
    }

    public function test_get_returns_null_for_unknown_module(): void
    {
        $registry = new ModuleRegistry;

        $this->assertNull($registry->get('nonexistent'));
    }

    public function test_all_returns_registered_modules(): void
    {
        $registry = new ModuleRegistry;

        $module1 = $this->createMockModule('module-a', '1.0.0');
        $module2 = $this->createMockModule('module-b', '2.0.0');

        $registry->register($module1);
        $registry->register($module2);

        $all = $registry->all();
        $this->assertCount(2, $all);
        $this->assertArrayHasKey('module-a', $all);
        $this->assertArrayHasKey('module-b', $all);
    }

    public function test_boot_all_boots_every_module(): void
    {
        $registry = new ModuleRegistry;

        $module = $this->createMockModule('bootable', '1.0.0');
        $module->expects($this->once())->method('boot');

        $registry->register($module);
        $registry->bootAll();
    }

    public function test_collects_dialplan_contributions(): void
    {
        $registry = new ModuleRegistry;

        $module = $this->createMockModuleWith('dialer', '1.0.0', [
            'dialplanContributions' => [10 => '<action application="playback" data="tone_stream://%(200,0,440)"/>'],
        ]);

        $registry->register($module);

        $contributions = $registry->collectDialplanContributions('example.com', '1001');
        $this->assertCount(1, $contributions);
        $this->assertArrayHasKey(10, $contributions);
    }

    public function test_dispatches_events_to_subscribed_modules(): void
    {
        $registry = new ModuleRegistry;

        $module = $this->createMockModuleWith('listener', '1.0.0', [
            'subscribedEvents' => ['call.hangup'],
        ]);
        $module->expects($this->once())->method('handleEvent')
            ->with('call.hangup', ['uuid' => '123']);

        $registry->register($module);
        $registry->dispatchEvent('call.hangup', ['uuid' => '123']);
    }

    public function test_does_not_dispatch_to_non_subscribed_modules(): void
    {
        $registry = new ModuleRegistry;

        $module = $this->createMockModuleWith('non-listener', '1.0.0', [
            'subscribedEvents' => ['call.started'],
        ]);
        $module->expects($this->never())->method('handleEvent');

        $registry->register($module);
        $registry->dispatchEvent('call.hangup', ['uuid' => '123']);
    }

    public function test_collects_permissions_from_modules(): void
    {
        $registry = new ModuleRegistry;

        $module1 = $this->createMockModuleWith('perm-a', '1.0.0', [
            'permissions' => ['recordings.view', 'recordings.delete'],
        ]);

        $module2 = $this->createMockModuleWith('perm-b', '1.0.0', [
            'permissions' => ['fax.send', 'recordings.view'], // duplicate
        ]);

        $registry->register($module1);
        $registry->register($module2);

        $permissions = $registry->collectPermissions();
        $this->assertCount(3, $permissions);
        $this->assertContains('recordings.view', $permissions);
        $this->assertContains('recordings.delete', $permissions);
        $this->assertContains('fax.send', $permissions);
    }

    public function test_module_event_handler_error_does_not_crash(): void
    {
        $registry = new ModuleRegistry;

        $module = $this->createMockModuleWith('crasher', '1.0.0', [
            'subscribedEvents' => ['call.hangup'],
        ]);
        $module->method('handleEvent')
            ->willThrowException(new \RuntimeException('Module error'));

        $registry->register($module);

        // Should not throw — error is logged
        $registry->dispatchEvent('call.hangup', ['uuid' => '123']);
        $this->assertTrue(true);
    }

    public function test_collects_migration_paths_from_modules(): void
    {
        $registry = new ModuleRegistry;

        $module1 = $this->createMockModuleWith('mod-a', '1.0.0', [
            'migrationsPath' => __DIR__,
        ]);

        $module2 = $this->createMockModule('mod-b', '1.0.0');

        $registry->register($module1);
        $registry->register($module2);

        $paths = $registry->collectMigrationPaths();
        $this->assertCount(1, $paths);
        $this->assertEquals(__DIR__, $paths[0]);
    }

    public function test_module_can_be_enabled_and_disabled(): void
    {
        $registry = new ModuleRegistry;

        $module = $this->createMockModule('toggleable', '1.0.0');
        $registry->register($module);

        $this->assertTrue($registry->isEnabled('toggleable'));

        $registry->disable('toggleable');
        $this->assertFalse($registry->isEnabled('toggleable'));

        $registry->enable('toggleable');
        $this->assertTrue($registry->isEnabled('toggleable'));
    }

    public function test_disabled_module_is_not_booted(): void
    {
        $registry = new ModuleRegistry;

        $module = $this->createMockModule('disabled-mod', '1.0.0');
        $module->expects($this->never())->method('boot');

        $registry->register($module);
        $registry->disable('disabled-mod');
        $registry->bootAll();
    }

    public function test_disabled_module_events_not_dispatched(): void
    {
        $registry = new ModuleRegistry;

        $module = $this->createMockModuleWith('disabled-listener', '1.0.0', [
            'subscribedEvents' => ['call.hangup'],
        ]);
        $module->expects($this->never())->method('handleEvent');

        $registry->register($module);
        $registry->disable('disabled-listener');
        $registry->dispatchEvent('call.hangup', ['uuid' => '123']);
    }

    public function test_disabled_module_permissions_not_collected(): void
    {
        $registry = new ModuleRegistry;

        $module = $this->createMockModuleWith('disabled-perm', '1.0.0', [
            'permissions' => ['secret.permission'],
        ]);

        $registry->register($module);
        $registry->disable('disabled-perm');

        $this->assertEmpty($registry->collectPermissions());
    }

    public function test_disabled_module_dialplan_not_collected(): void
    {
        $registry = new ModuleRegistry;

        $module = $this->createMockModuleWith('disabled-dialer', '1.0.0', [
            'dialplanContributions' => [10 => '<action application="hangup"/>'],
        ]);

        $registry->register($module);
        $registry->disable('disabled-dialer');

        $this->assertEmpty($registry->collectDialplanContributions('example.com', '1001'));
    }

    public function test_disabled_module_migrations_not_collected(): void
    {
        $registry = new ModuleRegistry;

        $module = $this->createMockModuleWith('disabled-migrator', '1.0.0', [
            'migrationsPath' => __DIR__,
        ]);

        $registry->register($module);
        $registry->disable('disabled-migrator');

        $this->assertEmpty($registry->collectMigrationPaths());
    }

    public function test_enabled_returns_only_enabled_modules(): void
    {
        $registry = new ModuleRegistry;

        $module1 = $this->createMockModule('active', '1.0.0');
        $module2 = $this->createMockModule('inactive', '1.0.0');

        $registry->register($module1);
        $registry->register($module2);
        $registry->disable('inactive');

        $enabled = $registry->enabled();
        $this->assertCount(1, $enabled);
        $this->assertArrayHasKey('active', $enabled);
        $this->assertArrayNotHasKey('inactive', $enabled);
    }

    public function test_manifests_returns_all_module_info(): void
    {
        $registry = new ModuleRegistry;

        $module = $this->createMockModuleWith('manifest-mod', '2.1.0', [
            'dependencies' => ['core-dep'],
        ]);

        $registry->register($module);

        $manifests = $registry->manifests();
        $this->assertArrayHasKey('manifest-mod', $manifests);
        $this->assertEquals('manifest-mod', $manifests['manifest-mod']['name']);
        $this->assertEquals('2.1.0', $manifests['manifest-mod']['version']);
        $this->assertTrue($manifests['manifest-mod']['enabled']);
        $this->assertEquals(['core-dep'], $manifests['manifest-mod']['dependencies']);
    }

    public function test_collects_route_files_from_enabled_modules(): void
    {
        $registry = new ModuleRegistry;

        $module = $this->createMockModuleWith('routed', '1.0.0', [
            'routesFile' => __FILE__,
        ]);

        $registry->register($module);

        $files = $registry->collectRouteFiles();
        $this->assertCount(1, $files);
        $this->assertEquals(__FILE__, $files[0]);
    }

    public function test_disabled_module_routes_not_collected(): void
    {
        $registry = new ModuleRegistry;

        $module = $this->createMockModuleWith('disabled-routed', '1.0.0', [
            'routesFile' => __FILE__,
        ]);

        $registry->register($module);
        $registry->disable('disabled-routed');

        $this->assertEmpty($registry->collectRouteFiles());
    }

    public function test_policy_hooks_collected_and_executed(): void
    {
        $registry = new ModuleRegistry;

        $module = $this->createMockModuleWith('hooked', '1.0.0', [
            'policyHooks' => [
                'before_route' => fn (string $dest) => "intercepted:{$dest}",
            ],
        ]);

        $registry->register($module);

        $results = $registry->executePolicyHook('before_route', ['1001']);
        $this->assertArrayHasKey('hooked', $results);
        $this->assertEquals('intercepted:1001', $results['hooked']);
    }

    public function test_disabled_module_policy_hooks_not_executed(): void
    {
        $registry = new ModuleRegistry;

        $module = $this->createMockModuleWith('disabled-hooked', '1.0.0', [
            'policyHooks' => [
                'before_route' => fn () => 'should not run',
            ],
        ]);

        $registry->register($module);
        $registry->disable('disabled-hooked');

        $this->assertEmpty($registry->executePolicyHook('before_route'));
    }

    public function test_is_enabled_returns_false_for_unknown_module(): void
    {
        $registry = new ModuleRegistry;

        $this->assertFalse($registry->isEnabled('nonexistent'));
    }

    private function createMockModule(string $name, string $version): NizamModule
    {
        return $this->createMockModuleWith($name, $version);
    }

    /**
     * Create a mock module with optional overrides for specific methods.
     *
     * @param  array<string, mixed>  $overrides  Keyed by method name
     */
    private function createMockModuleWith(string $name, string $version, array $overrides = []): NizamModule
    {
        $module = $this->createMock(NizamModule::class);
        $module->method('name')->willReturn($name);
        $module->method('version')->willReturn($version);
        $module->method('description')->willReturn("Test module {$name}");

        $defaults = [
            'dependencies' => [],
            'config' => [],
            'subscribedEvents' => [],
            'dialplanContributions' => [],
            'permissions' => [],
            'migrationsPath' => null,
            'routesFile' => null,
            'policyHooks' => [],
        ];

        foreach (array_merge($defaults, $overrides) as $method => $returnValue) {
            $module->method($method)->willReturn($returnValue);
        }

        return $module;
    }
}
