<?php

namespace Tests\Feature\Middleware;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TenantAccessMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'tenant.access'])
            ->get('/test/tenants/{tenant}/resource', function () {
                return response()->json(['ok' => true]);
            });
    }

    public function test_admin_user_can_access_any_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->getJson("/test/tenants/{$tenant->id}/resource");

        $response->assertStatus(200);
    }

    public function test_user_with_matching_tenant_id_can_access_their_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user)
            ->getJson("/test/tenants/{$tenant->id}/resource");

        $response->assertStatus(200);
    }

    public function test_user_with_different_tenant_id_gets_403(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->actingAs($user)
            ->getJson("/test/tenants/{$tenant->id}/resource");

        $response->assertStatus(403);
    }

    public function test_user_with_no_tenant_id_gets_403_for_tenant_scoped_routes(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => null]);

        $response = $this->actingAs($user)
            ->getJson("/test/tenants/{$tenant->id}/resource");

        $response->assertStatus(403);
    }

    public function test_suspended_tenant_is_blocked_for_tenant_user(): void
    {
        $tenant = Tenant::factory()->suspended()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user)
            ->getJson("/test/tenants/{$tenant->id}/resource");

        $response->assertStatus(403);
        $response->assertJsonFragment(['message' => 'Tenant is suspended.']);
    }

    public function test_terminated_tenant_is_blocked_for_tenant_user(): void
    {
        $tenant = Tenant::factory()->terminated()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user)
            ->getJson("/test/tenants/{$tenant->id}/resource");

        $response->assertStatus(403);
        $response->assertJsonFragment(['message' => 'Tenant is terminated.']);
    }

    public function test_suspended_tenant_is_blocked_for_admin_user(): void
    {
        $tenant = Tenant::factory()->suspended()->create();
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->getJson("/test/tenants/{$tenant->id}/resource");

        $response->assertStatus(403);
        $response->assertJsonFragment(['message' => 'Tenant is suspended.']);
    }

    public function test_trial_tenant_is_allowed(): void
    {
        $tenant = Tenant::factory()->trial()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user)
            ->getJson("/test/tenants/{$tenant->id}/resource");

        $response->assertStatus(200);
    }
}
