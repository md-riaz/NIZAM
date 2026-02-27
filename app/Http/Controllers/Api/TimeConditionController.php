<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTimeConditionRequest;
use App\Http\Requests\UpdateTimeConditionRequest;
use App\Http\Resources\TimeConditionResource;
use App\Models\Tenant;
use App\Models\TimeCondition;
use Illuminate\Http\JsonResponse;

/**
 * API controller for managing time conditions scoped to a tenant.
 */
class TimeConditionController extends Controller
{
    /**
     * List time conditions for a tenant (paginated).
     */
    public function index(Tenant $tenant)
    {
        return TimeConditionResource::collection($tenant->timeConditions()->paginate(15));
    }

    /**
     * Create a new time condition for a tenant.
     */
    public function store(StoreTimeConditionRequest $request, Tenant $tenant): JsonResponse
    {
        $timeCondition = $tenant->timeConditions()->create($request->validated());

        return (new TimeConditionResource($timeCondition))->response()->setStatusCode(201);
    }

    /**
     * Show a single time condition.
     */
    public function show(Tenant $tenant, TimeCondition $timeCondition): JsonResponse|TimeConditionResource
    {
        if ($timeCondition->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Time condition not found.'], 404);
        }

        return new TimeConditionResource($timeCondition);
    }

    /**
     * Update an existing time condition.
     */
    public function update(UpdateTimeConditionRequest $request, Tenant $tenant, TimeCondition $timeCondition): JsonResponse|TimeConditionResource
    {
        if ($timeCondition->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Time condition not found.'], 404);
        }

        $timeCondition->update($request->validated());

        return new TimeConditionResource($timeCondition);
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
