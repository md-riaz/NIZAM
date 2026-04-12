<?php

namespace Tests\Feature\Api\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCapabilityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_get_platform_capabilities(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/capabilities');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'description', 'status', 'category']
            ]
        ]);

        $response->assertJsonFragment(['id' => 'self_call_management', 'status' => 'active']);
    }

    public function test_non_admin_cannot_get_platform_capabilities(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/capabilities');

        $response->assertStatus(403);
    }
}
