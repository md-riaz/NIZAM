<?php

namespace Tests\Unit\Models;

use App\Models\Agent;
use App\Models\Queue;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentTest extends TestCase
{
    use RefreshDatabase;

    private function createTenantWithExtension(): array
    {
        $tenant = Tenant::create([
            'name' => 'Test Corp',
            'domain' => 'test.example.com',
            'slug' => 'test-corp',
            'max_extensions' => 50,
        ]);

        $extension = $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
        ]);

        return [$tenant, $extension];
    }

    public function test_can_be_created_with_valid_attributes(): void
    {
        [$tenant, $extension] = $this->createTenantWithExtension();

        $agent = Agent::create([
            'tenant_id' => $tenant->id,
            'extension_id' => $extension->id,
            'name' => 'Agent Smith',
            'role' => Agent::ROLE_AGENT,
            'state' => Agent::STATE_OFFLINE,
        ]);

        $this->assertDatabaseHas('agents', [
            'id' => $agent->id,
            'name' => 'Agent Smith',
            'role' => 'agent',
            'state' => 'offline',
        ]);
    }

    public function test_belongs_to_tenant(): void
    {
        [$tenant, $extension] = $this->createTenantWithExtension();

        $agent = Agent::create([
            'tenant_id' => $tenant->id,
            'extension_id' => $extension->id,
            'name' => 'Agent Smith',
        ]);

        $this->assertEquals($tenant->id, $agent->tenant->id);
    }

    public function test_belongs_to_extension(): void
    {
        [$tenant, $extension] = $this->createTenantWithExtension();

        $agent = Agent::create([
            'tenant_id' => $tenant->id,
            'extension_id' => $extension->id,
            'name' => 'Agent Smith',
        ]);

        $this->assertEquals($extension->id, $agent->extension->id);
    }

    public function test_state_transition(): void
    {
        [$tenant, $extension] = $this->createTenantWithExtension();

        $agent = Agent::create([
            'tenant_id' => $tenant->id,
            'extension_id' => $extension->id,
            'name' => 'Agent Smith',
            'state' => Agent::STATE_OFFLINE,
        ]);

        $agent->transitionState(Agent::STATE_AVAILABLE);
        $agent->refresh();

        $this->assertEquals(Agent::STATE_AVAILABLE, $agent->state);
        $this->assertNull($agent->pause_reason);
        $this->assertNotNull($agent->state_changed_at);
    }

    public function test_pause_state_with_reason(): void
    {
        [$tenant, $extension] = $this->createTenantWithExtension();

        $agent = Agent::create([
            'tenant_id' => $tenant->id,
            'extension_id' => $extension->id,
            'name' => 'Agent Smith',
            'state' => Agent::STATE_AVAILABLE,
        ]);

        $agent->transitionState(Agent::STATE_PAUSED, Agent::PAUSE_LUNCH);
        $agent->refresh();

        $this->assertEquals(Agent::STATE_PAUSED, $agent->state);
        $this->assertEquals(Agent::PAUSE_LUNCH, $agent->pause_reason);
    }

    public function test_pause_reason_cleared_on_non_pause_state(): void
    {
        [$tenant, $extension] = $this->createTenantWithExtension();

        $agent = Agent::create([
            'tenant_id' => $tenant->id,
            'extension_id' => $extension->id,
            'name' => 'Agent Smith',
            'state' => Agent::STATE_PAUSED,
            'pause_reason' => Agent::PAUSE_LUNCH,
        ]);

        $agent->transitionState(Agent::STATE_AVAILABLE);
        $agent->refresh();

        $this->assertEquals(Agent::STATE_AVAILABLE, $agent->state);
        $this->assertNull($agent->pause_reason);
    }

    public function test_is_available(): void
    {
        [$tenant, $extension] = $this->createTenantWithExtension();

        $agent = Agent::create([
            'tenant_id' => $tenant->id,
            'extension_id' => $extension->id,
            'name' => 'Agent Smith',
            'state' => Agent::STATE_AVAILABLE,
            'is_active' => true,
        ]);

        $this->assertTrue($agent->isAvailable());

        $agent->transitionState(Agent::STATE_BUSY);
        $this->assertFalse($agent->isAvailable());
    }

    public function test_inactive_agent_is_not_available(): void
    {
        [$tenant, $extension] = $this->createTenantWithExtension();

        $agent = Agent::create([
            'tenant_id' => $tenant->id,
            'extension_id' => $extension->id,
            'name' => 'Agent Smith',
            'state' => Agent::STATE_AVAILABLE,
            'is_active' => false,
        ]);

        $this->assertFalse($agent->isAvailable());
    }

    public function test_has_valid_constants(): void
    {
        $this->assertContains('available', Agent::VALID_STATES);
        $this->assertContains('busy', Agent::VALID_STATES);
        $this->assertContains('ringing', Agent::VALID_STATES);
        $this->assertContains('paused', Agent::VALID_STATES);
        $this->assertContains('offline', Agent::VALID_STATES);

        $this->assertContains('agent', Agent::VALID_ROLES);
        $this->assertContains('supervisor', Agent::VALID_ROLES);

        $this->assertContains('break', Agent::DEFAULT_PAUSE_REASONS);
        $this->assertContains('lunch', Agent::DEFAULT_PAUSE_REASONS);
        $this->assertContains('after_call_work', Agent::DEFAULT_PAUSE_REASONS);
        $this->assertContains('custom', Agent::DEFAULT_PAUSE_REASONS);
    }

    public function test_can_belong_to_queues(): void
    {
        [$tenant, $extension] = $this->createTenantWithExtension();

        $agent = Agent::create([
            'tenant_id' => $tenant->id,
            'extension_id' => $extension->id,
            'name' => 'Agent Smith',
        ]);

        $queue = Queue::create([
            'tenant_id' => $tenant->id,
            'name' => 'Support Queue',
        ]);

        $queue->members()->attach($agent->id, [
            'id' => \Illuminate\Support\Str::uuid(),
            'priority' => 1,
        ]);

        $this->assertCount(1, $agent->fresh()->queues);
        $this->assertEquals('Support Queue', $agent->fresh()->queues->first()->name);
    }
}
