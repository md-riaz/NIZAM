<?php

namespace Tests\Feature\Api;

use App\Models\Did;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DidUniquenessTest extends TestCase
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

    public function test_cannot_create_duplicate_did_number_within_same_tenant(): void
    {
        $this->tenant->dids()->create([
            'number' => '+15551234567',
            'destination_type' => 'extension',
            'destination_id' => Str::uuid()->toString(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/tenants/{$this->tenant->id}/dids", [
                'number' => '+15551234567',
                'destination_type' => 'extension',
                'destination_id' => Str::uuid()->toString(),
                'is_active' => true,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['number']);
    }

    public function test_same_did_number_allowed_across_different_tenants(): void
    {
        $tenantB = Tenant::factory()->create();
        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

        // Create DID in tenant A
        $this->tenant->dids()->create([
            'number' => '+15551234567',
            'destination_type' => 'extension',
            'destination_id' => Str::uuid()->toString(),
            'is_active' => true,
        ]);

        // Create same DID number in tenant B — should succeed
        $response = $this->actingAs($userB, 'sanctum')
            ->postJson("/api/tenants/{$tenantB->id}/dids", [
                'number' => '+15551234567',
                'destination_type' => 'extension',
                'destination_id' => Str::uuid()->toString(),
                'is_active' => true,
            ]);

        $response->assertStatus(201);
    }

    public function test_can_update_did_to_keep_same_number(): void
    {
        $did = $this->tenant->dids()->create([
            'number' => '+15551234567',
            'destination_type' => 'extension',
            'destination_id' => Str::uuid()->toString(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/tenants/{$this->tenant->id}/dids/{$did->id}", [
                'number' => '+15551234567',
                'description' => 'Updated description',
                'destination_type' => 'voicemail',
                'destination_id' => Str::uuid()->toString(),
            ]);

        $response->assertStatus(200);
    }

    public function test_cannot_update_did_to_existing_number_in_same_tenant(): void
    {
        $this->tenant->dids()->create([
            'number' => '+15551234567',
            'destination_type' => 'extension',
            'destination_id' => Str::uuid()->toString(),
            'is_active' => true,
        ]);

        $did2 = $this->tenant->dids()->create([
            'number' => '+15559876543',
            'destination_type' => 'extension',
            'destination_id' => Str::uuid()->toString(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/tenants/{$this->tenant->id}/dids/{$did2->id}", [
                'number' => '+15551234567',
                'destination_type' => 'extension',
                'destination_id' => Str::uuid()->toString(),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['number']);
    }
}
