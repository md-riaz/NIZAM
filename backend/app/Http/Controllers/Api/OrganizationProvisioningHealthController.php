<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Organization\OrganizationProvisioningHealthService;
use Illuminate\Http\JsonResponse;

class OrganizationProvisioningHealthController extends Controller
{
    public function __construct(
        protected OrganizationProvisioningHealthService $organizationProvisioningHealthService,
    ) {}

    public function __invoke(Organization $organization): JsonResponse
    {
        $this->authorize('view', $organization);

        return response()->json([
            'data' => $this->organizationProvisioningHealthService->evaluate($organization),
        ]);
    }
}
