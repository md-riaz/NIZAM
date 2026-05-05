<?php

namespace Tests\Unit\Observers;

use App\Models\Ivr;
use App\Models\Organization;
use App\Services\OrganizationManifestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IvrObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_ivr_created_triggers_manifest_rebuild(): void
    {
        $organization = Organization::factory()->create();

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $ivr = Ivr::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $this->assertDatabaseHas('ivrs', ['id' => $ivr->id]);
    }

    public function test_ivr_updated_triggers_manifest_rebuild(): void
    {
        $organization = Organization::factory()->create();
        $ivr = Ivr::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $ivr->update(['name' => 'Updated IVR']);

        $this->assertDatabaseHas('ivrs', [
            'id' => $ivr->id,
            'name' => 'Updated IVR',
        ]);
    }

    public function test_ivr_deleted_triggers_manifest_rebuild(): void
    {
        $organization = Organization::factory()->create();
        $ivr = Ivr::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $ivr->delete();

        $this->assertDatabaseMissing('ivrs', ['id' => $ivr->id]);
    }
}
