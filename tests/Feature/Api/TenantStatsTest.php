<?php

namespace Tests\Feature\Api;

use App\Models\CallDetailRecord;
use App\Models\CallFlow;
use App\Models\CallRoutingPolicy;
use App\Models\DeviceProfile;
use App\Models\Did;
use App\Models\Extension;
use App\Models\Ivr;
use App\Models\Recording;
use App\Models\RingGroup;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantStatsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);
    }

    public function test_returns_correct_stats(): void
    {
        Extension::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);
        Extension::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => false]);
        Did::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);
        RingGroup::factory()->create(['tenant_id' => $this->tenant->id]);
        Ivr::factory()->create(['tenant_id' => $this->tenant->id]);
        CallDetailRecord::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);
        CallDetailRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'start_stamp' => now(),
        ]);
        Recording::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'file_size' => 1000,
        ]);
        DeviceProfile::factory()->create(['tenant_id' => $this->tenant->id]);
        Webhook::factory()->create(['tenant_id' => $this->tenant->id]);
        CallRoutingPolicy::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);
        CallFlow::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/stats");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'extensions_count' => 4,
            'active_extensions_count' => 3,
            'dids_count' => 2,
            'ring_groups_count' => 1,
            'ivrs_count' => 1,
            'recordings_count' => 2,
            'recordings_total_size' => 2000,
            'device_profiles_count' => 1,
            'webhooks_count' => 1,
            'call_routing_policies_count' => 2,
            'call_flows_count' => 1,
        ]);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson("/api/tenants/{$this->tenant->id}/stats");

        $response->assertStatus(401);
    }

    public function test_same_tenant_user_can_access(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'user',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/stats");

        $response->assertStatus(200);
    }

    public function test_different_tenant_user_gets_403(): void
    {
        $otherTenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'role' => 'user',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/stats");

        $response->assertStatus(403);
    }
}
