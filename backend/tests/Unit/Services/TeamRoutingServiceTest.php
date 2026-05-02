<?php

namespace Tests\Unit\Services;

use App\Models\Agent;
use App\Models\Extension;
use App\Models\Organization;
use App\Models\Team;
use App\Models\TeamMember;
use App\Services\Team\TeamRoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamRoutingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_dial_string_uses_supplied_domain_for_extension_and_agent_members(): void
    {
        $organization = Organization::factory()->create(['domain' => 'org.example.test']);
        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'extension' => '1001',
            'is_active' => true,
        ]);
        $agentExtension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'extension' => '1002',
            'is_active' => true,
        ]);
        $agent = Agent::factory()->available()->create([
            'organization_id' => $organization->id,
            'extension_id' => $agentExtension->id,
            'is_active' => true,
        ]);
        $team = Team::create([
            'organization_id' => $organization->id,
            'name' => 'Dial Team',
            'strategy' => 'simultaneous',
            'timeout' => 20,
            'is_active' => true,
        ]);

        TeamMember::create([
            'team_id' => $team->id,
            'endpoint_type' => 'extension',
            'endpoint_id' => $extension->id,
            'priority' => 1,
            'is_active' => true,
        ]);
        TeamMember::create([
            'team_id' => $team->id,
            'endpoint_type' => 'agent',
            'endpoint_id' => $agent->id,
            'priority' => 2,
            'is_active' => true,
        ]);

        $dialString = app(TeamRoutingService::class)->buildDialString($team, 'tenant.example.test');

        $this->assertSame('user/1001@tenant.example.test,user/1002@tenant.example.test', $dialString);
    }
}
