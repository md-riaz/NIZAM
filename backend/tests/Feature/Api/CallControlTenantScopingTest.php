<?php

namespace Tests\Feature\Api;

use App\Models\CallSession;
use App\Models\Organization;
use App\Models\User;
use App\Services\EslConnectionManager;
use App\Services\Recording\AnsweredRecordingStarter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * FreeSWITCH is shared by every tenant, so `show channels` and the uuid_*
 * commands operate on the whole switch. These tests pin the organization
 * scoping that keeps one tenant from reading or controlling another's calls.
 */
class CallControlTenantScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function admin(Organization $organization): User
    {
        return User::factory()->create(['role' => 'admin', 'organization_id' => $organization->id]);
    }

    public function test_status_omits_channels_belonging_to_another_organization(): void
    {
        $mine = Organization::factory()->create(['domain' => 'mine.test']);
        $theirs = Organization::factory()->create(['domain' => 'theirs.test']);

        CallSession::factory()->create(['organization_id' => $mine->id, 'call_uuid' => 'mine-uuid']);
        CallSession::factory()->create(['organization_id' => $theirs->id, 'call_uuid' => 'theirs-uuid']);

        $esl = Mockery::mock(EslConnectionManager::class);
        $esl->shouldReceive('connect')->once()->andReturn(true);
        $esl->shouldReceive('disconnect')->once();
        $esl->shouldReceive('api')->once()->with('show channels as json')->andReturn(json_encode([
            'row_count' => 3,
            'rows' => [
                ['uuid' => 'mine-uuid', 'context' => 'mine.test', 'cid_num' => '1001'],
                ['uuid' => 'theirs-uuid', 'context' => 'theirs.test', 'cid_num' => '2002'],
                ['uuid' => 'unattributable-uuid', 'context' => 'public', 'cid_num' => '3003'],
            ],
        ]));

        $this->app->instance(EslConnectionManager::class, $esl);

        $response = $this->actingAs($this->admin($mine), 'sanctum')
            ->getJson("/api/v1/organizations/{$mine->id}/calls/status");

        $response->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('channels.0.uuid', 'mine-uuid');

        $body = $response->json();
        $this->assertStringNotContainsString('theirs-uuid', json_encode($body));
        $this->assertStringNotContainsString('2002', json_encode($body));
    }

    public function test_status_includes_channels_matched_by_organization_domain_context(): void
    {
        $mine = Organization::factory()->create(['domain' => 'mine.test']);

        $esl = Mockery::mock(EslConnectionManager::class);
        $esl->shouldReceive('connect')->once()->andReturn(true);
        $esl->shouldReceive('disconnect')->once();
        $esl->shouldReceive('api')->once()->andReturn(json_encode([
            'row_count' => 2,
            'rows' => [
                ['uuid' => 'no-session-yet', 'context' => 'mine.test'],
                ['uuid' => 'other-tenant', 'context' => 'elsewhere.test'],
            ],
        ]));

        $this->app->instance(EslConnectionManager::class, $esl);

        $this->actingAs($this->admin($mine), 'sanctum')
            ->getJson("/api/v1/organizations/{$mine->id}/calls/status")
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('channels.0.uuid', 'no-session-yet');
    }

    public function test_hangup_refuses_a_uuid_owned_by_another_organization(): void
    {
        $mine = Organization::factory()->create(['domain' => 'mine.test']);
        $theirs = Organization::factory()->create(['domain' => 'theirs.test']);
        CallSession::factory()->create(['organization_id' => $theirs->id, 'call_uuid' => 'theirs-uuid']);

        // No ESL binding is registered: a leaked command would fail loudly
        // rather than silently pass this test.
        $this->actingAs($this->admin($mine), 'sanctum')
            ->postJson("/api/v1/organizations/{$mine->id}/calls/hangup", ['uuid' => 'theirs-uuid'])
            ->assertNotFound();
    }

    public function test_transfer_and_hold_refuse_a_foreign_uuid(): void
    {
        $mine = Organization::factory()->create(['domain' => 'mine.test']);
        $theirs = Organization::factory()->create(['domain' => 'theirs.test']);
        CallSession::factory()->create(['organization_id' => $theirs->id, 'call_uuid' => 'theirs-uuid']);
        $actor = $this->admin($mine);

        $this->actingAs($actor, 'sanctum')
            ->postJson("/api/v1/organizations/{$mine->id}/calls/transfer", [
                'uuid' => 'theirs-uuid',
                'destination' => '1001',
            ])
            ->assertNotFound();

        $this->actingAs($actor, 'sanctum')
            ->postJson("/api/v1/organizations/{$mine->id}/calls/hold", [
                'uuid' => 'theirs-uuid',
                'action' => 'hold',
            ])
            ->assertNotFound();
    }

    public function test_recording_toggle_refuses_a_foreign_uuid(): void
    {
        $mine = Organization::factory()->create(['domain' => 'mine.test']);
        $theirs = Organization::factory()->create(['domain' => 'theirs.test']);
        CallSession::factory()->create(['organization_id' => $theirs->id, 'call_uuid' => 'theirs-uuid']);

        $starter = Mockery::mock(AnsweredRecordingStarter::class);
        $starter->shouldNotReceive('startForCall');
        $this->app->instance(AnsweredRecordingStarter::class, $starter);

        $this->actingAs($this->admin($mine), 'sanctum')
            ->postJson("/api/v1/organizations/{$mine->id}/calls/recording", [
                'uuid' => 'theirs-uuid',
                'action' => 'start',
            ])
            ->assertNotFound();
    }
}
