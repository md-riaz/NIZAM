<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallSession;
use App\Models\Tenant;
use App\Services\Interaction\InteractionOverviewService;
use Illuminate\Http\JsonResponse;

class InteractionController extends Controller
{
    public function show(Tenant $tenant, CallSession $callSession, InteractionOverviewService $overviewService): JsonResponse
    {
        if ($callSession->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Interaction not found.'], 404);
        }

        return response()->json([
            'data' => $overviewService->build($tenant, $callSession),
        ]);
    }
}
