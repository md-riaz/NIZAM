<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\EslConnectionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * API controller for call operations.
 */
class CallController extends Controller
{
    /**
     * Originate a call via FreeSWITCH.
     */
    public function originate(Request $request, Tenant $tenant): JsonResponse
    {
        Gate::authorize('originate');
        $validated = $request->validate([
            'extension' => 'required|string',
            'destination' => 'required|string',
            'caller_id_name' => 'nullable|string',
            'caller_id_number' => 'nullable|string',
        ]);

        $extension = $tenant->extensions()
            ->where('extension', $validated['extension'])
            ->where('is_active', true)
            ->first();

        if (! $extension) {
            return response()->json(['message' => 'Extension not found or inactive.'], 404);
        }

        $esl = EslConnectionManager::fromConfig();

        if (! $esl->connect()) {
            return response()->json(['message' => 'Unable to connect to FreeSWITCH.'], 503);
        }

        $callerIdName = $validated['caller_id_name'] ?? $extension->effective_caller_id_name ?? $extension->directory_first_name;
        $callerIdNumber = $validated['caller_id_number'] ?? $extension->effective_caller_id_number ?? $extension->extension;

        $originateString = sprintf(
            'originate {origination_caller_id_name=%s,origination_caller_id_number=%s}user/%s@%s %s XML %s',
            $callerIdName,
            $callerIdNumber,
            $extension->extension,
            $tenant->domain,
            $validated['destination'],
            $tenant->domain
        );

        $response = $esl->bgapi($originateString);
        $esl->disconnect();

        return response()->json([
            'message' => 'Call originated.',
            'response' => $response,
        ]);
    }

    /**
     * Get active channels/calls status.
     */
    public function status(Tenant $tenant): JsonResponse
    {
        Gate::authorize('viewStatus');
        $esl = EslConnectionManager::fromConfig();

        if (! $esl->connect()) {
            return response()->json(['message' => 'Unable to connect to FreeSWITCH.'], 503);
        }

        $response = $esl->api('show channels as json');
        $esl->disconnect();

        $channels = json_decode($response ?? '{}', true);

        return response()->json([
            'channels' => $channels['rows'] ?? [],
            'count' => $channels['row_count'] ?? 0,
        ]);
    }

    /**
     * Hangup a call by UUID.
     */
    public function hangup(Request $request, Tenant $tenant): JsonResponse
    {
        Gate::authorize('callControl');

        $validated = $request->validate([
            'uuid' => 'required|string|max:255',
            'cause' => 'nullable|string|max:100',
        ]);

        $esl = EslConnectionManager::fromConfig();

        if (! $esl->connect()) {
            return response()->json(['message' => 'Unable to connect to FreeSWITCH.'], 503);
        }

        $cause = $validated['cause'] ?? 'NORMAL_CLEARING';
        $response = $esl->api("uuid_kill {$validated['uuid']} {$cause}");
        $esl->disconnect();

        return response()->json([
            'message' => 'Hangup command sent.',
            'response' => $response,
        ]);
    }

    /**
     * Transfer a call by UUID.
     */
    public function transfer(Request $request, Tenant $tenant): JsonResponse
    {
        Gate::authorize('callControl');

        $validated = $request->validate([
            'uuid' => 'required|string|max:255',
            'destination' => 'required|string|max:255',
            'leg' => 'nullable|string|in:aleg,bleg,both',
        ]);

        $esl = EslConnectionManager::fromConfig();

        if (! $esl->connect()) {
            return response()->json(['message' => 'Unable to connect to FreeSWITCH.'], 503);
        }

        $leg = $validated['leg'] ?? '';
        $legFlag = $leg ? "-{$leg} " : '';
        $response = $esl->api("uuid_transfer {$validated['uuid']} {$legFlag}{$validated['destination']} XML {$tenant->domain}");
        $esl->disconnect();

        return response()->json([
            'message' => 'Transfer command sent.',
            'response' => $response,
        ]);
    }

    /**
     * Toggle recording on a live call by UUID.
     */
    public function toggleRecording(Request $request, Tenant $tenant): JsonResponse
    {
        Gate::authorize('callControl');

        $validated = $request->validate([
            'uuid' => 'required|string|max:255',
            'action' => 'required|string|in:start,stop',
        ]);

        $esl = EslConnectionManager::fromConfig();

        if (! $esl->connect()) {
            return response()->json(['message' => 'Unable to connect to FreeSWITCH.'], 503);
        }

        $basePath = config('filesystems.disks.recordings.root', storage_path('app/recordings'));
        $recordingPath = "{$basePath}/{$tenant->id}/{$validated['uuid']}.wav";

        if ($validated['action'] === 'start') {
            $response = $esl->api("uuid_record {$validated['uuid']} start {$recordingPath}");
        } else {
            $response = $esl->api("uuid_record {$validated['uuid']} stop {$recordingPath}");
        }

        $esl->disconnect();

        return response()->json([
            'message' => "Recording {$validated['action']} command sent.",
            'response' => $response,
        ]);
    }
}
