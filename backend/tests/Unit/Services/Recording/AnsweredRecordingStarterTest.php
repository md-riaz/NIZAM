<?php

namespace Tests\Unit\Services\Recording;

use App\Models\CallDeliveryAttempt;
use App\Models\CallSession;
use App\Models\Organization;
use App\Services\Media\FreeSwitchCommandService;
use App\Services\Recording\AnsweredRecordingStarter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnsweredRecordingStarterTest extends TestCase
{
    use RefreshDatabase;

    public function test_starter_skips_when_policy_is_disabled(): void
    {
        $session = CallSession::factory()->create([
            'variables' => [],
        ]);

        $freeSwitch = $this->mock(FreeSwitchCommandService::class);
        $freeSwitch->shouldNotReceive('execute');

        $result = app(AnsweredRecordingStarter::class)->start($session, [
            'organization_id' => $session->organization_id,
            'call_uuid' => $session->call_uuid,
            'should_record' => false,
            'answered_target_type' => 'extension',
        ]);

        $this->assertSame('skipped', $result['status']);
        $this->assertSame('policy_disabled', $result['reason']);
        $this->assertFalse((bool) data_get($session->fresh()->variables, 'recording_started', false));
    }

    public function test_starter_starts_recording_once_for_a_call_session(): void
    {
        config(['filesystems.disks.recordings.root' => '/tmp/test-recordings']);

        $organization = Organization::factory()->create();
        $session = CallSession::factory()->for($organization)->create([
            'variables' => [],
        ]);

        $expectedPath = sprintf(
            '/tmp/test-recordings/%s/%s/%s.wav',
            $organization->id,
            now()->format('Y/m/d'),
            $session->call_uuid
        );

        $freeSwitch = $this->mock(FreeSwitchCommandService::class);
        // The recorder reads these when it starts, so they are set first.
        $freeSwitch->shouldReceive('execute')
            ->with('uuid_setvar_multi', \Mockery::any(), false)
            ->andReturn(['executed' => true]);
        $freeSwitch->shouldReceive('execute')
            ->once()
            ->with('uuid_record', [$session->call_uuid, 'start', $expectedPath], false)
            ->andReturn([
                'command' => 'uuid_record',
                'arguments' => [$session->call_uuid, 'start', $expectedPath],
                'executed' => true,
                'background' => false,
                'response' => '+OK',
            ]);

        $starter = app(AnsweredRecordingStarter::class);

        $first = $starter->start($session, [
            'organization_id' => $session->organization_id,
            'call_uuid' => $session->call_uuid,
            'should_record' => true,
            'answered_target_type' => 'extension',
        ]);

        $second = $starter->start($session->fresh(), [
            'organization_id' => $session->organization_id,
            'call_uuid' => $session->call_uuid,
            'should_record' => true,
            'answered_target_type' => 'extension',
        ]);

        $session->refresh();

        $this->assertSame('started', $first['status']);
        $this->assertSame($expectedPath, $first['path']);
        $this->assertSame('skipped', $second['status']);
        $this->assertSame('already_recording', $second['reason']);
        $this->assertTrue((bool) data_get($session->variables, 'recording_attempted'));
        $this->assertTrue((bool) data_get($session->variables, 'recording_started'));
        $this->assertSame($expectedPath, data_get($session->variables, 'recording_path'));
    }

    public function test_starter_returns_failed_status_when_freeswitch_command_fails(): void
    {
        config(['filesystems.disks.recordings.root' => '/tmp/test-recordings']);

        $session = CallSession::factory()->create([
            'variables' => [],
        ]);

        $expectedPath = sprintf(
            '/tmp/test-recordings/%s/%s/%s.wav',
            $session->organization_id,
            now()->format('Y/m/d'),
            $session->call_uuid
        );

        $freeSwitch = $this->mock(FreeSwitchCommandService::class);
        // The recorder reads these when it starts, so they are set first.
        $freeSwitch->shouldReceive('execute')
            ->with('uuid_setvar_multi', \Mockery::any(), false)
            ->andReturn(['executed' => true]);
        $freeSwitch->shouldReceive('execute')
            ->once()
            ->with('uuid_record', [$session->call_uuid, 'start', $expectedPath], false)
            ->andReturn([
                'command' => 'uuid_record',
                'arguments' => [$session->call_uuid, 'start', $expectedPath],
                'executed' => false,
                'error' => 'Unable to connect to FreeSWITCH ESL.',
            ]);

        $result = app(AnsweredRecordingStarter::class)->start($session, [
            'organization_id' => $session->organization_id,
            'call_uuid' => $session->call_uuid,
            'should_record' => true,
            'answered_target_type' => 'extension',
        ]);

        $session->refresh();

        $this->assertSame('failed', $result['status']);
        $this->assertSame($expectedPath, $result['path']);
        $this->assertSame('Unable to connect to FreeSWITCH ESL.', $result['reason']);
        $this->assertTrue((bool) data_get($session->variables, 'recording_attempted'));
        $this->assertFalse((bool) data_get($session->variables, 'recording_started', false));
        $this->assertSame($expectedPath, data_get($session->variables, 'recording_path'));
    }

    public function test_outbound_calls_swap_the_recorded_channels(): void
    {
        config(['filesystems.disks.recordings.root' => '/tmp/test-recordings']);

        $session = CallSession::factory()->create(['variables' => []]);

        $variables = null;
        $freeSwitch = $this->mock(FreeSwitchCommandService::class);
        $freeSwitch->shouldReceive('execute')
            ->with('uuid_setvar_multi', \Mockery::on(function (array $arguments) use (&$variables): bool {
                $variables = $arguments[1] ?? null;

                return true;
            }), false)
            ->andReturn(['executed' => true]);
        $freeSwitch->shouldReceive('execute')
            ->with('uuid_record', \Mockery::any(), false)
            ->andReturn(['executed' => true]);

        app(AnsweredRecordingStarter::class)->start($session, [
            'organization_id' => $session->organization_id,
            'call_uuid' => $session->call_uuid,
            'should_record' => true,
            'direction' => 'outbound',
            'answered_target_type' => 'extension',
        ]);

        // Which leg is "read" is reversed on an outbound call, so the channels
        // have to be swapped or the two parties land the wrong way round.
        $this->assertStringContainsString('record_stereo_swap=true', (string) $variables);
        $this->assertStringNotContainsString('record_stereo=true', (string) $variables);
    }

    public function test_a_successful_start_is_remembered_as_a_recording_that_exists(): void
    {
        config(['filesystems.disks.recordings.root' => '/tmp/test-recordings']);

        $session = CallSession::factory()->create(['variables' => []]);

        $freeSwitch = $this->mock(FreeSwitchCommandService::class);
        $freeSwitch->shouldReceive('execute')->andReturn(['executed' => true]);

        app(AnsweredRecordingStarter::class)->start($session, [
            'organization_id' => $session->organization_id,
            'call_uuid' => $session->call_uuid,
            'should_record' => true,
            'answered_target_type' => 'extension',
        ]);

        $this->assertTrue((bool) data_get($session->fresh()->variables, 'recording_created'));
    }

    public function test_stopping_leaves_the_record_that_a_recording_exists(): void
    {
        config(['filesystems.disks.recordings.root' => '/tmp/test-recordings']);

        $session = CallSession::factory()->create([
            'variables' => [
                'recording_attempted' => true,
                'recording_created' => true,
                'recording_started' => true,
                'recording_path' => '/tmp/test-recordings/live.wav',
            ],
        ]);

        $freeSwitch = $this->mock(FreeSwitchCommandService::class);
        $freeSwitch->shouldReceive('execute')
            ->once()
            ->with('uuid_record', [$session->call_uuid, 'stop', '/tmp/test-recordings/live.wav'], false)
            ->andReturn(['executed' => true]);

        $result = app(AnsweredRecordingStarter::class)
            ->stopForCall($session->organization_id, $session->call_uuid);

        $session->refresh();

        $this->assertSame('stopped', $result['status']);
        $this->assertFalse((bool) data_get($session->variables, 'recording_started'));
        // The recorder is idle, but the file it wrote still has to reach the
        // CDR and the archive.
        $this->assertTrue((bool) data_get($session->variables, 'recording_created'));
        $this->assertSame('/tmp/test-recordings/live.wav', data_get($session->variables, 'recording_path'));
    }

    public function test_manual_control_finds_the_session_behind_an_answered_b_leg(): void
    {
        config(['filesystems.disks.recordings.root' => '/tmp/test-recordings']);

        $organization = Organization::factory()->create();
        $session = CallSession::factory()->for($organization)->create(['variables' => []]);

        // A supervisor recording an answered call acts on the B-leg UUID, which
        // lives on the delivery attempt, not on the session.
        $attempt = CallDeliveryAttempt::factory()->forCallSession($session)->create([
            'freeswitch_leg_uuid' => 'answered-b-leg-uuid',
        ]);

        $freeSwitch = $this->mock(FreeSwitchCommandService::class);
        $freeSwitch->shouldReceive('execute')->andReturn(['executed' => true]);

        $result = app(AnsweredRecordingStarter::class)
            ->startForCall($organization->id, $attempt->freeswitch_leg_uuid);

        $session->refresh();

        $this->assertSame('started', $result['status']);
        $this->assertSame($result['path'], data_get($session->variables, 'recording_path'));
        $this->assertTrue((bool) data_get($session->variables, 'recording_created'));
    }

    public function test_manual_control_does_not_reach_another_organizations_call(): void
    {
        config(['filesystems.disks.recordings.root' => '/tmp/test-recordings']);

        $session = CallSession::factory()->create(['variables' => []]);
        $other = Organization::factory()->create();

        $freeSwitch = $this->mock(FreeSwitchCommandService::class);
        $freeSwitch->shouldReceive('execute')->andReturn(['executed' => true]);

        app(AnsweredRecordingStarter::class)->startForCall($other->id, $session->call_uuid);

        $this->assertNull(data_get($session->fresh()->variables, 'recording_path'));
    }
}
