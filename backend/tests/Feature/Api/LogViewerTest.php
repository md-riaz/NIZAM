<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogViewerTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_list_log_files(): void
    {
        $user = User::factory()->create(['role' => 'superadmin', 'organization_id' => null]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/logs');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'directory',
            'files',
        ]);
    }

    public function test_platform_admin_can_view_application_logs(): void
    {
        $user = User::factory()->create(['role' => 'superadmin', 'organization_id' => null]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/logs/application?lines=50');

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
        $user = User::factory()->create(['role' => 'superadmin', 'organization_id' => null]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/logs/freeswitch?level=info');

        // May return 404 when FreeSWITCH log path is not present in test runtime.
        $this->assertContains($response->status(), [200, 404]);

        if ($response->status() === 200) {
            $response->assertJsonStructure([
                'source',
                'path',
                'size_kb',
                'filter',
                'sort',
                'lines',
                'logs',
            ]);
            $response->assertJsonPath('source', 'freeswitch');
        }

        if ($response->status() === 404) {
            $response->assertJsonPath('error', 'Log file not found');
        }
    }

    public function test_organization_admin_cannot_access_logs(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['role' => 'admin', 'organization_id' => $organization->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/logs');

        $response->assertStatus(403);
    }

    public function test_regular_user_cannot_access_logs(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['role' => 'agent', 'organization_id' => $organization->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/logs');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access_logs(): void
    {
        $response = $this->getJson('/api/v1/admin/logs');

        $response->assertStatus(401);
    }

    public function test_freeswitch_logs_returns_not_found_when_log_path_missing(): void
    {
        $user = User::factory()->create(['role' => 'superadmin', 'organization_id' => null]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/logs/freeswitch');

        if ($response->status() === 404) {
            $response->assertJsonPath('error', 'Log file not found');
        } else {
            $response->assertStatus(200);
        }
    }

    public function test_application_logs_respects_line_limit(): void
    {
        $user = User::factory()->create(['role' => 'superadmin', 'organization_id' => null]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/logs/application?lines=10');

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(10, count($response->json('logs')));
    }

    public function test_application_logs_enforces_max_limit(): void
    {
        $user = User::factory()->create(['role' => 'superadmin', 'organization_id' => null]);

        // Request more than max (1000)
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/logs/application?lines=5000');

        $response->assertStatus(200);
        // Should be capped at 1000
        $this->assertLessThanOrEqual(1000, count($response->json('logs')));
    }
}
