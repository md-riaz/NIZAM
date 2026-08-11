<?php

namespace Tests\Feature\Api;

use App\Models\CallSession;
use App\Models\Organization;
use App\Models\User;
use App\Services\Recording\AnsweredRecordingStarter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CallControllerRecordingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_manual_recording_start_returns_success_json_contract(): void
    {
        $organization = Organization::factory()->create(['domain' => 'acme.test']);
        $user = User::factory()->create(['organization_id' => $organization->id, 'role' => 'admin']);
        // Call control verifies the channel belongs to this organization, so the
        // session has to exist for the command to be dispatched at all.
        CallSession::factory()->create([
            'organization_id' => $organization->id,
            'call_uuid' => 'call-uuid',
        ]);

        $starter = Mockery::mock(AnsweredRecordingStarter::class);
        $starter->shouldReceive('startForCall')
            ->once()
            ->with($organization->id, 'call-uuid')
            ->andReturn([
                'status' => 'started',
                'path' => "/tmp/{$organization->id}/call-uuid.wav",
                'response' => ['response' => '+OK'],
            ]);

        $this->app->instance(AnsweredRecordingStarter::class, $starter);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/v1/organizations/{$organization->id}/calls/recording", [
            'uuid' => 'call-uuid',
            'action' => 'start',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Recording start command sent.')
            ->assertJsonPath('response.status', 'started');
    }

    public function test_manual_recording_stop_returns_success_json_contract(): void
    {
        $organization = Organization::factory()->create(['domain' => 'acme.test']);
        $user = User::factory()->create(['organization_id' => $organization->id, 'role' => 'admin']);
        // Call control verifies the channel belongs to this organization, so the
        // session has to exist for the command to be dispatched at all.
        CallSession::factory()->create([
            'organization_id' => $organization->id,
            'call_uuid' => 'call-uuid',
        ]);

        $starter = Mockery::mock(AnsweredRecordingStarter::class);
        $starter->shouldReceive('stopForCall')
            ->once()
            ->with($organization->id, 'call-uuid')
            ->andReturn([
                'status' => 'stopped',
                'path' => "/tmp/{$organization->id}/call-uuid.wav",
                'response' => ['response' => '+OK'],
            ]);

        $this->app->instance(AnsweredRecordingStarter::class, $starter);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/v1/organizations/{$organization->id}/calls/recording", [
            'uuid' => 'call-uuid',
            'action' => 'stop',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Recording stop command sent.')
            ->assertJsonPath('response.status', 'stopped');
    }
}
