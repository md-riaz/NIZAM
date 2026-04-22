<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTimeConditionRequest;
use App\Http\Requests\UpdateTimeConditionRequest;
use App\Http\Resources\TimeConditionResource;
use App\Models\Organization;
use App\Models\TimeCondition;
use Illuminate\Http\JsonResponse;

/**
 * API controller for managing time conditions scoped to a organization.
 */
class TimeConditionController extends Controller
{
    /**
     * List time conditions for an organization (paginated).
     */
    public function index(Organization $organization)
    {
        $this->authorize('viewAny', TimeCondition::class);

        return TimeConditionResource::collection($organization->timeConditions()->orderByDesc('id')->paginate(15));
    }

    /**
     * Create a new time condition for an organization.
     */
    public function store(StoreTimeConditionRequest $request, Organization $organization): JsonResponse
    {
        $this->authorize('create', TimeCondition::class);

        $timeCondition = $organization->timeConditions()->create($request->validated());

        return (new TimeConditionResource($timeCondition))->response()->setStatusCode(201);
    }

    /**
     * Show a single time condition.
     */
    public function show(Organization $organization, TimeCondition $timeCondition): JsonResponse|TimeConditionResource
    {
        if ($timeCondition->organization_id !== $organization->id) {
            return response()->json(['message' => 'Time condition not found.'], 404);
        }

        $this->authorize('view', $timeCondition);

        return new TimeConditionResource($timeCondition);
    }

    /**
     * Update an existing time condition.
     */
    public function update(UpdateTimeConditionRequest $request, Organization $organization, TimeCondition $timeCondition): JsonResponse|TimeConditionResource
    {
        if ($timeCondition->organization_id !== $organization->id) {
            return response()->json(['message' => 'Time condition not found.'], 404);
        }

        $this->authorize('update', $timeCondition);

        $timeCondition->update($request->validated());

        return new TimeConditionResource($timeCondition);
    }

    /**
     * Delete a time condition.
     */
    public function destroy(Organization $organization, TimeCondition $timeCondition): JsonResponse
    {
        if ($timeCondition->organization_id !== $organization->id) {
            return response()->json(['message' => 'Time condition not found.'], 404);
        }

        $this->authorize('delete', $timeCondition);

        $timeCondition->delete();

        return response()->json(null, 204);
    }
}
