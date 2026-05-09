<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Call\OutboundOriginateService;
use App\Services\EslConnectionManager;
use App\Services\Recording\AnsweredRecordingStarter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * API controller for call operations.
 */
class CallController extends Controller
{
    public function __construct(
        protected OutboundOriginateService $outboundOriginateService,
        protected AnsweredRecordingStarter $answeredRecordingStarter,
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
            'caller_id_number' => [
                'prohibited',
            ],
            'did_id' => [
                'nullable',
                'uuid',
                function ($attribute, $value, $fail) use ($organization) {
                    if ($value && ! $organization->dids()->where('id', $value)->where('is_active', true)->exists()) {
                        $fail('The selected outbound DID is invalid for this organization.');
                    }
                },
            ],
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

        $esl = app(EslConnectionManager::class);

        if (! $esl->connect()) {
            return response()->json(['message' => 'Unable to connect to FreeSWITCH.'], 503);
        }

        try {
            $originateString = $this->outboundOriginateService->buildCommand(
                organization: $organization,
                extension: $extension,
                destination: $validated['destination'],
                callerIdName: $validated['caller_id_name'] ?? null,
                didId: $validated['did_id'] ?? null,
                gatewayId: $validated['gateway_id'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'outbound_policy' => [$exception->getMessage()],
                ],
            ], 422);
        }

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
        $esl = app(EslConnectionManager::class);

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

        $esl = app(EslConnectionManager::class);

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

        $esl = app(EslConnectionManager::class);

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

        $response = $validated['action'] === 'start'
            ? $this->answeredRecordingStarter->startForCall($organization->id, $validated['uuid'])
            : $this->answeredRecordingStarter->stopForCall($organization->id, $validated['uuid']);

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

        $esl = app(EslConnectionManager::class);

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
