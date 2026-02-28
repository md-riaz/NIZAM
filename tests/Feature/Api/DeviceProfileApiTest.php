<?php

namespace Tests\Feature\Api;

use App\Models\DeviceProfile;
use App\Models\Extension;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceProfileApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_can_list_device_profiles_for_a_tenant(): void
    {
        DeviceProfile::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/device-profiles");

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_a_device_profile(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/tenants/{$this->tenant->id}/device-profiles", [
                'name' => 'Lobby Phone',
                'vendor' => 'polycom',
                'mac_address' => 'AA:BB:CC:DD:EE:FF',
                'is_active' => true,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('device_profiles', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Lobby Phone',
            'vendor' => 'polycom',
        ]);
    }

    public function test_can_show_a_device_profile(): void
    {
        $profile = DeviceProfile::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/device-profiles/{$profile->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => $profile->name]);
    }

    public function test_can_update_a_device_profile(): void
    {
        $profile = DeviceProfile::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/tenants/{$this->tenant->id}/device-profiles/{$profile->id}", [
                'name' => 'Updated Phone',
                'vendor' => 'yealink',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('device_profiles', [
            'id' => $profile->id,
            'name' => 'Updated Phone',
            'vendor' => 'yealink',
        ]);
    }

    public function test_can_delete_a_device_profile(): void
    {
        $profile = DeviceProfile::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/tenants/{$this->tenant->id}/device-profiles/{$profile->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('device_profiles', ['id' => $profile->id]);
    }

    public function test_validates_required_fields_on_create(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/tenants/{$this->tenant->id}/device-profiles", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'vendor']);
    }

    public function test_can_create_with_extension_assignment(): void
    {
        $extension = Extension::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/tenants/{$this->tenant->id}/device-profiles", [
                'name' => 'Desk Phone',
                'vendor' => 'grandstream',
                'mac_address' => '11:22:33:44:55:66',
                'extension_id' => $extension->id,
                'is_active' => true,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('device_profiles', [
            'extension_id' => $extension->id,
        ]);
    }

    public function test_returns_404_for_wrong_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $profile = DeviceProfile::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/device-profiles/{$profile->id}");

        $response->assertStatus(404);
    }
}
