<?php

namespace Tests\Unit\Services;

use App\Models\Extension;
use App\Models\Organization;
use App\Services\ExtensionFeatureService;
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
        $this->assertNull($updated->follow_me_destination);
    }
}
