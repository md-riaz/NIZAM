<?php

namespace Tests\Unit\Observers;

use App\Models\Organization;
use App\Models\RingGroup;
use App\Services\OrganizationManifestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RingGroupObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_ring_group_created_triggers_manifest_rebuild(): void
    {
        $organization = Organization::factory()->create();

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $ringGroup = RingGroup::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $this->assertDatabaseHas('ring_groups', ['id' => $ringGroup->id]);
    }

    public function test_ring_group_updated_triggers_manifest_rebuild(): void
    {
        $organization = Organization::factory()->create();
        $ringGroup = RingGroup::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $ringGroup->update(['name' => 'Updated Sales Team']);

        $this->assertDatabaseHas('ring_groups', [
            'id' => $ringGroup->id,
            'name' => 'Updated Sales Team',
        ]);
    }

    public function test_ring_group_deleted_triggers_manifest_rebuild(): void
    {
        $organization = Organization::factory()->create();
        $ringGroup = RingGroup::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $ringGroup->delete();

        $this->assertDatabaseMissing('ring_groups', ['id' => $ringGroup->id]);
    }
}
