<?php

namespace Tests\Unit\Observers;

use App\Models\Organization;
use App\Models\Team;
use App\Services\OrganizationManifestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_created_triggers_manifest_rebuild_for_team_organization_without_schedule(): void
    {
        $organization = Organization::factory()->create();

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $team = Team::create([
            'organization_id' => $organization->id,
            'name' => 'Sales',
            'strategy' => 'simultaneous',
            'timeout' => 20,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('teams', ['id' => $team->id]);
    }

    public function test_team_updated_triggers_manifest_rebuild_for_team_organization_without_schedule(): void
    {
        $organization = Organization::factory()->create();
        $team = Team::create([
            'organization_id' => $organization->id,
            'name' => 'Sales',
            'strategy' => 'simultaneous',
            'timeout' => 20,
            'is_active' => true,
        ]);

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $team->update(['name' => 'Support']);

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'name' => 'Support',
        ]);
    }

    public function test_team_deleted_triggers_manifest_rebuild_for_team_organization_without_schedule(): void
    {
        $organization = Organization::factory()->create();
        $team = Team::create([
            'organization_id' => $organization->id,
            'name' => 'Sales',
            'strategy' => 'simultaneous',
            'timeout' => 20,
            'is_active' => true,
        ]);

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $team->delete();

        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
    }
}
