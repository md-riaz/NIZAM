<?php

namespace Tests\Feature\Web;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_dashboard_route_requires_authentication(): void
    {
        $response = $this->get('/ui/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_dashboard_renders_for_tenant_user(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'user',
        ]);

        $response = $this->actingAs($user)->get('/ui/dashboard');

        $response->assertOk();
        $response->assertSee('Tenant Dashboard');
        $response->assertSee('Active calls');
        $response->assertSee('Extensions');
        $response->assertSee('Routing');
        $response->assertDontSee('Fraud Alerts');
        $response->assertDontSee('/api/v1/admin/dashboard', false);
    }

    public function test_suspended_tenant_is_blocked_from_ui_pages_for_non_admin(): void
    {
        $tenant = Tenant::factory()->suspended()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'user',
        ]);

        $this->actingAs($user)->get('/ui/dashboard')->assertForbidden();
        $this->actingAs($user)->get('/ui/system-health')->assertForbidden();
        $this->actingAs($user)->get('/ui/modules')->assertForbidden();
    }

    public function test_non_admin_tenant_selector_does_not_leak_other_tenants(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Allowed Tenant']);
        $otherTenant = Tenant::factory()->create(['name' => 'Hidden Tenant']);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'user',
        ]);

        $response = $this->actingAs($user)->get('/ui/dashboard?tenant='.$otherTenant->id);

        $response->assertOk();
        $response->assertSee('Allowed Tenant');
        $response->assertDontSee('Hidden Tenant');
    }

    public function test_extensions_can_be_created_via_htmx_partial_response(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        $response = $this->actingAs($user)->withHeaders([
            'HX-Request' => 'true',
        ])->post("/ui/tenants/{$tenant->id}/extensions", [
            'extension' => '2001',
            'password' => 'secret1234',
            'directory_first_name' => 'Ops',
            'directory_last_name' => 'Agent',
        ]);

        $response->assertOk();
        $response->assertSee('2001');

        $this->assertDatabaseHas('extensions', [
            'tenant_id' => $tenant->id,
            'extension' => '2001',
        ]);
    }

    public function test_non_admin_cannot_toggle_modules(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'user',
        ]);

        $response = $this->actingAs($user)->post('/ui/modules/PbxRouting/toggle');

        $response->assertForbidden();
    }

    public function test_login_page_renders_and_allows_web_session_login(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => 'password',
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign in');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/ui/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_requested_surface_pages_are_routable_for_non_admin_user(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'user',
        ]);

        $routes = [
            '/ui/routing/dids',
            '/ui/routing/ring-groups',
            '/ui/routing/ivr',
            '/ui/routing/time-conditions',
            '/ui/contact-center/queues',
            '/ui/contact-center/agents',
            '/ui/contact-center/wallboard',
            '/ui/automation/webhooks',
            '/ui/automation/event-log-viewer',
            '/ui/automation/retry-management',
            '/ui/analytics/recordings',
            '/ui/analytics/sla-trends',
            '/ui/analytics/call-volume',
            '/ui/media-policy/gateways',
            '/ui/media-policy/codec-policy',
            '/ui/media-policy/transcoding-stats',
        ];

        foreach ($routes as $route) {
            $this->actingAs($user)->get($route)->assertOk();
        }
    }

    public function test_requested_admin_surface_pages_are_admin_only(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'user',
        ]);

        $routes = [
            '/ui/admin/tenants',
            '/ui/admin/node-health-per-fs',
            '/ui/admin/fraud-alerts',
        ];

        foreach ($routes as $route) {
            $this->actingAs($admin)->get($route)->assertOk();
            $this->actingAs($user)->get($route)->assertForbidden();
        }
    }
}
