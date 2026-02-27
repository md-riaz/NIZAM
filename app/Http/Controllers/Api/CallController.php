<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\EslConnectionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        if (!$extension) {
            return response()->json(['message' => 'Extension not found or inactive.'], 404);
        }

        $esl = EslConnectionManager::fromConfig();

        if (!$esl->connect()) {
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
        $esl = EslConnectionManager::fromConfig();

        if (!$esl->connect()) {
            return response()->json(['message' => 'Unable to connect to FreeSWITCH.'], 503);
        }

        $response = $esl->api("show channels as json");
        $esl->disconnect();

        $channels = json_decode($response ?? '{}', true);

        return response()->json([
            'channels' => $channels['rows'] ?? [],
            'count' => $channels['row_count'] ?? 0,
        ]);
    }
}
