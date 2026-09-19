<?php

namespace App\Services\Recording;

use App\Models\CallSession;

/**
 * Works out where a call's audio was written.
 *
 * FreeSWITCH has no single channel variable holding the recording path. Which
 * variable carries it depends on which application started the recorder, so
 * every reference implementation walks a chain of candidates rather than
 * reading one name. This is that chain, in the same order FusionPBX's CDR
 * handler uses:
 *
 * - `record_path` + `record_name` — set by the dialplan's `record_session`,
 *   the usual path for a policy-recorded call.
 * - `cc_record_filename` — set by mod_callcenter for a queued call.
 * - `last_arg` when `last_app` is `record_session` — the recorder was the last
 *   application to run and left its argument behind.
 * - `sofia_record_file` — set by `uuid_record` when it is issued through the
 *   dialplan's `record` application.
 * - `conference_recording` — set by mod_conference.
 *
 * NIZAM adds one more at the end. A recording started with `uuid_record` over
 * the event socket — which is how this application starts every one of them —
 * appears in none of those: nothing writes the path back to the channel. The
 * call session is where that path is stored when the recorder starts, and for
 * those calls it is the only thing that knows.
 */
class RecordingPathResolver
{
    /**
     * @param  array<string, mixed>  $event  A raw ESL event.
     */
    public function resolve(array $event, ?CallSession $callSession = null): ?string
    {
        return $this->fromChannelVariables($event)
            ?? $this->fromCallSession($callSession);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function fromChannelVariables(array $event): ?string
    {
        $directory = $this->value($event, 'variable_record_path');
        $name = $this->value($event, 'variable_record_name');

        if ($directory !== null && $name !== null) {
            return rtrim($directory, '/').'/'.$name;
        }

        if ($filename = $this->value($event, 'variable_cc_record_filename')) {
            return $filename;
        }

        if ($this->value($event, 'variable_last_app') === 'record_session') {
            if ($argument = $this->value($event, 'variable_last_arg')) {
                return $argument;
            }
        }

        return $this->value($event, 'variable_sofia_record_file')
            ?? $this->value($event, 'variable_conference_recording')
            // The variable this code read before the chain existed. Nothing in
            // FreeSWITCH is known to set it, but an integration or a dialplan
            // outside this repository may, and dropping a candidate that used
            // to work is not worth the tidiness.
            ?? $this->value($event, 'variable_record_file_path');
    }

    public function fromCallSession(?CallSession $callSession): ?string
    {
        if (! $callSession) {
            return null;
        }

        // Only report a path the recorder actually wrote to. An attempt that
        // failed to start leaves the path behind as a record of what was
        // tried; putting it on the CDR would promise audio that does not
        // exist.
        //
        // The question is whether a recording was ever created, not whether
        // one is running now. A supervisor who stops a recording before the
        // call ends leaves `recording_started` false, and the file they
        // recorded still has to reach the CDR and the archive.
        if ((($callSession->variables['recording_created'] ?? false)) !== true) {
            return null;
        }

        $path = $callSession->variables['recording_path'] ?? null;

        return is_string($path) && trim($path) !== '' ? trim($path) : null;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    protected function value(array $event, string $key): ?string
    {
        $value = $event[$key] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
