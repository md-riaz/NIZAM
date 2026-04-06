<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogViewerTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_list_log_files(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'tenant_id' => null]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/logs');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'directory',
            'files',
        ]);
    }

    public function test_platform_admin_can_view_application_logs(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'tenant_id' => null]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/logs/application?lines=50');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'source',
            'path',
            'lines',
            'logs',
        ]);
        $response->assertJsonPath('source', 'laravel');
    }

    public function test_platform_admin_can_query_freeswitch_logs(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'tenant_id' => null]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/logs/freeswitch?level=info');

        // May fail if FreeSWITCH is not running, but should not be authorization error
        $this->assertContains($response->status(), [200, 503]);
        
        if ($response->status() === 200) {
            $response->assertJsonStructure([
                'source',
                'level',
                'current_log_level',
                'status',
                'note',
            ]);
            $response->assertJsonPath('source', 'freeswitch');
        }
    }

    public function test_tenant_admin_cannot_access_logs(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['role' => 'admin', 'tenant_id' => $tenant->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/logs');

        $response->assertStatus(403);
    }

    public function test_regular_user_cannot_access_logs(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['role' => 'user', 'tenant_id' => $tenant->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/logs');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access_logs(): void
    {
        $response = $this->getJson('/api/admin/logs');

        $response->assertStatus(401);
    }

    public function test_invalid_log_level_returns_400(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'tenant_id' => null]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/logs/freeswitch?level=invalid');

        $response->assertStatus(400);
        $response->assertJsonPath('error', 'Invalid log level');
    }

    public function test_application_logs_respects_line_limit(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'tenant_id' => null]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/logs/application?lines=10');

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(10, count($response->json('logs')));
    }

    public function test_application_logs_enforces_max_limit(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'tenant_id' => null]);

        // Request more than max (1000)
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/logs/application?lines=5000');

        $response->assertStatus(200);
        // Should be capped at 1000
        $this->assertLessThanOrEqual(1000, count($response->json('logs')));
    }
}
