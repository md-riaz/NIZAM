<?php

namespace Tests\Unit\Services;

use App\Models\Agent;
use App\Models\Queue;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelecomEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    private function createTenant(array $overrides = []): Tenant
    {
        return Tenant::create(array_merge([
            'name' => 'Test Corp',
            'domain' => 'test.example.com',
            'slug' => 'test-corp',
            'max_extensions' => 50,
        ], $overrides));
    }

    // -- Recording retention --

    public function test_tenant_can_set_recording_retention_days(): void
    {
        $tenant = $this->createTenant(['recording_retention_days' => 90]);

        $this->assertEquals(90, $tenant->recording_retention_days);
        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'recording_retention_days' => 90,
        ]);
    }

    public function test_tenant_recording_retention_defaults_to_null(): void
    {
        $tenant = $this->createTenant();

        $this->assertNull($tenant->recording_retention_days);
    }

    // -- Abuse controls --

    public function test_tenant_can_set_max_calls_per_minute(): void
    {
        $tenant = $this->createTenant(['max_calls_per_minute' => 30]);

        $this->assertEquals(30, $tenant->max_calls_per_minute);
        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'max_calls_per_minute' => 30,
        ]);
    }

    public function test_tenant_max_calls_per_minute_defaults_to_null(): void
    {
        $tenant = $this->createTenant();

        $this->assertNull($tenant->max_calls_per_minute);
    }

    // -- Queue wrapup_seconds (ACW) --

    public function test_queue_can_set_wrapup_seconds(): void
    {
        $tenant = $this->createTenant();

        $queue = Queue::create([
            'tenant_id' => $tenant->id,
            'name' => 'Support',
            'wrapup_seconds' => 30,
        ]);

        $this->assertEquals(30, $queue->wrapup_seconds);
        $this->assertDatabaseHas('queues', [
            'id' => $queue->id,
            'wrapup_seconds' => 30,
        ]);
    }

    public function test_queue_wrapup_seconds_defaults_to_zero(): void
    {
        $tenant = $this->createTenant();

        $queue = Queue::create([
            'tenant_id' => $tenant->id,
            'name' => 'Support',
        ]);

        $this->assertEquals(0, $queue->wrapup_seconds);
    }

    // -- Agent ACW pause reason --

    public function test_agent_acw_pause_transition(): void
    {
        $tenant = $this->createTenant();
        $extension = $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret',
            'directory_first_name' => 'Test',
            'directory_last_name' => 'Agent',
        ]);

        $agent = Agent::create([
            'tenant_id' => $tenant->id,
            'extension_id' => $extension->id,
            'name' => 'Agent ACW',
            'state' => Agent::STATE_AVAILABLE,
            'is_active' => true,
        ]);

        // Transition to paused with ACW reason
        $agent->transitionState(Agent::STATE_PAUSED, Agent::PAUSE_AFTER_CALL_WORK);
        $agent->refresh();

        $this->assertEquals(Agent::STATE_PAUSED, $agent->state);
        $this->assertEquals('after_call_work', $agent->pause_reason);
        $this->assertFalse($agent->isAvailable());

        // Resume after ACW
        $agent->transitionState(Agent::STATE_AVAILABLE);
        $agent->refresh();

        $this->assertEquals(Agent::STATE_AVAILABLE, $agent->state);
        $this->assertNull($agent->pause_reason);
        $this->assertTrue($agent->isAvailable());
    }

    // -- Config --

    public function test_nizam_config_has_media_section(): void
    {
        $this->assertNotNull(config('nizam.media'));
        $this->assertArrayHasKey('dtmf_type', config('nizam.media'));
        $this->assertArrayHasKey('srtp_policy', config('nizam.media'));
        $this->assertArrayHasKey('rtp_port_range_start', config('nizam.media'));
        $this->assertArrayHasKey('ext_rtp_ip', config('nizam.media'));
    }

    public function test_nizam_config_has_emergency_patterns(): void
    {
        $patterns = config('nizam.emergency.patterns');

        $this->assertIsArray($patterns);
        $this->assertContains('911', $patterns);
        $this->assertContains('112', $patterns);
        $this->assertContains('999', $patterns);
    }

    public function test_nizam_dtmf_defaults_to_rfc2833(): void
    {
        $this->assertEquals('rfc2833', config('nizam.media.dtmf_type'));
    }

    public function test_nizam_srtp_defaults_to_optional(): void
    {
        $this->assertEquals('optional', config('nizam.media.srtp_policy'));
    }
}
