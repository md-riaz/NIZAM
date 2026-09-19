<?php

namespace Tests\Unit\Services\Cdr;

use App\Services\Cdr\XmlCdrFileParser;
use Tests\TestCase;

/**
 * A spooled record does not say where the recording went in any one place.
 *
 * FreeSWITCH has several ways to record a call and each leaves the path
 * somewhere different, so the record has to be asked in turn. Reading a single
 * variable — and the wrong one at that — meant the spool reported no recording
 * for every call, whatever had actually been captured.
 */
class XmlCdrRecordingPathTest extends TestCase
{
    public function test_it_joins_the_record_directory_and_name(): void
    {
        $this->assertSame(
            '/recordings/acme/2026/Sep/18/abc.wav',
            $this->pathFor([
                'record_path' => '/recordings/acme/2026/Sep/18',
                'record_name' => 'abc.wav',
            ])
        );
    }

    public function test_it_tolerates_a_trailing_separator_on_the_directory(): void
    {
        $this->assertSame(
            '/recordings/acme/abc.wav',
            $this->pathFor(['record_path' => '/recordings/acme/', 'record_name' => 'abc.wav'])
        );
    }

    public function test_it_reads_a_queue_recording_filename(): void
    {
        $this->assertSame(
            '/recordings/queue/abc.wav',
            $this->pathFor(['cc_record_filename' => '/recordings/queue/abc.wav'])
        );
    }

    /**
     * `record_session` leaves its destination as the last application argument,
     * which is the only trace of it left on the channel.
     */
    public function test_it_recovers_the_path_from_a_record_session_application(): void
    {
        $this->assertSame(
            '/recordings/acme/abc.wav',
            $this->pathFor(['last_app' => 'record_session', 'last_arg' => '/recordings/acme/abc.wav'])
        );
    }

    public function test_it_ignores_the_last_argument_of_any_other_application(): void
    {
        $this->assertNull($this->pathFor(['last_app' => 'bridge', 'last_arg' => 'user/1001']));
    }

    public function test_it_reads_a_conference_recording(): void
    {
        $this->assertSame(
            '/recordings/conf/abc.wav',
            $this->pathFor(['conference_recording' => '/recordings/conf/abc.wav'])
        );
    }

    /**
     * A recording started with `uuid_record` over the event socket writes its
     * path to no channel variable at all, so the record genuinely cannot say.
     * Reporting nothing is correct; the caller must not treat it as "no
     * recording exists".
     */
    public function test_it_reports_nothing_when_the_record_does_not_say(): void
    {
        $this->assertNull($this->pathFor([]));
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function pathFor(array $variables): ?string
    {
        $body = '';

        foreach ($variables as $name => $value) {
            $body .= sprintf('    <%s>%s</%s>%s', $name, $value, $name, "\n");
        }

        $parsed = (new XmlCdrFileParser)->parseString(<<<XML
        <?xml version="1.0"?>
        <cdr>
          <variables>
            <uuid>recording-path-test</uuid>
        {$body}  </variables>
        </cdr>
        XML);

        return $parsed['recording_path'];
    }
}
