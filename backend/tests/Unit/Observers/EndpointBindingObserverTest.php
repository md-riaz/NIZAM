<?php

namespace Tests\Unit\Observers;

use App\Models\EndpointBinding;
use App\Models\Organization;
use App\Services\OrganizationManifestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndpointBindingObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoint_binding_created_triggers_manifest_rebuild_for_binding_organization(): void
    {
        $organization = Organization::factory()->create();

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $binding = EndpointBinding::query()->create([
            'organization_id' => $organization->id,
            'type' => EndpointBinding::TYPE_PSTN_FORWARD,
            'device_uuid' => 'follow-me-test',
            'platform' => EndpointBinding::PLATFORM_UNKNOWN,
            'is_push_capable' => false,
            'is_enabled' => true,
            'rings_immediately_when_online' => false,
            'allow_late_join_after_push' => false,
            'forward_number' => '+8801712345678',
            'forward_requires_confirm' => true,
        ]);

        $this->assertDatabaseHas('endpoint_bindings', ['id' => $binding->id]);
    }

    public function test_endpoint_binding_updated_triggers_manifest_rebuild_for_binding_organization(): void
    {
        $organization = Organization::factory()->create();
        $binding = EndpointBinding::query()->create([
            'organization_id' => $organization->id,
            'type' => EndpointBinding::TYPE_PSTN_FORWARD,
            'device_uuid' => 'follow-me-test',
            'platform' => EndpointBinding::PLATFORM_UNKNOWN,
            'is_push_capable' => false,
            'is_enabled' => true,
            'rings_immediately_when_online' => false,
            'allow_late_join_after_push' => false,
            'forward_number' => '+8801712345678',
            'forward_requires_confirm' => true,
        ]);

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $binding->update(['is_enabled' => false]);
    }

    public function test_endpoint_binding_deleted_triggers_manifest_rebuild_for_binding_organization(): void
    {
        $organization = Organization::factory()->create();
        $binding = EndpointBinding::query()->create([
            'organization_id' => $organization->id,
            'type' => EndpointBinding::TYPE_PSTN_FORWARD,
            'device_uuid' => 'follow-me-test',
            'platform' => EndpointBinding::PLATFORM_UNKNOWN,
            'is_push_capable' => false,
            'is_enabled' => true,
            'rings_immediately_when_online' => false,
            'allow_late_join_after_push' => false,
            'forward_number' => '+8801712345678',
            'forward_requires_confirm' => true,
        ]);

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $binding->delete();
    }
}
