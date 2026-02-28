<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $tenant->id]);
    }

    public function test_can_list_tokens(): void
    {
        $this->user->createToken('test-token');

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/auth/tokens');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['name' => 'test-token']);
    }

    public function test_can_create_named_token(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/auth/tokens', ['name' => 'my-api-token']);

        $response->assertStatus(201);
        $response->assertJsonStructure(['plainTextToken', 'data']);
        $this->assertNotEmpty($response->json('plainTextToken'));
    }

    public function test_can_revoke_own_token(): void
    {
        $token = $this->user->createToken('revoke-me');
        $tokenId = $token->accessToken->id;

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/auth/tokens/{$tokenId}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    public function test_cannot_revoke_another_users_token(): void
    {
        $otherUser = User::factory()->create(['tenant_id' => $this->user->tenant_id]);
        $token = $otherUser->createToken('other-token');
        $tokenId = $token->accessToken->id;

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/auth/tokens/{$tokenId}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenId]);
    }
}
