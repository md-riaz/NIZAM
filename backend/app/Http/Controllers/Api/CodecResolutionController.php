<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bridge;
use App\Models\Gateway;
use App\Models\Organization;
use App\Services\Routing\CodecResolutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Preview the effective codec resolution for a given endpoint + destination + gateway combination.
 *
 * POST /api/v1/organizations/{organization}/codec-resolution/preview
 */
class CodecResolutionController extends Controller
{
    public function __construct(
        protected CodecResolutionService $codecResolution,
    ) {}

    /**
     * Preview codec resolution without executing a real call.
     */
    public function preview(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('view', $organization);

        $validated = $request->validate([
            'endpoint_type' => 'required|string|in:webrtc,sip',
            'bridge_id' => 'nullable|uuid|exists:bridges,id',
            'gateway_id' => 'nullable|uuid|exists:gateways,id',
            'offered_codecs' => 'nullable|array',
            'offered_codecs.*' => 'string|in:'.implode(',', CodecResolutionService::SUPPORTED_CODECS),
        ]);

        $bridge = isset($validated['bridge_id'])
            ? Bridge::where('organization_id', $organization->id)->where('id', $validated['bridge_id'])->first()
            : null;

        $gateway = null;
        if (isset($validated['gateway_id'])) {
            $gateway = Gateway::where('organization_id', $organization->id)->where('id', $validated['gateway_id'])->first();
        } elseif ($bridge?->gateway_id) {
            $gateway = Gateway::find($bridge->gateway_id);
        }

        $result = $this->codecResolution->resolve(
            endpointType: $validated['endpoint_type'],
            bridge: $bridge,
            gateway: $gateway,
            offeredCodecs: $validated['offered_codecs'] ?? [],
        );

        return response()->json([
            'data' => [
                'endpoint_type' => $validated['endpoint_type'],
                'bridge_id' => $bridge?->id,
                'gateway_id' => $gateway?->id,
                'gateway_name' => $gateway?->name,
                'codec_policy' => $bridge?->codec_policy ?? 'default',
                'offered_codecs' => $validated['offered_codecs'] ?? [],
                'effective_codecs' => $result['effective_codecs'],
                'transcoding_required' => $result['transcoding_required'],
                'transcoding_allowed' => $result['transcoding_allowed'],
                'inherit_codec' => $result['inherit_codec'],
                'fs_variable_name' => $result['fs_variable_name'],
                'fs_variable_value' => $result['fs_variable_value'],
                'warnings' => $result['warnings'],
            ],
        ]);
    }
}
