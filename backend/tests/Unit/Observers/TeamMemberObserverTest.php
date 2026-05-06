<?php

namespace Tests\Unit\Observers;

use App\Models\Extension;
use App\Models\Organization;
use App\Models\Team;
use App\Models\TeamMember;
use App\Services\OrganizationManifestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamMemberObserverTest extends TestCase
{
    use RefreshDatabase;

    protected function createTeamMemberFixture(): array
    {
        $organization = Organization::factory()->create();
        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $team = Team::create([
            'organization_id' => $organization->id,
            'name' => 'Sales',
            'strategy' => 'simultaneous',
            'timeout' => 20,
            'is_active' => true,
        ]);

        return [$organization, $extension, $team];
    }

    public function test_team_member_created_triggers_manifest_rebuild_for_team_organization(): void
    {
        [$organization, $extension, $team] = $this->createTeamMemberFixture();

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $member = TeamMember::create([
            'team_id' => $team->id,
            'endpoint_type' => Extension::class,
            'endpoint_id' => $extension->id,
            'priority' => 100,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('team_members', ['id' => $member->id]);
    }

    public function test_team_member_updated_triggers_manifest_rebuild_for_team_organization(): void
    {
        [$organization, $extension, $team] = $this->createTeamMemberFixture();
        $member = TeamMember::create([
            'team_id' => $team->id,
            'endpoint_type' => Extension::class,
            'endpoint_id' => $extension->id,
            'priority' => 100,
            'is_active' => true,
        ]);

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $member->update(['priority' => 50]);

        $this->assertDatabaseHas('team_members', [
            'id' => $member->id,
            'priority' => 50,
        ]);
    }

    public function test_team_member_deleted_triggers_manifest_rebuild_for_team_organization(): void
    {
        [$organization, $extension, $team] = $this->createTeamMemberFixture();
        $member = TeamMember::create([
            'team_id' => $team->id,
            'endpoint_type' => Extension::class,
            'endpoint_id' => $extension->id,
            'priority' => 100,
            'is_active' => true,
        ]);

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $member->delete();

        $this->assertDatabaseMissing('team_members', ['id' => $member->id]);
    }
}
