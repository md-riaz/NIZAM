<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\CapabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AdminCapabilityController extends Controller
{
    /**
     * Return a registry of platform-wide capabilities and their statuses.
     */
    public function index(CapabilityService $capabilityService): JsonResponse
    {
        Gate::authorize('platform-admin');

        return response()->json([
            'data' => $capabilityService->getCapabilities()
        ]);
    }
}
