<?php

namespace Tests\Feature\Api;

use App\Models\Recording;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordingApiTest extends TestCase
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

    public function test_can_list_recordings(): void
    {
        Recording::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/recordings");

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_show_recording(): void
    {
        $recording = Recording::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/recordings/{$recording->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['call_uuid' => $recording->call_uuid]);
    }

    public function test_cannot_show_recording_from_another_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $recording = Recording::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/recordings/{$recording->id}");

        $response->assertStatus(404);
    }

    public function test_can_filter_recordings_by_call_uuid(): void
    {
        $recording = Recording::factory()->create(['tenant_id' => $this->tenant->id]);
        Recording::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/recordings?call_uuid={$recording->call_uuid}");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_can_delete_recording(): void
    {
        $recording = Recording::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/tenants/{$this->tenant->id}/recordings/{$recording->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('recordings', ['id' => $recording->id]);
    }

    public function test_recording_has_tenant_relationship(): void
    {
        $recording = Recording::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertEquals($this->tenant->id, $recording->tenant->id);
    }

    public function test_tenant_has_recordings_relationship(): void
    {
        Recording::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

        $this->assertCount(2, $this->tenant->recordings);
    }
}
