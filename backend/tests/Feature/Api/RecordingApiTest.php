<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Recording;
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

        // Permissions are deny-by-default. Recordings split view/download/delete
        // into distinct slugs on purpose, so only grant what these cases need
        // rather than the full set.
        $slugs = ['recordings.view', 'recordings.download', 'recordings.delete'];
        foreach ($slugs as $slug) {
            Permission::updateOrCreate(['slug' => $slug], ['module' => 'core']);
        }
        $this->user->grantPermissions(['recordings.view', 'recordings.delete']);
    }

    public function test_user_without_permission_cannot_list_recordings(): void
    {
        $unprivileged = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($unprivileged, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/recordings")
            ->assertForbidden();
    }

    public function test_view_permission_alone_cannot_download_or_delete_a_recording(): void
    {
        $recording = Recording::factory()->create(['organization_id' => $this->organization->id]);
        $viewer = User::factory()->create(['organization_id' => $this->organization->id]);
        $viewer->grantPermissions(['recordings.view']);

        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/recordings/{$recording->id}/download")
            ->assertForbidden();

        $this->actingAs($viewer, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$this->organization->id}/recordings/{$recording->id}")
            ->assertForbidden();
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
