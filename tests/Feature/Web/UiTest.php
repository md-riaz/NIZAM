<?php

namespace Tests\Feature\Web;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UiTest extends TestCase
{
    use RefreshDatabase;

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
}
