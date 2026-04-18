<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class PlatformSettingController extends Controller
{
    public function show(): JsonResponse
    {
        Gate::authorize('platform-admin');

        return response()->json([
            'data' => [
                'organization_domain_suffix' => SystemSetting::platformString(SystemSetting::ORGANIZATION_DOMAIN_SUFFIX, ''),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        Gate::authorize('platform-admin');

        $validated = $request->validate([
            'organization_domain_suffix' => ['required', 'string', 'max:255'],
        ]);

        $suffix = $this->normalizeSuffix($validated['organization_domain_suffix']);

        SystemSetting::upsertPlatformString(SystemSetting::ORGANIZATION_DOMAIN_SUFFIX, $suffix);

        return response()->json([
            'data' => [
                'organization_domain_suffix' => $suffix,
            ],
        ]);
    }

    private function normalizeSuffix(string $suffix): string
    {
        $normalized = Str::of($suffix)
            ->trim()
            ->lower()
            ->replaceMatches('/^\.+/', '')
            ->replaceMatches('/\.+$/', '')
            ->value();

        return preg_replace('/[^a-z0-9.-]/', '', $normalized) ?? '';
    }
}
