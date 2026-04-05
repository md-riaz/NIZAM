<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WebRtcTlsSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class WebRtcTlsSettingsController extends Controller
{
    public function __construct(
        protected WebRtcTlsSettingsService $settingsService,
    ) {}

    public function index(): JsonResponse
    {
        Gate::authorize('platform-admin');

        return response()->json([
            'status' => 'success',
            'data' => $this->settingsService->getSettings(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        Gate::authorize('platform-admin');

        $validated = $request->validate([
            'webrtc_enabled' => ['required', 'boolean'],
            'active_mode' => ['required', Rule::in([
                WebRtcTlsSettingsService::MODE_TRUSTED_CA,
                WebRtcTlsSettingsService::MODE_SELF_SIGNED,
            ])],
            'trusted_ca_enabled' => ['required', 'boolean'],
            'trusted_ca_cert_dir' => ['required', 'string', 'max:255'],
            'self_signed_enabled' => ['required', 'boolean'],
            'self_signed_cert_dir' => ['required', 'string', 'max:255'],
        ]);

        if (! $validated['trusted_ca_enabled'] && ! $validated['self_signed_enabled']) {
            return response()->json([
                'message' => 'At least one WebRTC TLS mode must remain enabled.',
                'errors' => [
                    'trusted_ca_enabled' => ['At least one WebRTC TLS mode must remain enabled.'],
                    'self_signed_enabled' => ['At least one WebRTC TLS mode must remain enabled.'],
                ],
            ], 422);
        }

        if ($validated['active_mode'] === WebRtcTlsSettingsService::MODE_TRUSTED_CA && ! $validated['trusted_ca_enabled']) {
            return response()->json([
                'message' => 'The active TLS mode must be enabled.',
                'errors' => [
                    'active_mode' => ['The active TLS mode must be enabled.'],
                ],
            ], 422);
        }

        if ($validated['active_mode'] === WebRtcTlsSettingsService::MODE_SELF_SIGNED && ! $validated['self_signed_enabled']) {
            return response()->json([
                'message' => 'The active TLS mode must be enabled.',
                'errors' => [
                    'active_mode' => ['The active TLS mode must be enabled.'],
                ],
            ], 422);
        }

        $settings = $this->settingsService->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'WebRTC TLS settings updated successfully.',
            'data' => $settings,
        ]);
    }
}
