<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtensionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::create([
            'name' => 'Test Organization',
            'domain' => 'test.example.com',
        ]);
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_can_list_extensions_for_a_organization(): void
    {
        $this->organization->extensions()->create([
            'extension' => '101',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/extensions");

        $response->assertStatus(200);
        $response->assertJsonFragment(['extension' => '101']);
    }

    public function test_can_create_an_extension_for_a_organization(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/extensions", [
                'extension' => '102',
                'password' => 'secret1234',
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'follow_me_enabled' => true,
                'follow_me_destination' => '+15551234567',
                'voicemail_enabled' => false,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.follow_me_enabled', true)
            ->assertJsonPath('data.follow_me_destination', '+15551234567');

        $this->assertDatabaseHas('extensions', [
            'extension' => '102',
            'organization_id' => $this->organization->id,
            'follow_me_enabled' => true,
            'follow_me_destination' => '+15551234567',
        ]);
    }

    public function test_can_show_an_extension(): void
    {
        $extension = $this->organization->extensions()->create([
            'extension' => '101',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/extensions/{$extension->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['extension' => '101']);
    }

    public function test_can_update_an_extension(): void
    {
        $extension = $this->organization->extensions()->create([
            'extension' => '101',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/organizations/{$this->organization->id}/extensions/{$extension->id}", [
                'extension' => '101',
                'password' => 'updated1234',
                'first_name' => 'Johnny',
                'last_name' => 'Doe',
                'follow_me_enabled' => true,
                'follow_me_destination' => '+15557654321',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.follow_me_enabled', true)
            ->assertJsonPath('data.follow_me_destination', '+15557654321');

        $this->assertDatabaseHas('extensions', [
            'id' => $extension->id,
            'first_name' => 'Johnny',
            'follow_me_enabled' => true,
            'follow_me_destination' => '+15557654321',
        ]);
    }

    public function test_can_delete_an_extension(): void
    {
        $extension = $this->organization->extensions()->create([
            'extension' => '101',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$this->organization->id}/extensions/{$extension->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('extensions', ['id' => $extension->id]);
    }

    public function test_validates_required_fields_on_create(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/extensions", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['extension', 'password', 'first_name', 'last_name']);
    }

    public function test_rejects_legacy_caller_id_number_fields_on_create(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/extensions", [
                'extension' => '102',
                'password' => 'secret1234',
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'effective_caller_id_number' => '+15551234567',
                'outbound_caller_id_number' => '+15557654321',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['effective_caller_id_number', 'outbound_caller_id_number']);
    }

    public function test_rejects_legacy_caller_id_number_fields_on_update(): void
    {
        $extension = $this->organization->extensions()->create([
            'extension' => '101',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/organizations/{$this->organization->id}/extensions/{$extension->id}", [
                'extension' => '101',
                'password' => 'updated1234',
                'first_name' => 'Johnny',
                'last_name' => 'Doe',
                'effective_caller_id_number' => '+15551234567',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['effective_caller_id_number']);
    }

    public function test_returns_validation_error_when_creating_forwarding_without_destination(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/extensions", [
                'extension' => '102',
                'password' => 'secret1234',
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'follow_me_enabled' => true,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['follow_me_destination']);
    }

    public function test_dnd_update_retains_stored_follow_me_destination(): void
    {
        $extension = $this->organization->extensions()->create([
            'extension' => '101',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'follow_me_enabled' => true,
            'follow_me_destination' => '+15551234567',
            'dnd_enabled' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/organizations/{$this->organization->id}/extensions/{$extension->id}", [
                'extension' => '101',
                'password' => 'secret1234',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'dnd_enabled' => true,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.follow_me_enabled', false)
            ->assertJsonPath('data.follow_me_destination', '+15551234567')
            ->assertJsonPath('data.dnd_enabled', true);

        $this->assertDatabaseHas('extensions', [
            'id' => $extension->id,
            'follow_me_enabled' => false,
            'follow_me_destination' => '+15551234567',
            'dnd_enabled' => true,
        ]);
    }
}
