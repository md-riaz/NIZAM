<?php

namespace Tests\Feature\Api;

use App\Models\DeviceProfile;
use App\Models\Extension;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A desk phone assigned from the Devices page must survive unrelated edits to
 * the extension it is assigned to.
 *
 * ExtensionController::syncOwnedDevice() previously treated an absent
 * device_profile_id as an instruction to clear the link, so saving a user-owned
 * extension for any reason — voicemail PIN, caller-ID allow-list, is_active —
 * silently unlinked the hardware and it stopped provisioning.
 */
class ExtensionDeviceLinkPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(Organization $organization): User
    {
        return User::factory()->create(['role' => 'admin', 'organization_id' => $organization->id]);
    }

    /**
     * The update endpoint is a full replace — extension, password, first_name
     * and last_name are all required — so a realistic "unrelated edit" resends
     * every field, which is exactly why the reverse-link bug was reachable.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function fullPayload(Extension $extension, array $overrides = []): array
    {
        return array_merge([
            'extension' => $extension->extension,
            'password' => 'sup3r-secret-pass',
            'first_name' => 'Ayesha',
            'last_name' => 'Rahman',
        ], $overrides);
    }

    public function test_device_link_survives_an_unrelated_extension_update(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create(['role' => 'agent', 'organization_id' => $organization->id]);

        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'device_profile_id' => null,
            'is_active' => true,
        ]);

        // Assigned from the device side, which is the only way to give a
        // user-owned extension a physical phone.
        $device = DeviceProfile::factory()->create([
            'organization_id' => $organization->id,
            'extension_id' => $extension->id,
        ]);

        $this->actingAs($this->admin($organization), 'sanctum')
            ->putJson(
                "/api/v1/organizations/{$organization->id}/extensions/{$extension->id}",
                // Mirrors the admin form, which always submits device_profile_id
                // as null for a user-owned extension.
                $this->fullPayload($extension, ['first_name' => 'Renamed', 'device_profile_id' => null]),
            )
            ->assertOk();

        $this->assertSame(
            $extension->id,
            $device->fresh()->extension_id,
            'An unrelated extension update must not unlink the assigned device.',
        );
    }

    public function test_explicitly_clearing_the_device_still_unlinks_it(): void
    {
        $organization = Organization::factory()->create();

        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => null,
            'is_active' => true,
        ]);

        $device = DeviceProfile::factory()->create([
            'organization_id' => $organization->id,
            'extension_id' => $extension->id,
        ]);

        $extension->update(['device_profile_id' => $device->id]);

        $this->actingAs($this->admin($organization), 'sanctum')
            ->putJson(
                "/api/v1/organizations/{$organization->id}/extensions/{$extension->id}",
                $this->fullPayload($extension, ['device_profile_id' => null]),
            )
            ->assertOk();

        $this->assertNull(
            $device->fresh()->extension_id,
            'Explicitly sending a null device_profile_id must still clear the link.',
        );
    }

    public function test_assigning_a_device_from_the_extension_side_still_links_it(): void
    {
        $organization = Organization::factory()->create();

        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => null,
            'device_profile_id' => null,
            'is_active' => true,
        ]);

        $device = DeviceProfile::factory()->create([
            'organization_id' => $organization->id,
            'extension_id' => null,
        ]);

        $this->actingAs($this->admin($organization), 'sanctum')
            ->putJson(
                "/api/v1/organizations/{$organization->id}/extensions/{$extension->id}",
                $this->fullPayload($extension, ['device_profile_id' => $device->id]),
            )
            ->assertOk();

        $this->assertSame($extension->id, $device->fresh()->extension_id);
    }
}
