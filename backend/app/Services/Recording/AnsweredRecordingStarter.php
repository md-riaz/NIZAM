<?php

namespace App\Services\Recording;

use App\Models\CallDeliveryAttempt;
use App\Models\CallSession;
use App\Services\Media\FreeSwitchCommandService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class AnsweredRecordingStarter
{
    public function __construct(
        protected FreeSwitchCommandService $freeSwitchCommandService,
    ) {}

    /**
     * @param  array<string, mixed>  $decision
     * @return array<string, mixed>
     */
    public function start(CallSession $callSession, array $decision): array
    {
        if (! ($decision['should_record'] ?? false)) {
            return [
                'status' => 'skipped',
                'reason' => 'policy_disabled',
            ];
        }

        $variables = $callSession->variables ?? [];

        if (($variables['recording_started'] ?? false) === true) {
            return [
                'status' => 'skipped',
                'reason' => 'already_recording',
                'path' => $variables['recording_path'] ?? $this->buildPath($callSession->organization_id, $callSession->call_uuid),
            ];
        }

        $path = $this->buildPath($callSession->organization_id, $callSession->call_uuid);

        if (! $this->ensureDirectoryFor($path)) {
            // Starting the recorder now would report success for a file
            // FreeSWITCH cannot create, and the CDR would point at audio that
            // does not exist.
            $callSession->forceFill([
                'variables' => array_merge($variables, [
                    'recording_attempted' => true,
                    'recording_path' => $path,
                ]),
            ])->save();

            return [
                'status' => 'failed',
                'reason' => 'recording_directory_unavailable',
                'path' => $path,
            ];
        }

        $variablesApplied = $this->applyRecordingVariables($callSession->call_uuid, $this->directionFor($decision));

        $response = $this->freeSwitchCommandService->execute('uuid_record', [$callSession->call_uuid, 'start', $path], false);

        $nextVariables = array_merge($variables, [
            'recording_attempted' => true,
            'recording_path' => $path,
        ]);

        if (! $variablesApplied) {
            $nextVariables['recording_variables_applied'] = false;
        }

        if (($response['executed'] ?? false) === true) {
            $nextVariables['recording_started'] = true;
            $nextVariables['recording_created'] = true;
        }

        $callSession->forceFill([
            'variables' => $nextVariables,
        ])->save();

        if (($response['executed'] ?? false) !== true) {
            return [
                'status' => 'failed',
                'reason' => $response['error'] ?? 'recording_start_failed',
                'path' => $path,
                'response' => $response,
            ];
        }

        return [
            'status' => 'started',
            'path' => $path,
            'variables_applied' => $variablesApplied,
            'response' => $response,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function startForCall(string $organizationId, string $callUuid, string $direction = 'inbound'): array
    {
        $session = $this->sessionFor($organizationId, $callUuid);
        $path = $this->pathFor($session) ?? $this->buildPath($organizationId, $callUuid);

        if (! $this->ensureDirectoryFor($path)) {
            return [
                'status' => 'failed',
                'reason' => 'recording_directory_unavailable',
                'path' => $path,
            ];
        }

        // The caller rarely knows the direction — the call control API is given
        // a UUID and nothing else — but the session worked it out when the call
        // was answered, so prefer what it recorded.
        $this->applyRecordingVariables(
            $callUuid,
            (string) (data_get($session?->variables, 'recording_context.direction') ?: $direction)
        );

        $response = $this->freeSwitchCommandService->execute('uuid_record', [$callUuid, 'start', $path], false);
        $started = ($response['executed'] ?? false) === true;

        // Recording started by hand has to be visible to the automatic path,
        // or a policy decision arriving afterwards starts a second recorder
        // against the same call.
        $this->rememberOnSession($session, $path, $started);

        return [
            'status' => $started ? 'started' : 'failed',
            'path' => $path,
            'response' => $response,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function stopForCall(string $organizationId, string $callUuid): array
    {
        $session = $this->sessionFor($organizationId, $callUuid);

        // Stop the recording that was actually started. Recomputing the path
        // gives today's date, so a call recorded either side of midnight — or
        // under any path this code once produced — would be told to stop a file
        // it never started, and would keep recording.
        $path = $this->pathFor($session) ?? $this->buildPath($organizationId, $callUuid);

        $response = $this->freeSwitchCommandService->execute('uuid_record', [$callUuid, 'stop', $path], false);
        $stopped = ($response['executed'] ?? false) === true;

        if ($stopped && $session) {
            $session->forceFill([
                // `recording_created` is deliberately left alone: the recorder
                // is no longer running, but the audio it wrote is still there
                // and still has to reach the CDR and the archive.
                'variables' => array_merge($session->variables ?? [], ['recording_started' => false]),
            ])->save();
        }

        return [
            'status' => $stopped ? 'stopped' : 'failed',
            'path' => $path,
            'response' => $response,
        ];
    }

    /**
     * The path a recording was actually started at, if one was.
     */
    public function pathFor(?CallSession $session): ?string
    {
        $path = $session?->variables['recording_path'] ?? null;

        return is_string($path) && $path !== '' ? $path : null;
    }

    protected function rememberOnSession(?CallSession $session, string $path, bool $started): void
    {
        if (! $session) {
            return;
        }

        $variables = array_merge($session->variables ?? [], [
            'recording_attempted' => true,
            'recording_path' => $path,
        ]);

        if ($started) {
            $variables['recording_started'] = true;
            $variables['recording_created'] = true;
        }

        $session->forceFill(['variables' => $variables])->save();
    }

    /**
     * The call session a UUID belongs to.
     *
     * A supervisor recording an answered call acts on the B-leg UUID, which is
     * the one surfaced to clients as `winner.leg_uuid` and the one the call
     * control API accepts. That UUID is on the delivery attempt, not on the
     * session, so looking only at `call_uuid` would find nothing and the start
     * would go unrecorded — leaving the CDR with no path and a later stop
     * recomputing a different one.
     */
    protected function sessionFor(string $organizationId, string $callUuid): ?CallSession
    {
        $session = CallSession::query()
            ->where('organization_id', $organizationId)
            ->where('call_uuid', $callUuid)
            ->first();

        if ($session) {
            return $session;
        }

        return CallDeliveryAttempt::query()
            ->where('freeswitch_leg_uuid', $callUuid)
            ->whereHas('callSession', fn ($query) => $query->where('organization_id', $organizationId))
            ->first()?->callSession;
    }

    /**
     * @param  array<string, mixed>  $decision
     */
    protected function directionFor(array $decision): string
    {
        return ($decision['direction'] ?? null) === 'outbound' ? 'outbound' : 'inbound';
    }

    /**
     * Set the channel variables the recorder reads when it starts.
     *
     * A failure here is reported but does not stop the recorder. These
     * variables shape the recording — stereo layout, behaviour across a
     * transfer — and a recording without them is worse than one with them, but
     * it is far better than no recording at all, which is what aborting would
     * produce. If the socket is genuinely down, `uuid_record` fails on the next
     * line and the start is reported failed on its own account.
     */
    protected function applyRecordingVariables(string $callUuid, string $direction): bool
    {
        $pairs = [];

        foreach ($this->recordingVariables($direction) as $name => $value) {
            $pairs[] = $name.'='.$value;
        }

        $response = $this->freeSwitchCommandService->execute('uuid_setvar_multi', [$callUuid, implode(';', $pairs)], false);

        return ($response['executed'] ?? false) === true;
    }

    /**
     * Where the recording for this call goes.
     *
     * Partitioned by organization and date, which is the layout FusionPBX and
     * FS PBX both use: a flat directory per organization accumulates every call
     * ever recorded, and directory listings degrade long before disk does.
     *
     * The path is deterministic so it can be recomputed, but it is also stored
     * on the call session when recording starts — `uuid_record` writes it to no
     * channel variable, so that record is the only thing that knows where the
     * audio went.
     */
    public function buildPath(string $organizationId, string $callUuid, ?Carbon $startedAt = null): string
    {
        $basePath = config('filesystems.disks.recordings.root', storage_path('app/recordings'));
        $startedAt ??= Carbon::now();

        return sprintf(
            '%s/%s/%s/%s.wav',
            rtrim($basePath, '/'),
            $organizationId,
            $startedAt->format('Y/m/d'),
            $callUuid
        );
    }

    /**
     * Create the directory before asking FreeSWITCH to write into it.
     *
     * The recorder fails silently when the tree does not exist, which is why
     * every reference dialplan calls `mkdir` immediately before recording.
     */
    protected function ensureDirectoryFor(string $path): bool
    {
        $directory = dirname($path);

        if (File::isDirectory($directory)) {
            return true;
        }

        // The fourth argument swallows the warning; the return value is the
        // only report of what happened, and the caller needs it — FreeSWITCH
        // writes nothing into a directory that is not there, and says so in a
        // way this code cannot see.
        if (! File::makeDirectory($directory, 0775, true, true)) {
            // Lost a race with another leg of the same call, most likely.
            clearstatcache(true, $directory);

            return File::isDirectory($directory);
        }

        // makeDirectory's mode is subject to the process umask, which commonly
        // clears the group write bit. FreeSWITCH runs as a different user in
        // the same group and has to write the audio into here.
        @chmod($directory, 0775);

        return true;
    }

    /**
     * The channel variables FreeSWITCH needs set before the recorder starts.
     *
     * These are the ones every reference implementation sets, and each answers a
     * specific failure:
     *
     * - `recording_follow_transfer` keeps the recording attached to the call
     *   across a transfer instead of stopping at it.
     * - `record_stereo` puts the two parties on separate channels, so who said
     *   what survives into the file. Outbound calls swap the channels, because
     *   which leg is "read" is reversed relative to an inbound call.
     * - `record_append` stops a second start against the same file truncating
     *   what is already there, which is what a transfer would otherwise do.
     *
     * @return array<string, string>
     */
    protected function recordingVariables(string $direction): array
    {
        return [
            'recording_follow_transfer' => 'true',
            'record_append' => 'true',
            $direction === 'outbound' ? 'record_stereo_swap' : 'record_stereo' => 'true',
        ];
    }
}
