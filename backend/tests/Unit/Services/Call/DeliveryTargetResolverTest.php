<?php

namespace Tests\Unit\Services\Call;

use App\Models\Agent;
use App\Models\CallSession;
use App\Models\Did;
use App\Models\EndpointBinding;
use App\Models\Extension;
use App\Models\Flow;
use App\Models\FlowEdge;
use App\Models\FlowNode;
use App\Models\FlowVersion;
use App\Models\Queue;
use App\Models\Schedule;
use App\Models\ScheduleRule;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Organization;
use App\Models\TimeCondition;
use App\Services\Call\DeliveryTargetResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeliveryTargetResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);
    }

    public function test_resolves_extension_target_from_call_session_metadata(): void
    {
        $organization = Organization::factory()->create();
        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
        ]);
        $callSession = CallSession::factory()->create([
            'organization_id' => $organization->id,
            'variables' => [
                'nizam_delivery_target_type' => 'extension',
                'nizam_delivery_target_id' => $extension->id,
            ],
        ]);

        $resolved = app(DeliveryTargetResolver::class)->resolve($callSession);

        $this->assertCount(1, $resolved->targets);
        $this->assertSame('extension', $resolved->targets[0]->type);
        $this->assertSame($extension->id, $resolved->targets[0]->id);
        $this->assertSame('extension', $resolved->metadata['final_target_type']);
    }

    public function test_resolves_team_members_into_targets(): void
    {
        $organization = Organization::factory()->create();
        $first = Extension::factory()->create(['organization_id' => $organization->id, 'is_active' => true]);
        $secondExtension = Extension::factory()->create(['organization_id' => $organization->id, 'is_active' => true]);
        $secondAgent = Agent::factory()->available()->create([
            'organization_id' => $organization->id,
            'extension_id' => $secondExtension->id,
            'is_active' => true,
        ]);
        $team = Team::create([
            'organization_id' => $organization->id,
            'name' => 'Resolver Team',
            'strategy' => 'simultaneous',
            'timeout' => 20,
            'is_active' => true,
        ]);
        TeamMember::create([
            'team_id' => $team->id,
            'endpoint_type' => 'extension',
            'endpoint_id' => $first->id,
            'priority' => 1,
            'is_active' => true,
        ]);
        TeamMember::create([
            'team_id' => $team->id,
            'endpoint_type' => 'agent',
            'endpoint_id' => $secondAgent->id,
            'priority' => 2,
            'is_active' => true,
        ]);
        $callSession = CallSession::factory()->create([
            'organization_id' => $organization->id,
            'variables' => [
                'nizam_delivery_target_type' => 'team',
                'nizam_delivery_target_id' => $team->id,
            ],
        ]);

        $resolved = app(DeliveryTargetResolver::class)->resolve($callSession);

        $this->assertCount(2, $resolved->targets);
        $this->assertSame(['extension', 'agent'], array_map(fn ($target) => $target->type, $resolved->targets));
        $this->assertSame([$first->id, $secondAgent->id], array_map(fn ($target) => $target->id, $resolved->targets));
        $this->assertSame('team', $resolved->metadata['resolved_from']);
        $this->assertSame('team', $resolved->targets[0]->sourcePath[1]['type']);
        $this->assertSame('extension', $resolved->targets[0]->sourcePath[1]['member_endpoint_type']);
        $this->assertSame('agent', $resolved->targets[1]->sourcePath[1]['member_endpoint_type']);
    }

    public function test_resolves_queue_ring_all_strategy_into_eligible_agents(): void
    {
        $organization = Organization::factory()->create();
        $firstExtension = Extension::factory()->create(['organization_id' => $organization->id]);
        $secondExtension = Extension::factory()->create(['organization_id' => $organization->id]);
        $firstAgent = Agent::factory()->available()->create([
            'organization_id' => $organization->id,
            'extension_id' => $firstExtension->id,
            'is_active' => true,
        ]);
        $secondAgent = Agent::factory()->available()->create([
            'organization_id' => $organization->id,
            'extension_id' => $secondExtension->id,
            'is_active' => true,
        ]);
        $queue = Queue::factory()->ringAll()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
        ]);
        $queue->members()->attach($firstAgent->id, ['id' => Str::uuid(), 'priority' => 1]);
        $queue->members()->attach($secondAgent->id, ['id' => Str::uuid(), 'priority' => 2]);
        $callSession = CallSession::factory()->create([
            'organization_id' => $organization->id,
            'variables' => [
                'nizam_delivery_target_type' => 'queue',
                'nizam_delivery_target_id' => $queue->id,
            ],
        ]);

        $resolved = app(DeliveryTargetResolver::class)->resolve($callSession);

        $this->assertCount(2, $resolved->targets);
        $this->assertSame([$firstAgent->id, $secondAgent->id], array_map(fn ($target) => $target->id, $resolved->targets));
        $this->assertSame('queue', $resolved->metadata['resolved_from']);
        $this->assertSame($queue->strategy, $resolved->metadata['strategy']);
    }

    public function test_did_resolution_bypasses_non_human_destinations(): void
    {
        $organization = Organization::factory()->create();
        $extension = Extension::factory()->create(['organization_id' => $organization->id]);
        $did = Did::factory()->create([
            'organization_id' => $organization->id,
            'destination_type' => 'voicemail',
            'destination_id' => $extension->id,
            'is_active' => true,
        ]);
        $callSession = CallSession::factory()->create([
            'organization_id' => $organization->id,
            'variables' => [
                'nizam_delivery_target_type' => 'did',
                'nizam_delivery_target_id' => $did->id,
            ],
        ]);

        $resolved = app(DeliveryTargetResolver::class)->resolve($callSession);

        $this->assertTrue($resolved->isEmpty());
        $this->assertSame('non_human_destination', $resolved->metadata['bypass_reason']);
        $this->assertSame('voicemail', $resolved->metadata['destination_type']);
    }

    public function test_time_condition_resolves_the_matching_human_branch(): void
    {
        $organization = Organization::factory()->create();
        $matchExtension = Extension::factory()->create(['organization_id' => $organization->id]);
        $noMatchExtension = Extension::factory()->create(['organization_id' => $organization->id]);
        $timeCondition = TimeCondition::factory()->create([
            'organization_id' => $organization->id,
            'conditions' => [[
                'wday' => 'mon-fri',
                'time_from' => '09:00',
                'time_to' => '17:00',
            ]],
            'match_destination_type' => 'extension',
            'match_destination_id' => $matchExtension->id,
            'no_match_destination_type' => 'extension',
            'no_match_destination_id' => $noMatchExtension->id,
            'is_active' => true,
        ]);
        $callSession = CallSession::factory()->create([
            'organization_id' => $organization->id,
            'variables' => [
                'nizam_delivery_target_type' => 'time_condition',
                'nizam_delivery_target_id' => $timeCondition->id,
            ],
        ]);

        $resolved = app(DeliveryTargetResolver::class)->resolve(
            $callSession,
            new \DateTimeImmutable('2026-05-18T10:00:00+00:00')
        );

        $this->assertCount(1, $resolved->targets);
        $this->assertSame($matchExtension->id, $resolved->targets[0]->id);
        $this->assertSame('time_condition', $resolved->targets[0]->sourcePath[1]['type']);
        $this->assertSame('match', $resolved->targets[0]->sourcePath[1]['branch']);
    }

    public function test_flow_resolution_follows_open_schedule_branch_to_human_team_targets(): void
    {
        $organization = Organization::factory()->create();
        $team = Team::create([
            'organization_id' => $organization->id,
            'name' => 'Open Team',
            'strategy' => 'simultaneous',
            'timeout' => 20,
            'is_active' => true,
        ]);
        $extension = Extension::factory()->create(['organization_id' => $organization->id, 'is_active' => true]);
        TeamMember::create([
            'team_id' => $team->id,
            'endpoint_type' => 'extension',
            'endpoint_id' => $extension->id,
            'priority' => 1,
            'is_active' => true,
        ]);

        $flow = Flow::factory()->create(['organization_id' => $organization->id]);
        $flowVersion = FlowVersion::factory()->create([
            'flow_id' => $flow->id,
            'is_published' => true,
        ]);
        $flow->update(['active_version_id' => $flowVersion->id]);

        $schedule = Schedule::factory()->create([
            'organization_id' => $organization->id,
            'timezone' => 'UTC',
            'is_active' => true,
        ]);
        ScheduleRule::factory()->create([
            'schedule_id' => $schedule->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        $startNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'start',
            'config_json' => [],
        ]);
        $scheduleNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'schedule_check',
            'config_json' => ['schedule_id' => $schedule->id],
        ]);
        $ringTeamNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'ring_team',
            'config_json' => ['team_id' => $team->id],
        ]);
        $voicemailNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'voicemail',
            'config_json' => ['mailbox' => '1000'],
        ]);

        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $startNode->id,
            'target_node_id' => $scheduleNode->id,
            'condition' => 'next',
        ]);
        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $scheduleNode->id,
            'target_node_id' => $ringTeamNode->id,
            'condition' => 'open',
        ]);
        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $scheduleNode->id,
            'target_node_id' => $voicemailNode->id,
            'condition' => 'closed',
        ]);

        $callSession = CallSession::factory()->create([
            'organization_id' => $organization->id,
            'variables' => [
                'nizam_delivery_target_type' => 'flow',
                'nizam_delivery_target_id' => $flow->id,
            ],
        ]);

        $resolved = app(DeliveryTargetResolver::class)->resolve(
            $callSession,
            new \DateTimeImmutable('2026-05-18T10:00:00+00:00')
        );

        $this->assertCount(1, $resolved->targets);
        $this->assertSame('extension', $resolved->targets[0]->type);
        $this->assertSame($extension->id, $resolved->targets[0]->id);
        $this->assertSame('flow', $resolved->targets[0]->sourcePath[1]['type']);
        $this->assertSame('schedule_check', $resolved->targets[0]->sourcePath[1]['node_type']);
        $this->assertSame('open', $resolved->targets[0]->sourcePath[1]['branch']);
        $this->assertSame('flow', $resolved->targets[0]->sourcePath[2]['type']);
        $this->assertSame('ring_team', $resolved->targets[0]->sourcePath[2]['node_type']);
        $this->assertSame('team', $resolved->targets[0]->sourcePath[3]['type']);
        $this->assertSame($team->id, $resolved->targets[0]->sourcePath[3]['id']);
        $this->assertSame('extension', $resolved->targets[0]->sourcePath[3]['member_endpoint_type']);
        $this->assertSame($extension->id, $resolved->targets[0]->sourcePath[3]['member_endpoint_id']);
    }

    public function test_team_without_active_members_returns_empty_set(): void
    {
        $organization = Organization::factory()->create();
        $inactiveMember = Extension::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => false,
        ]);
        $team = Team::create([
            'organization_id' => $organization->id,
            'name' => 'Empty Team',
            'strategy' => 'simultaneous',
            'timeout' => 20,
            'is_active' => true,
        ]);
        TeamMember::create([
            'team_id' => $team->id,
            'endpoint_type' => 'extension',
            'endpoint_id' => $inactiveMember->id,
            'priority' => 1,
            'is_active' => true,
        ]);
        $callSession = CallSession::factory()->create([
            'organization_id' => $organization->id,
            'variables' => [
                'nizam_delivery_target_type' => 'team',
                'nizam_delivery_target_id' => $team->id,
            ],
        ]);

        $resolved = app(DeliveryTargetResolver::class)->resolve($callSession);

        $this->assertTrue($resolved->isEmpty());
        $this->assertSame('team_without_human_members', $resolved->metadata['bypass_reason']);
        $this->assertSame($team->id, $resolved->metadata['team_id']);
    }

    public function test_extension_resolution_keeps_follow_me_as_extension_target_for_orchestration(): void
    {
        $organization = Organization::factory()->create();
        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'extension' => '4001',
            'follow_me_enabled' => true,
            'follow_me_destination' => '+15551234567',
            'is_active' => true,
        ]);
        EndpointBinding::factory()->forExtension($extension)->pstnForward()->create([
            'organization_id' => $organization->id,
            'forward_number' => '+15551234567',
            'forward_requires_confirm' => true,
        ]);
        $callSession = CallSession::factory()->create([
            'organization_id' => $organization->id,
            'variables' => [
                'nizam_delivery_target_type' => 'extension',
                'nizam_delivery_target_id' => $extension->id,
            ],
        ]);

        $resolved = app(DeliveryTargetResolver::class)->resolve($callSession);

        $this->assertCount(1, $resolved->targets);
        $this->assertSame('extension', $resolved->targets[0]->type);
        $this->assertSame($extension->id, $resolved->targets[0]->id);
        $this->assertSame('extension', $resolved->metadata['final_target_type']);
        $this->assertArrayNotHasKey('bypass_reason', $resolved->metadata);
    }
}
