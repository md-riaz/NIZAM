<?php

namespace Tests\Unit\Modules;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\PbxMediaPolicy\PbxMediaPolicyModule;
use Tests\TestCase;

class PbxMediaPolicyModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_manifest(): void
    {
        $module = new PbxMediaPolicyModule;

        $this->assertEquals('pbx-media-policy', $module->name());
        $this->assertEquals('1.0.0', $module->version());
        $this->assertNotEmpty($module->description());
        $this->assertEmpty($module->dependencies());
        $this->assertNotEmpty($module->permissions());
        $this->assertNotNull($module->routesFile());
        $this->assertFileExists($module->routesFile());
    }

    public function test_module_permissions(): void
    {
        $module = new PbxMediaPolicyModule;

        $this->assertContains('gateways.view', $module->permissions());
        $this->assertContains('gateways.manage', $module->permissions());
        $this->assertContains('codec-metrics.view', $module->permissions());
    }

    public function test_module_subscribed_events(): void
    {
        $module = new PbxMediaPolicyModule;

        $this->assertContains('call.created', $module->subscribedEvents());
        $this->assertContains('call.hangup', $module->subscribedEvents());
    }

    public function test_policy_hooks_returns_before_bridge_hook(): void
    {
        $module = new PbxMediaPolicyModule;
        $hooks = $module->policyHooks();

        $this->assertArrayHasKey('before.bridge', $hooks);
        $this->assertIsCallable($hooks['before.bridge']);
    }

    public function test_before_bridge_hook_returns_defaults_without_context(): void
    {
        $module = new PbxMediaPolicyModule;
        $hooks = $module->policyHooks();
        $result = ($hooks['before.bridge'])([]);

        $this->assertTrue($result['allow_transcoding']);
        $this->assertNull($result['codec_string']);
    }

    public function test_dialplan_contributions_returns_empty_for_unknown_domain(): void
    {
        $module = new PbxMediaPolicyModule;
        $contributions = $module->dialplanContributions('unknown.example.com', '1001');

        $this->assertEmpty($contributions);
    }
}
