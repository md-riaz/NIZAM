<?php

namespace Tests\Feature\Api;

use App\Models\CallDetailRecord;
use App\Models\CallRoutingPolicy;
use App\Models\DeviceProfile;
use App\Models\Did;
use App\Models\Extension;
use App\Models\Flow;
use App\Models\Ivr;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Recording;
use App\Models\Team;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationStatsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create();
        $this->admin = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role' => 'admin',
        ]);
    }

    public function test_returns_correct_stats(): void
    {
        Extension::factory()->count(3)->create(['organization_id' => $this->organization->id, 'is_active' => true]);
        Extension::factory()->create(['organization_id' => $this->organization->id, 'is_active' => false]);
        Did::factory()->count(2)->create(['organization_id' => $this->organization->id]);
        Team::create([
            'organization_id' => $this->organization->id,
            'name' => 'Stats Team',
            'strategy' => 'simultaneous',
            'timeout' => 30,
            'is_active' => true,
        ]);
        Ivr::factory()->create(['organization_id' => $this->organization->id]);
        CallDetailRecord::factory()->count(5)->create(['organization_id' => $this->organization->id]);
        CallDetailRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'start_stamp' => now(),
        ]);
        Recording::factory()->count(2)->create([
            'organization_id' => $this->organization->id,
            'file_size' => 1000,
        ]);
        DeviceProfile::factory()->create(['organization_id' => $this->organization->id]);
        Webhook::factory()->create(['organization_id' => $this->organization->id]);
        CallRoutingPolicy::factory()->count(2)->create(['organization_id' => $this->organization->id]);
        Flow::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/stats");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'extensions_count' => 4,
            'active_extensions_count' => 3,
            'dids_count' => 2,
            'teams_count' => 1,
            'ivrs_count' => 1,
            'recordings_count' => 2,
            'recordings_total_size' => 2000,
            'device_profiles_count' => 1,
            'webhooks_count' => 1,
            'call_routing_policies_count' => 2,
            'flows_count' => 1,
        ]);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson("/api/v1/organizations/{$this->organization->id}/stats");

        $response->assertStatus(401);
    }

    public function test_same_organization_user_can_access(): void
    {
        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role' => 'agent',
        ]);

        // Permissions are deny-by-default; viewing stats reuses the
        // organization "view" ability.
        Permission::updateOrCreate(['slug' => 'organizations.view'], ['module' => 'core']);
        $user->grantPermissions(['organizations.view']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/stats");

        $response->assertStatus(200);
    }

    public function test_user_without_permission_cannot_access_stats(): void
    {
        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role' => 'agent',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/stats");

        $response->assertForbidden();
    }

    public function test_different_organization_user_gets_403(): void
    {
        $otherOrganization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $otherOrganization->id,
            'role' => 'agent',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/stats");

        $response->assertStatus(403);
    }
}
