<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\OfficeFeatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfficeFeatureController extends Controller
{
    public function __construct(
        protected OfficeFeatureService $officeFeatureService,
    ) {}

    public function show(Tenant $tenant): JsonResponse
    {
        $this->authorize('view', $tenant);

        return response()->json([
            'data' => $this->officeFeatureService->getFeatures($tenant),
        ]);
    }

    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('update', $tenant);

        $validated = $request->validate([
            'parking_enabled' => ['sometimes', 'boolean'],
            'pickup_enabled' => ['sometimes', 'boolean'],
            'paging_enabled' => ['sometimes', 'boolean'],
            'intercom_enabled' => ['sometimes', 'boolean'],
            'directory_enabled' => ['sometimes', 'boolean'],
        ]);

        return response()->json([
            'data' => $this->officeFeatureService->updateFeatures($tenant, $validated),
        ]);
    }
}
