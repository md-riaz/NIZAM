<?php

namespace Tests\Unit\Services\Recording;

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

        $expectedPath = "/tmp/test-recordings/{$organization->id}/{$session->call_uuid}.wav";

        $freeSwitch = $this->mock(FreeSwitchCommandService::class);
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

        $expectedPath = "/tmp/test-recordings/{$session->organization_id}/{$session->call_uuid}.wav";

        $freeSwitch = $this->mock(FreeSwitchCommandService::class);
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
}
