<?php

namespace App\Services\Recording;

use App\Models\CallSession;
use App\Services\Media\FreeSwitchCommandService;

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
        $path = $this->buildPath($organizationId, $callUuid);
        $response = $this->freeSwitchCommandService->execute('uuid_record', [$callUuid, 'start', $path], false);

        return [
            'status' => ($response['executed'] ?? false) === true ? 'started' : 'failed',
            'path' => $path,
            'response' => $response,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function stopForCall(string $organizationId, string $callUuid): array
    {
        $path = $this->buildPath($organizationId, $callUuid);
        $response = $this->freeSwitchCommandService->execute('uuid_record', [$callUuid, 'stop', $path], false);

        return [
            'status' => ($response['executed'] ?? false) === true ? 'stopped' : 'failed',
            'path' => $path,
            'response' => $response,
        ];
    }

    public function buildPath(string $organizationId, string $callUuid): string
    {
        $basePath = config('filesystems.disks.recordings.root', storage_path('app/recordings'));

        return sprintf('%s/%s/%s.wav', rtrim($basePath, '/'), $organizationId, $callUuid);
    }
}
