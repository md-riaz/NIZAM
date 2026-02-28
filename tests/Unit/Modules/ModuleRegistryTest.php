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

        $module = $this->createMock(NizamModule::class);
        $module->method('name')->willReturn('dialer');
        $module->method('version')->willReturn('1.0.0');
        $module->method('description')->willReturn('Test module dialer');
        $module->method('subscribedEvents')->willReturn([]);
        $module->method('permissions')->willReturn([]);
        $module->method('dialplanContributions')
            ->willReturn([10 => '<action application="playback" data="tone_stream://%(200,0,440)"/>']);

        $registry->register($module);

        $contributions = $registry->collectDialplanContributions('example.com', '1001');
        $this->assertCount(1, $contributions);
        $this->assertArrayHasKey(10, $contributions);
    }

    public function test_dispatches_events_to_subscribed_modules(): void
    {
        $registry = new ModuleRegistry;

        $module = $this->createMock(NizamModule::class);
        $module->method('name')->willReturn('listener');
        $module->method('version')->willReturn('1.0.0');
        $module->method('description')->willReturn('Test module listener');
        $module->method('dialplanContributions')->willReturn([]);
        $module->method('permissions')->willReturn([]);
        $module->method('subscribedEvents')->willReturn(['call.hangup']);
        $module->expects($this->once())->method('handleEvent')
            ->with('call.hangup', ['uuid' => '123']);

        $registry->register($module);
        $registry->dispatchEvent('call.hangup', ['uuid' => '123']);
    }

    public function test_does_not_dispatch_to_non_subscribed_modules(): void
    {
        $registry = new ModuleRegistry;

        $module = $this->createMockModule('non-listener', '1.0.0');
        $module->method('subscribedEvents')->willReturn(['call.started']);
        $module->expects($this->never())->method('handleEvent');

        $registry->register($module);
        $registry->dispatchEvent('call.hangup', ['uuid' => '123']);
    }

    public function test_collects_permissions_from_modules(): void
    {
        $registry = new ModuleRegistry;

        $module1 = $this->createMock(NizamModule::class);
        $module1->method('name')->willReturn('perm-a');
        $module1->method('version')->willReturn('1.0.0');
        $module1->method('description')->willReturn('Test module perm-a');
        $module1->method('subscribedEvents')->willReturn([]);
        $module1->method('dialplanContributions')->willReturn([]);
        $module1->method('permissions')->willReturn(['recordings.view', 'recordings.delete']);

        $module2 = $this->createMock(NizamModule::class);
        $module2->method('name')->willReturn('perm-b');
        $module2->method('version')->willReturn('1.0.0');
        $module2->method('description')->willReturn('Test module perm-b');
        $module2->method('subscribedEvents')->willReturn([]);
        $module2->method('dialplanContributions')->willReturn([]);
        $module2->method('permissions')->willReturn(['fax.send', 'recordings.view']); // duplicate

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

        $module = $this->createMock(NizamModule::class);
        $module->method('name')->willReturn('crasher');
        $module->method('version')->willReturn('1.0.0');
        $module->method('description')->willReturn('Test module crasher');
        $module->method('dialplanContributions')->willReturn([]);
        $module->method('permissions')->willReturn([]);
        $module->method('subscribedEvents')->willReturn(['call.hangup']);
        $module->method('handleEvent')
            ->willThrowException(new \RuntimeException('Module error'));

        $registry->register($module);

        // Should not throw — error is logged
        $registry->dispatchEvent('call.hangup', ['uuid' => '123']);
        $this->assertTrue(true);
    }

    private function createMockModule(string $name, string $version): NizamModule
    {
        $module = $this->createMock(NizamModule::class);
        $module->method('name')->willReturn($name);
        $module->method('version')->willReturn($version);
        $module->method('description')->willReturn("Test module {$name}");
        $module->method('subscribedEvents')->willReturn([]);
        $module->method('dialplanContributions')->willReturn([]);
        $module->method('permissions')->willReturn([]);

        return $module;
    }
}
