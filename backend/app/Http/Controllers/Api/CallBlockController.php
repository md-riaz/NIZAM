<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CallBlockResource;
use App\Models\CallRoutingPolicy;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CallBlockController extends Controller
{
    public function index(Organization $organization)
    {
        $this->authorize('viewAny', CallRoutingPolicy::class);

        $callBlocks = $organization->callRoutingPolicies()
            ->whereNull('match_destination_type')
            ->whereNull('match_destination_id')
            ->whereNull('no_match_destination_type')
            ->whereNull('no_match_destination_id')
            ->orderBy('priority')
            ->get()
            ->filter(fn (CallRoutingPolicy $policy) => $this->blockedNumber($policy) !== null)
            ->values();

        return CallBlockResource::collection($callBlocks);
    }

    public function store(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('create', CallRoutingPolicy::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'number' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $number = $this->normalizeNumber($validated['number']);

        if ($number === '') {
            throw ValidationException::withMessages([
                'number' => 'The number field format is invalid.',
            ]);
        }

        $policy = $organization->callRoutingPolicies()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'conditions' => [[
                'type' => 'blacklist',
                'params' => ['numbers' => [$number]],
            ]],
            'match_destination_type' => null,
            'match_destination_id' => null,
            'no_match_destination_type' => null,
            'no_match_destination_id' => null,
            'priority' => 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return (new CallBlockResource($policy))->response()->setStatusCode(201);
    }

    public function show(Organization $organization, CallRoutingPolicy $callBlock): JsonResponse|CallBlockResource
    {
        if ($callBlock->organization_id !== $organization->id || $this->blockedNumber($callBlock) === null) {
            return response()->json(['message' => 'Call block not found.'], 404);
        }

        $this->authorize('view', $callBlock);

        return new CallBlockResource($callBlock);
    }

    public function update(Request $request, Organization $organization, CallRoutingPolicy $callBlock): JsonResponse|CallBlockResource
    {
        if ($callBlock->organization_id !== $organization->id || $this->blockedNumber($callBlock) === null) {
            return response()->json(['message' => 'Call block not found.'], 404);
        }

        $this->authorize('update', $callBlock);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'number' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        if (array_key_exists('number', $validated)) {
            $number = $this->normalizeNumber($validated['number']);

            if ($number === '') {
                throw ValidationException::withMessages([
                    'number' => 'The number field format is invalid.',
                ]);
            }

            $validated['conditions'] = [[
                'type' => 'blacklist',
                'params' => ['numbers' => [$number]],
            ]];
        }

        $callBlock->update($validated);

        return new CallBlockResource($callBlock);
    }

    public function destroy(Organization $organization, CallRoutingPolicy $callBlock): JsonResponse
    {
        if ($callBlock->organization_id !== $organization->id || $this->blockedNumber($callBlock) === null) {
            return response()->json(['message' => 'Call block not found.'], 404);
        }

        $this->authorize('delete', $callBlock);

        $callBlock->delete();

        return response()->json(null, 204);
    }

    private function blockedNumber(CallRoutingPolicy $policy): ?string
    {
        foreach ($policy->conditions ?? [] as $condition) {
            if (($condition['type'] ?? null) !== 'blacklist') {
                continue;
            }

            $numbers = $condition['params']['numbers'] ?? [];
            $number = $this->normalizeNumber($numbers[0] ?? null);

            return $number === '' ? null : $number;
        }

        return null;
    }

    private function normalizeNumber(?string $number): string
    {
        return preg_replace('/\D+/', '', (string) $number) ?? '';
    }
}
