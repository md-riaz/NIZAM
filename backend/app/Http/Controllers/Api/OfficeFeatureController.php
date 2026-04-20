<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\OfficeFeatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfficeFeatureController extends Controller
{
    public function __construct(
        protected OfficeFeatureService $officeFeatureService,
    ) {}

    public function show(Organization $organization): JsonResponse
    {
        $this->authorize('view', $organization);

        return response()->json([
            'data' => $this->officeFeatureService->getFeatures($organization),
        ]);
    }

    public function update(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('update', $organization);

        $validated = $request->validate([
            'parking_enabled' => ['sometimes', 'boolean'],
            'pickup_enabled' => ['sometimes', 'boolean'],
            'paging_enabled' => ['sometimes', 'boolean'],
            'intercom_enabled' => ['sometimes', 'boolean'],
            'directory_enabled' => ['sometimes', 'boolean'],
        ]);

        return response()->json([
            'data' => $this->officeFeatureService->updateFeatures($organization, $validated),
        ]);
    }
}
