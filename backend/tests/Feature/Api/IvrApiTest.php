<?php

namespace Tests\Feature\Api;

use App\Models\Ivr;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IvrApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_can_list_ivrs_for_a_organization(): void
    {
        Ivr::factory()->count(3)->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/ivrs");

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_an_ivr(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/ivrs", [
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
            'organization_id' => $this->organization->id,
            'name' => 'Main Menu',
        ]);
    }

    public function test_can_show_an_ivr(): void
    {
        $ivr = Ivr::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/ivrs/{$ivr->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => $ivr->name]);
    }

    public function test_can_update_an_ivr(): void
    {
        $ivr = Ivr::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/organizations/{$this->organization->id}/ivrs/{$ivr->id}", [
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
        $ivr = Ivr::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$this->organization->id}/ivrs/{$ivr->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('ivrs', ['id' => $ivr->id]);
    }
}
