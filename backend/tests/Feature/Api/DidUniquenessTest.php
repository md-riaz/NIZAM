<?php

namespace Tests\Feature\Api;

use App\Models\Did;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DidUniquenessTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);

        // Permissions are deny-by-default, so the acting user is granted the
        // DID abilities these cases exercise rather than relying on a
        // permissive fallback.
        $slugs = ['dids.create', 'dids.update'];
        foreach ($slugs as $slug) {
            Permission::updateOrCreate(['slug' => $slug], ['module' => 'core']);
        }
        $this->user->grantPermissions($slugs);
    }

    public function test_user_without_permission_cannot_create_a_did(): void
    {
        $unprivileged = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($unprivileged, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/dids", [
                'number' => '+15551234567',
                'destination_type' => 'extension',
                'destination_id' => Str::uuid()->toString(),
                'is_active' => true,
            ])
            ->assertForbidden();
    }

    public function test_cannot_create_duplicate_did_number_within_same_organization(): void
    {
        $this->organization->dids()->create([
            'number' => '+15551234567',
            'destination_type' => 'extension',
            'destination_id' => Str::uuid()->toString(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/dids", [
                'number' => '+15551234567',
                'destination_type' => 'extension',
                'destination_id' => Str::uuid()->toString(),
                'is_active' => true,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['number']);
    }

    public function test_same_did_number_allowed_across_different_organizations(): void
    {
        $organizationB = Organization::factory()->create();
        $userB = User::factory()->create(['organization_id' => $organizationB->id]);
        $userB->grantPermissions(['dids.create']);

        // Create DID in organization A
        $this->organization->dids()->create([
            'number' => '+15551234567',
            'destination_type' => 'extension',
            'destination_id' => Str::uuid()->toString(),
            'is_active' => true,
        ]);

        // Create same DID number in organization B — should succeed
        $response = $this->actingAs($userB, 'sanctum')
            ->postJson("/api/v1/organizations/{$organizationB->id}/dids", [
                'number' => '+15551234567',
                'destination_type' => 'extension',
                'destination_id' => Str::uuid()->toString(),
                'is_active' => true,
            ]);

        $response->assertStatus(201);
    }

    public function test_can_update_did_to_keep_same_number(): void
    {
        $did = $this->organization->dids()->create([
            'number' => '+15551234567',
            'destination_type' => 'extension',
            'destination_id' => Str::uuid()->toString(),
            'is_active' => true,
        ]);

        // 'voicemail' isn't in UpdateDidRequest's extension|flow enum, so the
        // update endpoint would 422 regardless of the number check this test
        // targets; use 'flow' to still exercise a destination_type change.
        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/organizations/{$this->organization->id}/dids/{$did->id}", [
                'number' => '+15551234567',
                'description' => 'Updated description',
                'destination_type' => 'flow',
                'destination_id' => Str::uuid()->toString(),
            ]);

        $response->assertStatus(200);
    }

    public function test_cannot_update_did_to_existing_number_in_same_organization(): void
    {
        $this->organization->dids()->create([
            'number' => '+15551234567',
            'destination_type' => 'extension',
            'destination_id' => Str::uuid()->toString(),
            'is_active' => true,
        ]);

        $did2 = $this->organization->dids()->create([
            'number' => '+15559876543',
            'destination_type' => 'extension',
            'destination_id' => Str::uuid()->toString(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/organizations/{$this->organization->id}/dids/{$did2->id}", [
                'number' => '+15551234567',
                'destination_type' => 'extension',
                'destination_id' => Str::uuid()->toString(),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['number']);
    }
}
