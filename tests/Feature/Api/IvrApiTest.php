<?php

namespace Tests\Feature\Api;

use App\Models\Ivr;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IvrApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->tenant = Tenant::factory()->create();
    }

    public function test_can_list_ivrs_for_a_tenant(): void
    {
        Ivr::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/ivrs");

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_an_ivr(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/tenants/{$this->tenant->id}/ivrs", [
                'name' => 'Main Menu',
                'timeout' => 5,
                'max_failures' => 3,
                'options' => [
                    ['digit' => '1', 'destination_type' => 'extension', 'destination_id' => Str::uuid()->toString()],
                    ['digit' => '2', 'destination_type' => 'ring_group', 'destination_id' => Str::uuid()->toString()],
                ],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('ivrs', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Menu',
        ]);
    }

    public function test_can_show_an_ivr(): void
    {
        $ivr = Ivr::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/ivrs/{$ivr->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => $ivr->name]);
    }

    public function test_can_update_an_ivr(): void
    {
        $ivr = Ivr::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/tenants/{$this->tenant->id}/ivrs/{$ivr->id}", [
                'name' => 'Updated Menu',
                'options' => [
                    ['digit' => '1', 'destination_type' => 'voicemail', 'destination_id' => Str::uuid()->toString()],
                ],
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('ivrs', [
            'id' => $ivr->id,
            'name' => 'Updated Menu',
        ]);
    }

    public function test_can_delete_an_ivr(): void
    {
        $ivr = Ivr::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/tenants/{$this->tenant->id}/ivrs/{$ivr->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('ivrs', ['id' => $ivr->id]);
    }
}
