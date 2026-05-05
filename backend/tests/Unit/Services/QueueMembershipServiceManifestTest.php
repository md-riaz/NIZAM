<?php

namespace Tests\Unit\Services;

use App\Models\Agent;
use App\Models\Organization;
use App\Models\Queue;
use App\Services\OrganizationManifestBuilder;
use App\Services\QueueMembershipService;
use App\Services\WallboardProjectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueMembershipServiceManifestTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_member_rebuilds_manifest_for_queue_organization(): void
    {
        $organization = Organization::factory()->create();
        $queue = Queue::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $agent = Agent::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $wallboard = $this->mock(WallboardProjectionService::class);
        $wallboard->shouldReceive('refreshAgentProjection')->once()->withArgs(fn ($arg) => $arg->is($agent));
        $wallboard->shouldReceive('refreshQueueProjection')->once()->withArgs(fn ($arg) => $arg->is($queue));

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $service = app(QueueMembershipService::class);
        $service->addMember($organization, $queue, $agent->id, 0);

        $this->assertDatabaseHas('queue_members', [
            'queue_id' => $queue->id,
            'agent_id' => $agent->id,
        ]);
    }

    public function test_remove_member_rebuilds_manifest_for_queue_organization(): void
    {
        $organization = Organization::factory()->create();
        $queue = Queue::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $agent = Agent::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $queue->members()->attach($agent->id, ['id' => (string) \Illuminate\Support\Str::uuid(), 'priority' => 0]);

        $wallboard = $this->mock(WallboardProjectionService::class);
        $wallboard->shouldReceive('refreshQueueProjection')->once()->withArgs(fn ($arg) => $arg->is($queue));

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $service = app(QueueMembershipService::class);
        $service->removeMember($queue, $agent);

        $this->assertDatabaseMissing('queue_members', [
            'queue_id' => $queue->id,
            'agent_id' => $agent->id,
        ]);
    }
}
