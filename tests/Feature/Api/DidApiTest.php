<?php

namespace Tests\Feature\Api;

use App\Models\Did;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DidApiTest extends TestCase
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

    public function test_can_list_dids_for_a_tenant(): void
    {
        Did::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/dids");

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_a_did(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/tenants/{$this->tenant->id}/dids", [
                'number' => '+15551234567',
                'description' => 'Main line',
                'destination_type' => 'extension',
                'destination_id' => Str::uuid()->toString(),
                'is_active' => true,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('dids', [
            'tenant_id' => $this->tenant->id,
            'number' => '+15551234567',
        ]);
    }

    public function test_can_show_a_did(): void
    {
        $did = Did::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/dids/{$did->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['number' => $did->number]);
    }

    public function test_can_update_a_did(): void
    {
        $did = Did::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/tenants/{$this->tenant->id}/dids/{$did->id}", [
                'number' => '+15559999999',
                'destination_type' => 'voicemail',
                'destination_id' => Str::uuid()->toString(),
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('dids', [
            'id' => $did->id,
            'number' => '+15559999999',
        ]);
    }

    public function test_can_delete_a_did(): void
    {
        $did = Did::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/tenants/{$this->tenant->id}/dids/{$did->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('dids', ['id' => $did->id]);
    }
}
