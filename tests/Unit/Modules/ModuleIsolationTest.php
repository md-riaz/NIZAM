<?php

namespace Tests\Unit\Modules;

use App\Modules\ModuleRegistry;
use App\Modules\PbxContactCenterModule;
use App\Modules\PbxRoutingModule;
use Tests\TestCase;

class ModuleIsolationTest extends TestCase
{
    public function test_disabling_contact_center_does_not_affect_routing(): void
    {
        $registry = new ModuleRegistry;

        $routing = new PbxRoutingModule;
        $contactCenter = new PbxContactCenterModule;

        $registry->register($routing);
        $registry->register($contactCenter);

        // Disable contact center
        $registry->disable('pbx-contact-center');

        // Routing should still work
        $this->assertTrue($registry->isEnabled('pbx-routing'));
        $this->assertFalse($registry->isEnabled('pbx-contact-center'));

        // Only routing permissions should be collected
        $permissions = $registry->collectPermissions();
        $this->assertContains('dids.view', $permissions);
        $this->assertNotContains('queues.view', $permissions);
    }

    public function test_disabling_all_modules_leaves_core_functional(): void
    {
        $registry = new ModuleRegistry;

        $routing = new PbxRoutingModule;
        $contactCenter = new PbxContactCenterModule;

        $registry->register($routing);
        $registry->register($contactCenter);

        // Disable all
        $registry->disable('pbx-routing');
        $registry->disable('pbx-contact-center');

        // Registry still works, just has no enabled modules
        $this->assertCount(2, $registry->all());
        $this->assertEmpty($registry->enabled());
        $this->assertEmpty($registry->collectPermissions());
        $this->assertEmpty($registry->collectRouteFiles());
    }

    public function test_module_registry_manifests_show_enabled_state(): void
    {
        $registry = new ModuleRegistry;

        $routing = new PbxRoutingModule;
        $contactCenter = new PbxContactCenterModule;

        $registry->register($routing);
        $registry->register($contactCenter);

        $registry->disable('pbx-contact-center');

        $manifests = $registry->manifests();

        $this->assertTrue($manifests['pbx-routing']['enabled']);
        $this->assertFalse($manifests['pbx-contact-center']['enabled']);
    }

    public function test_disabled_module_can_be_re_enabled(): void
    {
        $registry = new ModuleRegistry;

        $routing = new PbxRoutingModule;
        $registry->register($routing);

        $registry->disable('pbx-routing');
        $this->assertEmpty($registry->collectPermissions());

        $registry->enable('pbx-routing');
        $this->assertNotEmpty($registry->collectPermissions());
    }
}
