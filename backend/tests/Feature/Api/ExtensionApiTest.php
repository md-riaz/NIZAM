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
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/extensions");

        $response->assertStatus(200);
        $response->assertJsonFragment(['extension' => '1001']);
    }

    public function test_can_create_an_extension_for_a_organization(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/extensions", [
                'extension' => '1002',
                'password' => 'secret1234',
                'directory_first_name' => 'Jane',
                'directory_last_name' => 'Doe',
                'voicemail_enabled' => false,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('extensions', [
            'extension' => '1002',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_can_show_an_extension(): void
    {
        $extension = $this->organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/extensions/{$extension->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['extension' => '1001']);
    }

    public function test_can_update_an_extension(): void
    {
        $extension = $this->organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/organizations/{$this->organization->id}/extensions/{$extension->id}", [
                'extension' => '1001',
                'password' => 'updated1234',
                'directory_first_name' => 'Johnny',
                'directory_last_name' => 'Doe',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('extensions', [
            'id' => $extension->id,
            'directory_first_name' => 'Johnny',
        ]);
    }

    public function test_can_delete_an_extension(): void
    {
        $extension = $this->organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
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
        $response->assertJsonValidationErrors(['extension', 'password', 'directory_first_name', 'directory_last_name']);
    }
}
