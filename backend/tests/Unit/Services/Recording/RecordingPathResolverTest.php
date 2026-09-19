<?php

namespace Tests\Unit\Services\Recording;

use App\Models\CallSession;
use App\Services\Recording\RecordingPathResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordingPathResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function resolver(): RecordingPathResolver
    {
        return new RecordingPathResolver;
    }

    public function test_it_joins_record_path_and_record_name(): void
    {
        $path = $this->resolver()->fromChannelVariables([
            'variable_record_path' => '/var/lib/freeswitch/recordings/acme/2026/09/18',
            'variable_record_name' => 'call.wav',
        ]);

        $this->assertSame('/var/lib/freeswitch/recordings/acme/2026/09/18/call.wav', $path);
    }

    public function test_it_tolerates_a_trailing_slash_on_record_path(): void
    {
        $path = $this->resolver()->fromChannelVariables([
            'variable_record_path' => '/recordings/acme/',
            'variable_record_name' => 'call.wav',
        ]);

        $this->assertSame('/recordings/acme/call.wav', $path);
    }

    public function test_it_falls_back_to_the_call_center_filename(): void
    {
        $path = $this->resolver()->fromChannelVariables([
            'variable_record_path' => '/recordings/acme',
            'variable_cc_record_filename' => '/recordings/acme/queued.wav',
        ]);

        // record_path alone is not a path: without record_name there is no file
        // to point at, so the next candidate wins.
        $this->assertSame('/recordings/acme/queued.wav', $path);
    }

    public function test_it_reads_the_last_argument_only_when_the_last_application_recorded(): void
    {
        $resolver = $this->resolver();

        $this->assertSame('/recordings/acme/session.wav', $resolver->fromChannelVariables([
            'variable_last_app' => 'record_session',
            'variable_last_arg' => '/recordings/acme/session.wav',
        ]));

        $this->assertNull($resolver->fromChannelVariables([
            'variable_last_app' => 'bridge',
            'variable_last_arg' => 'user/1001@acme.test',
        ]));
    }

    public function test_it_falls_back_to_sofia_and_conference_recordings(): void
    {
        $resolver = $this->resolver();

        $this->assertSame('/recordings/acme/sofia.wav', $resolver->fromChannelVariables([
            'variable_sofia_record_file' => '/recordings/acme/sofia.wav',
        ]));

        $this->assertSame('/recordings/acme/conf.wav', $resolver->fromChannelVariables([
            'variable_conference_recording' => '/recordings/acme/conf.wav',
        ]));
    }

    public function test_it_ignores_blank_variables(): void
    {
        $this->assertNull($this->resolver()->fromChannelVariables([
            'variable_record_path' => '   ',
            'variable_record_name' => '',
            'variable_sofia_record_file' => ' ',
        ]));
    }

    public function test_it_uses_the_call_session_when_no_channel_variable_carries_the_path(): void
    {
        // This is the case that matters most here: `uuid_record` over the event
        // socket writes the path to no channel variable at all, so a recording
        // this application started would otherwise be lost to the CDR.
        $session = CallSession::factory()->create([
            'variables' => [
                'recording_started' => true,
                'recording_path' => '/recordings/acme/2026/09/18/call.wav',
            ],
        ]);

        $this->assertSame(
            '/recordings/acme/2026/09/18/call.wav',
            $this->resolver()->resolve([], $session)
        );
    }

    public function test_channel_variables_win_over_the_call_session(): void
    {
        $session = CallSession::factory()->create([
            'variables' => [
                'recording_started' => true,
                'recording_path' => '/recordings/acme/stale.wav',
            ],
        ]);

        $path = $this->resolver()->resolve([
            'variable_record_path' => '/recordings/acme',
            'variable_record_name' => 'actual.wav',
        ], $session);

        $this->assertSame('/recordings/acme/actual.wav', $path);
    }

    public function test_an_attempted_recording_that_never_started_is_not_reported(): void
    {
        $session = CallSession::factory()->create([
            'variables' => [
                'recording_attempted' => true,
                'recording_started' => false,
                'recording_path' => '/recordings/acme/never-written.wav',
            ],
        ]);

        $this->assertNull($this->resolver()->resolve([], $session));
    }

    public function test_it_resolves_to_nothing_without_a_session_or_variables(): void
    {
        $this->assertNull($this->resolver()->resolve([], null));
    }
}
