<?php

namespace Tests\Unit\Services;

use App\Models\EndpointBinding;
use App\Models\Extension;
use App\Models\Organization;
use App\Services\ExtensionFeatureService;
use App\Services\OrganizationManifestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ExtensionFeatureServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);
    }

    public function test_it_can_enable_follow_me_for_an_extension(): void
    {
        $organization = Organization::factory()->create();
        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $service = app(ExtensionFeatureService::class);

        $updated = $service->updateFeatures($extension, [
            'follow_me_enabled' => true,
            'follow_me_destination' => '+8801712345678',
        ]);

        $this->assertTrue($updated->follow_me_enabled);
        $this->assertSame('+8801712345678', $updated->follow_me_destination);
        $this->assertFalse($updated->dnd_enabled);
    }

    public function test_it_preserves_existing_follow_me_state_on_partial_dnd_update(): void
    {
        $organization = Organization::factory()->create();
        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'follow_me_enabled' => true,
            'follow_me_destination' => '+8801712345678',
            'dnd_enabled' => false,
        ]);

        $service = app(ExtensionFeatureService::class);

        $updated = $service->updateFeatures($extension, [
            'dnd_enabled' => false,
        ]);

        $this->assertTrue($updated->follow_me_enabled);
        $this->assertSame('+8801712345678', $updated->follow_me_destination);
        $this->assertFalse($updated->dnd_enabled);
    }

    public function test_it_requires_destination_when_enabling_follow_me(): void
    {
        $organization = Organization::factory()->create();
        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $service = app(ExtensionFeatureService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('follow_me_destination is required when follow_me_enabled is true.');

        $service->updateFeatures($extension, [
            'follow_me_enabled' => true,
        ]);
    }

    public function test_dnd_disables_follow_me_to_avoid_conflicting_state(): void
    {
        $organization = Organization::factory()->create();
        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'follow_me_enabled' => true,
            'follow_me_destination' => '+8801712345678',
            'dnd_enabled' => false,
        ]);

        $service = app(ExtensionFeatureService::class);

        $updated = $service->updateFeatures($extension, [
            'dnd_enabled' => true,
        ]);

        $this->assertTrue($updated->dnd_enabled);
        $this->assertFalse($updated->follow_me_enabled);
        $this->assertSame('+8801712345678', $updated->follow_me_destination);
    }

    public function test_enabling_follow_me_rebuilds_manifest_after_binding_sync(): void
    {
        $organization = Organization::factory()->create([
            'domain' => 'test.example.com',
        ]);
        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'follow_me_enabled' => false,
            'follow_me_destination' => null,
            'dnd_enabled' => false,
        ]);

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')
            ->once()
            ->withArgs(function (Organization $rebuiltOrganization) use ($organization, $extension): bool {
                $this->assertTrue($rebuiltOrganization->is($organization));
                $this->assertDatabaseHas('endpoint_bindings', [
                    'organization_id' => $organization->id,
                    'extension_id' => $extension->id,
                    'type' => EndpointBinding::TYPE_PSTN_FORWARD,
                    'is_enabled' => true,
                    'forward_number' => '+8801712345678',
                ]);

                return true;
            });

        $service = app(ExtensionFeatureService::class);

        $service->updateFeatures($extension, [
            'follow_me_enabled' => true,
            'follow_me_destination' => '+8801712345678',
        ]);
    }
}
