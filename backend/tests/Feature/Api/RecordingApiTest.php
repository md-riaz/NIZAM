<?php

namespace Tests\Feature\Api;

use App\Models\Recording;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordingApiTest extends TestCase
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

    public function test_can_list_recordings(): void
    {
        Recording::factory()->count(3)->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/recordings");

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_show_recording(): void
    {
        $recording = Recording::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/recordings/{$recording->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['call_uuid' => $recording->call_uuid]);
    }

    public function test_cannot_show_recording_from_another_organization(): void
    {
        $otherOrganization = Organization::factory()->create();
        $recording = Recording::factory()->create(['organization_id' => $otherOrganization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/recordings/{$recording->id}");

        $response->assertStatus(403);
    }

    public function test_can_filter_recordings_by_call_uuid(): void
    {
        $recording = Recording::factory()->create(['organization_id' => $this->organization->id]);
        Recording::factory()->count(2)->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/recordings?call_uuid={$recording->call_uuid}");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_can_delete_recording(): void
    {
        $recording = Recording::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$this->organization->id}/recordings/{$recording->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('recordings', ['id' => $recording->id]);
    }

    public function test_recording_has_organization_relationship(): void
    {
        $recording = Recording::factory()->create(['organization_id' => $this->organization->id]);

        $this->assertEquals($this->organization->id, $recording->organization->id);
    }

    public function test_organization_has_recordings_relationship(): void
    {
        Recording::factory()->count(2)->create(['organization_id' => $this->organization->id]);

        $this->assertCount(2, $this->organization->recordings);
    }
}
