<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'superadmin', 'organization_id' => null]);
    }

    private function organizationUser(Organization $organization): User
    {
        return User::factory()->create(['organization_id' => $organization->id, 'role' => 'agent']);
    }

    public function test_unauthenticated_requests_return_401(): void
    {
        $response = $this->getJson('/api/v1/organizations');

        $response->assertStatus(401);
    }

    public function test_admin_can_list_all_organizations(): void
    {
        $user = $this->adminUser();

        Organization::create([
            'name' => 'Organization One',
            'domain' => 'one.example.com',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/organizations');

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Organization One']);
    }

    public function test_organization_user_only_sees_own_organization(): void
    {
        $organization = Organization::create([
            'name' => 'My Organization',
            'domain' => 'my.example.com',
        ]);

        Organization::create([
            'name' => 'Other Organization',
            'domain' => 'other.example.com',
        ]);

        $user = $this->organizationUser($organization);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/organizations');

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'My Organization']);
        $response->assertJsonMissing(['name' => 'Other Organization']);
    }

    public function test_admin_can_create_a_organization(): void
    {
        $user = $this->adminUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/organizations', [
                'name' => 'New Organization',
                'domain_prefix' => 'new',
                'max_extensions' => 100,
                'is_active' => true,
            ]);

        $response->assertStatus(201);
        $organizationId = $response->json('data.id');

        $this->assertDatabaseHas('organizations', [
            'name' => 'New Organization',
            'domain' => 'new',
        ]);
        $this->assertDatabaseHas('schedules', [
            'organization_id' => $organizationId,
            'name' => 'Main Business Hours',
        ]);
        $this->assertDatabaseHas('flows', [
            'organization_id' => $organizationId,
            'name' => 'Main Business Phone',
        ]);
        $this->assertDatabaseHas('dids', [
            'organization_id' => $organizationId,
            'description' => 'Default Business Phone Entrypoint',
            'destination_type' => 'flow',
        ]);
    }

    public function test_non_admin_cannot_create_a_organization(): void
    {
        $organization = Organization::create([
            'name' => 'Existing',
            'domain' => 'existing.example.com',
        ]);
        $user = $this->organizationUser($organization);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/organizations', [
                'name' => 'New Organization',
                'domain_prefix' => 'new',
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_show_a_single_organization(): void
    {
        $user = $this->adminUser();

        $organization = Organization::create([
            'name' => 'Show Organization',
            'domain' => 'show.example.com',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/organizations/{$organization->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Show Organization']);
    }

    public function test_admin_can_update_a_organization(): void
    {
        $user = $this->adminUser();

        $organization = Organization::create([
            'name' => 'Old Name',
            'domain' => 'old.example.com',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/organizations/{$organization->id}", [
                'name' => 'Updated Name',
                'domain_prefix' => 'old',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('organizations', ['name' => 'Updated Name']);
    }

    public function test_admin_can_delete_a_organization(): void
    {
        $user = $this->adminUser();

        $organization = Organization::create([
            'name' => 'Delete Me',
            'domain' => 'delete.example.com',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$organization->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('organizations', ['id' => $organization->id]);
    }

    public function test_validates_required_fields_on_create(): void
    {
        $user = $this->adminUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/organizations', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'domain_prefix']);
    }

    public function test_organization_creation_provisions_default_business_phone_entrypoint(): void
    {
        $user = $this->adminUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/organizations', [
                'name' => 'Provisioned Organization',
                'domain_prefix' => 'provisioned',
            ]);

        $response->assertStatus(201);

        $organization = Organization::query()
            ->with(['defaultSchedule', 'flows.activeVersion', 'dids'])
            ->findOrFail($response->json('data.id'));

        $this->assertNotNull($organization->defaultSchedule);
        $this->assertSame('Main Business Hours', $organization->defaultSchedule->name);
        $this->assertDatabaseCount('schedule_rules', 5);

        $starterFlow = $organization->flows->firstWhere('name', 'Main Business Phone');
        $this->assertNotNull($starterFlow);
        $this->assertNotNull($starterFlow->activeVersion);
        $this->assertSame((string) $starterFlow->id, data_get($organization->settings, 'business_phone.default_entrypoint.flow_id'));
        $this->assertSame((string) $organization->defaultSchedule->id, data_get($organization->settings, 'business_phone.default_entrypoint.schedule_id'));

        $starterDid = $organization->dids->firstWhere('description', 'Default Business Phone Entrypoint');
        $this->assertNotNull($starterDid);
        $this->assertSame('flow', $starterDid->destination_type);
        $this->assertSame((string) $starterFlow->id, (string) $starterDid->destination_id);

        $starterExtension = $organization->extensions()->first();
        $this->assertNotNull($starterExtension);
        $this->assertSame('101', $starterExtension->extension);
        $this->assertSame('Main', $starterExtension->first_name);
        $this->assertSame('User', $starterExtension->last_name);
        $this->assertTrue($starterExtension->is_active);
        $this->assertTrue($starterExtension->voicemail_enabled);
    }
}
