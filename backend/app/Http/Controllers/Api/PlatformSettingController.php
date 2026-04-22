<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\ExtensionNumberingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class PlatformSettingController extends Controller
{
    public function __construct(
        protected ExtensionNumberingService $extensionNumberingService,
    ) {}

    public function show(): JsonResponse
    {
        Gate::authorize('platform-admin');

        [$extensionRangeStart, $extensionRangeEnd] = $this->extensionNumberingService->range();

        return response()->json([
            'data' => [
                'organization_domain_suffix' => SystemSetting::platformString(SystemSetting::ORGANIZATION_DOMAIN_SUFFIX, ''),
                'extension_range_start' => $extensionRangeStart,
                'extension_range_end' => $extensionRangeEnd,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        Gate::authorize('platform-admin');

        $validated = $request->validate([
            'organization_domain_suffix' => ['required', 'string', 'max:255'],
            'extension_range_start' => ['required', 'integer', 'min:1'],
            'extension_range_end' => ['required', 'integer', 'min:1', 'gte:extension_range_start'],
        ]);

        $suffix = $this->normalizeSuffix($validated['organization_domain_suffix']);

        SystemSetting::upsertPlatformString(SystemSetting::ORGANIZATION_DOMAIN_SUFFIX, $suffix);
        SystemSetting::upsertPlatformInteger(SystemSetting::EXTENSION_RANGE_START, (int) $validated['extension_range_start']);
        SystemSetting::upsertPlatformInteger(SystemSetting::EXTENSION_RANGE_END, (int) $validated['extension_range_end']);

        return response()->json([
            'data' => [
                'organization_domain_suffix' => $suffix,
                'extension_range_start' => (int) $validated['extension_range_start'],
                'extension_range_end' => (int) $validated['extension_range_end'],
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
