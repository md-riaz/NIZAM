<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallEventStreamTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);

        Permission::updateOrCreate(['slug' => 'call_events.view'], ['module' => 'core']);
        $this->user->grantPermissions(['call_events.view']);
    }

    public function test_stream_endpoint_returns_sse_content_type(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->get("/api/v1/organizations/{$this->organization->id}/call-events/stream");

        $response->assertStatus(200);
        $this->assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));
    }

    public function test_stream_endpoint_requires_authentication(): void
    {
        $response = $this->getJson("/api/v1/organizations/{$this->organization->id}/call-events/stream");

        $response->assertStatus(401);
    }

    public function test_stream_endpoint_includes_no_cache_header(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->get("/api/v1/organizations/{$this->organization->id}/call-events/stream");

        $response->assertStatus(200);
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
    }

    public function test_stream_route_exists(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->get("/api/v1/organizations/{$this->organization->id}/call-events/stream");

        $this->assertNotEquals(404, $response->getStatusCode());
    }
}
