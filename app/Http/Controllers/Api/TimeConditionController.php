<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TimeCondition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API controller for managing time conditions scoped to a tenant.
 */
class TimeConditionController extends Controller
{
    /**
     * List time conditions for a tenant (paginated).
     */
    public function index(Tenant $tenant): JsonResponse
    {
        $timeConditions = $tenant->timeConditions()->paginate(15);

        return response()->json($timeConditions);
    }

    /**
     * Create a new time condition for a tenant.
     */
    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'conditions' => 'required|array',
            'match_destination_type' => 'nullable|string|required_with:match_destination_id',
            'match_destination_id' => 'nullable|uuid|required_with:match_destination_type',
            'no_match_destination_type' => 'nullable|string|required_with:no_match_destination_id',
            'no_match_destination_id' => 'nullable|uuid|required_with:no_match_destination_type',
            'is_active' => 'boolean',
        ]);

        $timeCondition = $tenant->timeConditions()->create($validated);

        return response()->json($timeCondition, 201);
    }

    /**
     * Show a single time condition.
     */
    public function show(Tenant $tenant, TimeCondition $timeCondition): JsonResponse
    {
        if ($timeCondition->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Time condition not found.'], 404);
        }

        return response()->json($timeCondition);
    }

    /**
     * Update an existing time condition.
     */
    public function update(Request $request, Tenant $tenant, TimeCondition $timeCondition): JsonResponse
    {
        if ($timeCondition->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Time condition not found.'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string',
            'conditions' => 'required|array',
            'match_destination_type' => 'nullable|string|required_with:match_destination_id',
            'match_destination_id' => 'nullable|uuid|required_with:match_destination_type',
            'no_match_destination_type' => 'nullable|string|required_with:no_match_destination_id',
            'no_match_destination_id' => 'nullable|uuid|required_with:no_match_destination_type',
            'is_active' => 'boolean',
        ]);

        $timeCondition->update($validated);

        return response()->json($timeCondition);
    }

    /**
     * Delete a time condition.
     */
    public function destroy(Tenant $tenant, TimeCondition $timeCondition): JsonResponse
    {
        if ($timeCondition->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Time condition not found.'], 404);
        }

        $timeCondition->delete();

        return response()->json(null, 204);
    }
}
