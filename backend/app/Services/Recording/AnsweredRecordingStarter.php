<?php

namespace App\Services\Recording;

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
        $this->ensureDirectoryFor($path);
        $this->applyRecordingVariables($callSession->call_uuid, (string) ($decision['direction'] ?? 'inbound'));

        $response = $this->freeSwitchCommandService->execute('uuid_record', [$callSession->call_uuid, 'start', $path], false);

        $nextVariables = array_merge($variables, [
            'recording_attempted' => true,
            'recording_path' => $path,
        ]);

        if (($response['executed'] ?? false) === true) {
            $nextVariables['recording_started'] = true;
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
            'response' => $response,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function startForCall(string $organizationId, string $callUuid): array
    {
        $session = CallSession::query()->where('call_uuid', $callUuid)->first();
        $path = $this->pathFor($session) ?? $this->buildPath($organizationId, $callUuid);

        $this->ensureDirectoryFor($path);
        $this->applyRecordingVariables($callUuid, 'inbound');

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
        $session = CallSession::query()->where('call_uuid', $callUuid)->first();

        // Stop the recording that was actually started. Recomputing the path
        // gives today's date, so a call recorded either side of midnight — or
        // under any path this code once produced — would be told to stop a file
        // it never started, and would keep recording.
        $path = $this->pathFor($session) ?? $this->buildPath($organizationId, $callUuid);

        $response = $this->freeSwitchCommandService->execute('uuid_record', [$callUuid, 'stop', $path], false);
        $stopped = ($response['executed'] ?? false) === true;

        if ($stopped && $session) {
            $session->forceFill([
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

    /**
     * @param  array<string, string>  $extra
     */
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
        }

        $session->forceFill(['variables' => $variables])->save();
    }

    /**
     * Set the channel variables the recorder reads when it starts.
     */
    protected function applyRecordingVariables(string $callUuid, string $direction): void
    {
        $pairs = [];

        foreach ($this->recordingVariables($direction) as $name => $value) {
            $pairs[] = $name.'='.$value;
        }

        $this->freeSwitchCommandService->execute('uuid_setvar_multi', [$callUuid, implode(';', $pairs)], false);
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
    protected function ensureDirectoryFor(string $path): void
    {
        $directory = dirname($path);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0775, true, true);
        }
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
