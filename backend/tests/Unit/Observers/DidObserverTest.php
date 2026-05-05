<?php

namespace Tests\Unit\Observers;

use App\Models\Did;
use App\Models\Organization;
use App\Services\OrganizationManifestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DidObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_did_created_triggers_manifest_rebuild(): void
    {
        $organization = Organization::factory()->create();

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $did = Did::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $this->assertDatabaseHas('dids', ['id' => $did->id]);
    }

    public function test_did_updated_triggers_manifest_rebuild(): void
    {
        $organization = Organization::factory()->create();
        $did = Did::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $did->update(['description' => 'Updated DID']);

        $this->assertDatabaseHas('dids', [
            'id' => $did->id,
            'description' => 'Updated DID',
        ]);
    }

    public function test_did_deleted_triggers_manifest_rebuild(): void
    {
        $organization = Organization::factory()->create();
        $did = Did::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $did->delete();

        $this->assertDatabaseMissing('dids', ['id' => $did->id]);
    }
}
