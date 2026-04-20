<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCallRoutingPolicyRequest;
use App\Http\Requests\UpdateCallRoutingPolicyRequest;
use App\Http\Resources\CallRoutingPolicyResource;
use App\Models\CallRoutingPolicy;
use App\Models\Organization;
use App\Services\PolicyEvaluator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API controller for managing call routing policies scoped to a organization.
 */
class CallRoutingPolicyController extends Controller
{
    /**
     * List call routing policies for an organization (paginated).
     */
    public function index(Organization $organization)
    {
        $this->authorize('viewAny', CallRoutingPolicy::class);

        return CallRoutingPolicyResource::collection(
            $organization->callRoutingPolicies()->orderBy('priority')->paginate(15)
        );
    }

    /**
     * Create a new call routing policy for an organization.
     */
    public function store(StoreCallRoutingPolicyRequest $request, Organization $organization): JsonResponse
    {
        $this->authorize('create', CallRoutingPolicy::class);

        $policy = $organization->callRoutingPolicies()->create($request->validated());

        return (new CallRoutingPolicyResource($policy))->response()->setStatusCode(201);
    }

    /**
     * Show a single call routing policy.
     */
    public function show(Organization $organization, CallRoutingPolicy $callRoutingPolicy): JsonResponse|CallRoutingPolicyResource
    {
        if ($callRoutingPolicy->organization_id !== $organization->id) {
            return response()->json(['message' => 'Call routing policy not found.'], 404);
        }

        $this->authorize('view', $callRoutingPolicy);

        return new CallRoutingPolicyResource($callRoutingPolicy);
    }

    /**
     * Update an existing call routing policy.
     */
    public function update(UpdateCallRoutingPolicyRequest $request, Organization $organization, CallRoutingPolicy $callRoutingPolicy): JsonResponse|CallRoutingPolicyResource
    {
        if ($callRoutingPolicy->organization_id !== $organization->id) {
            return response()->json(['message' => 'Call routing policy not found.'], 404);
        }

        $this->authorize('update', $callRoutingPolicy);

        $callRoutingPolicy->update($request->validated());

        return new CallRoutingPolicyResource($callRoutingPolicy);
    }

    /**
     * Delete a call routing policy.
     */
    public function destroy(Organization $organization, CallRoutingPolicy $callRoutingPolicy): JsonResponse
    {
        if ($callRoutingPolicy->organization_id !== $organization->id) {
            return response()->json(['message' => 'Call routing policy not found.'], 404);
        }

        $this->authorize('delete', $callRoutingPolicy);

        $callRoutingPolicy->delete();

        return response()->json(null, 204);
    }

    /**
     * Evaluate a specific policy against provided context.
     *
     * Allows external systems to test policy decisions without routing a real call.
     */
    public function evaluate(Request $request, Organization $organization, CallRoutingPolicy $callRoutingPolicy): JsonResponse
    {
        if ($callRoutingPolicy->organization_id !== $organization->id) {
            return response()->json(['message' => 'Call routing policy not found.'], 404);
        }

        $this->authorize('view', $callRoutingPolicy);

        $validated = $request->validate([
            'did' => 'nullable|string',
            'caller_id' => 'nullable|string',
            'time' => 'nullable|date',
            'metadata' => 'nullable|array',
        ]);

        $context = [
            'organization_id' => $organization->id,
            'did' => $validated['did'] ?? '',
            'caller_id' => $validated['caller_id'] ?? '',
            'now' => isset($validated['time']) ? \Carbon\Carbon::parse($validated['time']) : now(),
        ];

        if (isset($validated['metadata'])) {
            $context = array_merge($context, $validated['metadata']);
        }

        $evaluator = app(PolicyEvaluator::class);
        $decision = $evaluator->evaluatePolicy($callRoutingPolicy, $context);

        return response()->json([
            'policy_id' => $callRoutingPolicy->id,
            'policy_name' => $callRoutingPolicy->name,
            'context' => $context,
            'decision' => $decision,
        ]);
    }
}
