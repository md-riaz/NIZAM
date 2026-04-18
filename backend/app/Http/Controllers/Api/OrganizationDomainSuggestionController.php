<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Organization\OrganizationDomainSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationDomainSuggestionController extends Controller
{
    public function __invoke(Request $request, OrganizationDomainSuggestionService $service): JsonResponse
    {
        $this->authorize('create', Organization::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        return response()->json([
            'data' => $service->suggest($validated['name']),
        ]);
    }
}
