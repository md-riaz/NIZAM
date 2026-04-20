<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Call\OutboundOriginateService;
use App\Services\EslConnectionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * API controller for call operations.
 */
class CallController extends Controller
{
    public function __construct(
        protected OutboundOriginateService $outboundOriginateService,
    ) {}

    /**
     * Originate a call via FreeSWITCH.
     */
    public function originate(Request $request, Organization $organization): JsonResponse
    {
        Gate::authorize('originate');
        $validated = $request->validate([
            'extension' => 'required|string',
            'destination' => 'required|string',
            'caller_id_name' => 'nullable|string',
            'caller_id_number' => 'nullable|string',
            'gateway_id' => [
                'nullable',
                'uuid',
                function ($attribute, $value, $fail) use ($organization) {
                    if ($value && ! $organization->gateways()->where('id', $value)->where('is_active', true)->exists()) {
                        $fail('The selected gateway is invalid for this organization.');
                    }
                },
            ],
        ]);

        $extension = $organization->extensions()
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

        $gateway = ! empty($validated['gateway_id'])
            ? $organization->gateways()->find($validated['gateway_id'])
            : null;

        $originateString = $this->outboundOriginateService->buildCommand(
            organization: $organization,
            extension: $extension,
            destination: $validated['destination'],
            callerIdName: $validated['caller_id_name'] ?? null,
            callerIdNumber: $validated['caller_id_number'] ?? null,
            gateway: $gateway,
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
    public function status(Organization $organization): JsonResponse
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
    public function hangup(Request $request, Organization $organization): JsonResponse
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
    public function transfer(Request $request, Organization $organization): JsonResponse
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
        $response = $esl->api("uuid_transfer {$validated['uuid']} {$legFlag}{$validated['destination']} XML {$organization->domain}");
        $esl->disconnect();

        return response()->json([
            'message' => 'Transfer command sent.',
            'response' => $response,
        ]);
    }

    /**
     * Toggle recording on a live call by UUID.
     */
    public function toggleRecording(Request $request, Organization $organization): JsonResponse
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
        $recordingPath = "{$basePath}/{$organization->id}/{$validated['uuid']}.wav";

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

    /**
     * Hold or unhold a call by UUID.
     */
    public function hold(Request $request, Organization $organization): JsonResponse
    {
        Gate::authorize('callControl');

        $validated = $request->validate([
            'uuid' => 'required|string|max:255',
            'action' => 'required|string|in:hold,unhold',
        ]);

        $esl = EslConnectionManager::fromConfig();

        if (! $esl->connect()) {
            return response()->json(['message' => 'Unable to connect to FreeSWITCH.'], 503);
        }

        if ($validated['action'] === 'hold') {
            $response = $esl->api("uuid_hold {$validated['uuid']}");
        } else {
            $response = $esl->api("uuid_hold off {$validated['uuid']}");
        }

        $esl->disconnect();

        return response()->json([
            'message' => "Hold {$validated['action']} command sent.",
            'response' => $response,
        ]);
    }
}
