<?php

namespace Tests\Feature\Api;

use App\Models\Did;
use App\Models\Gateway;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DidApiTest extends TestCase
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

    public function test_can_list_dids_for_a_organization(): void
    {
        Did::factory()->count(3)->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/dids");

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_a_did(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/dids", [
                'number' => '+15551234567',
                'description' => 'Main line',
                'destination_type' => 'extension',
                'destination_id' => Str::uuid()->toString(),
                'is_active' => true,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('dids', [
            'organization_id' => $this->organization->id,
            'number' => '+15551234567',
        ]);
    }

    public function test_can_show_a_did(): void
    {
        $did = Did::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/dids/{$did->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['number' => $did->number]);
    }

    public function test_can_show_a_did_with_linked_gateway_fields(): void
    {
        $gateway = Gateway::factory()->create(['organization_id' => $this->organization->id]);
        $did = Did::factory()->create([
            'organization_id' => $this->organization->id,
            'gateway_id' => $gateway->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/dids/{$did->id}");

        $response->assertOk()
            ->assertJsonPath('data.gateway_id', $gateway->id)
            ->assertJsonPath('data.gateway.id', $gateway->id)
            ->assertJsonPath('data.gateway.name', $gateway->name)
            ->assertJsonPath('data.gateway.organization_id', $gateway->organization_id);
    }

    public function test_show_preserves_canonical_destination_type(): void
    {
        $did = Did::factory()->create([
            'organization_id' => $this->organization->id,
            'destination_type' => 'ivr',
            'destination_id' => Str::uuid()->toString(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/dids/{$did->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.destination_type', 'ivr');
    }

    public function test_can_update_a_did(): void
    {
        $did = Did::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/organizations/{$this->organization->id}/dids/{$did->id}", [
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
        $did = Did::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$this->organization->id}/dids/{$did->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('dids', ['id' => $did->id]);
    }
}
