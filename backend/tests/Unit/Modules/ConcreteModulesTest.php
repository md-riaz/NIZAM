<?php

namespace Tests\Unit\Modules;

use Modules\PbxAnalytics\PbxAnalyticsModule;
use Modules\PbxAutomation\PbxAutomationModule;
use Modules\PbxContactCenter\PbxContactCenterModule;
use Modules\PbxProvisioning\PbxProvisioningModule;
use Modules\PbxRouting\PbxRoutingModule;
use Tests\TestCase;

class ConcreteModulesTest extends TestCase
{
    public function test_pbx_routing_module_manifest(): void
    {
        $module = new PbxRoutingModule;

        $this->assertEquals('pbx-routing', $module->name());
        $this->assertEquals('1.0.0', $module->version());
        $this->assertNotEmpty($module->description());
        $this->assertEmpty($module->dependencies());
        $this->assertNotEmpty($module->permissions());
        $this->assertNotEmpty($module->subscribedEvents());
        $this->assertNotNull($module->routesFile());
        $this->assertFileExists($module->routesFile());
    }

    public function test_pbx_contact_center_module_manifest(): void
    {
        $module = new PbxContactCenterModule;

        $this->assertEquals('pbx-contact-center', $module->name());
        $this->assertEquals('1.0.0', $module->version());
        $this->assertNotEmpty($module->permissions());
        $this->assertContains('queues.view', $module->permissions());
        $this->assertContains('agents.manage', $module->permissions());
        $this->assertNotNull($module->routesFile());
        $this->assertFileExists($module->routesFile());
    }

    public function test_pbx_automation_module_manifest(): void
    {
        $module = new PbxAutomationModule;

        $this->assertEquals('pbx-automation', $module->name());
        $this->assertEquals('1.0.0', $module->version());
        $this->assertContains('webhooks.view', $module->permissions());
        $this->assertContains('calls.control', $module->permissions());
        $this->assertNotNull($module->routesFile());
        $this->assertFileExists($module->routesFile());
    }

    public function test_pbx_analytics_module_manifest(): void
    {
        $module = new PbxAnalyticsModule;

        $this->assertEquals('pbx-analytics', $module->name());
        $this->assertEquals('1.0.0', $module->version());
        $this->assertContains('recordings.view', $module->permissions());
        $this->assertNotNull($module->routesFile());
        $this->assertFileExists($module->routesFile());
    }

    public function test_pbx_provisioning_module_manifest(): void
    {
        $module = new PbxProvisioningModule;

        $this->assertEquals('pbx-provisioning', $module->name());
        $this->assertEquals('1.0.0', $module->version());
        $this->assertContains('device-profiles.view', $module->permissions());
        $this->assertNotNull($module->routesFile());
        $this->assertFileExists($module->routesFile());
    }

    public function test_all_modules_have_unique_names(): void
    {
        $modules = [
            new PbxRoutingModule,
            new PbxContactCenterModule,
            new PbxAutomationModule,
            new PbxAnalyticsModule,
            new PbxProvisioningModule,
        ];

        $names = array_map(fn ($m) => $m->name(), $modules);
        $this->assertCount(count($modules), array_unique($names));
    }

    public function test_base_module_provides_defaults(): void
    {
        $module = new PbxRoutingModule;

        // BaseModule defaults
        $this->assertIsArray($module->config());
        $this->assertIsArray($module->policyHooks());
        $this->assertNull($module->migrationsPath());
    }

    public function test_base_module_manifest_method(): void
    {
        $module = new PbxRoutingModule;
        $manifest = $module->manifest();

        $this->assertEquals('pbx-routing', $manifest['name']);
        $this->assertEquals('1.0.0', $manifest['version']);
        $this->assertArrayHasKey('description', $manifest);
        $this->assertArrayHasKey('dependencies', $manifest);
    }
}
