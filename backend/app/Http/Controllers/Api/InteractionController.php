<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallSession;
use App\Models\Organization;
use App\Services\Interaction\InteractionOverviewService;
use Illuminate\Http\JsonResponse;

class InteractionController extends Controller
{
    public function show(Organization $organization, CallSession $callSession, InteractionOverviewService $overviewService): JsonResponse
    {
        if ($callSession->organization_id !== $organization->id) {
            return response()->json(['message' => 'Interaction not found.'], 404);
        }

        return response()->json([
            'data' => $overviewService->build($organization, $callSession),
        ]);
    }
}
