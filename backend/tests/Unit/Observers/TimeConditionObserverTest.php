<?php

namespace Tests\Unit\Observers;

use App\Models\Organization;
use App\Models\TimeCondition;
use App\Services\OrganizationManifestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeConditionObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_time_condition_created_triggers_manifest_rebuild(): void
    {
        $organization = Organization::factory()->create();

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $timeCondition = TimeCondition::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $this->assertDatabaseHas('time_conditions', ['id' => $timeCondition->id]);
    }

    public function test_time_condition_updated_triggers_manifest_rebuild(): void
    {
        $organization = Organization::factory()->create();
        $timeCondition = TimeCondition::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $timeCondition->update(['name' => 'Updated condition']);

        $this->assertDatabaseHas('time_conditions', [
            'id' => $timeCondition->id,
            'name' => 'Updated condition',
        ]);
    }

    public function test_time_condition_deleted_triggers_manifest_rebuild(): void
    {
        $organization = Organization::factory()->create();
        $timeCondition = TimeCondition::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $timeCondition->delete();

        $this->assertDatabaseMissing('time_conditions', ['id' => $timeCondition->id]);
    }
}
