<?php

namespace App\Http\Controllers\Api;

use App\Models\SslSetting;
use App\Services\Ssl\SslManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SslController extends Controller
{
    public function __construct(
        protected SslManager $sslManager
    ) {}

    /**
     * Get the current SSL settings and status.
     */
    public function index(): JsonResponse
    {
        $setting = SslSetting::first();

        return response()->json([
            'status' => 'success',
            'data' => $setting ?: [
                'is_enabled' => false,
                'email' => '',
                'domains' => [],
                'status' => 'pending',
                'last_error' => null,
                'last_renewed_at' => null,
                'expires_at' => null,
            ],
        ]);
    }

    /**
     * Update SSL settings and toggle auto-renewal.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'is_enabled' => 'required|boolean',
            'domains' => 'required|array',
            'domains.*' => 'required|string|distinct',
        ]);

        $setting = SslSetting::firstOrNew([]);
        $setting->fill($validated);
        $setting->save();

        return response()->json([
            'status' => 'success',
            'message' => 'SSL settings updated successfully.',
            'data' => $setting,
        ]);
    }

    /**
     * Manually trigger a certificate request/renewal.
     */
    public function requestCertificate(): JsonResponse
    {
        $setting = SslSetting::first();

        if (! $setting) {
            return response()->json([
                'status' => 'error',
                'message' => 'SSL settings not configured.',
            ], 400);
        }

        $success = $this->sslManager->requestCertificate($setting);

        if ($success) {
            return response()->json([
                'status' => 'success',
                'message' => 'Certificate request initiated successfully.',
                'data' => $setting->fresh(),
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Certificate request failed.',
            'error' => $setting->last_error,
        ], 500);
    }
}
