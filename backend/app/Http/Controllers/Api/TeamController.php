<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class TeamController extends Controller
{
    public function index(Tenant $tenant)
    {
        return TeamResource::collection($tenant->teams()->with('members')->paginate(15));
    }

    public function store(StoreTeamRequest $request, Tenant $tenant): JsonResponse
    {
        $team = $tenant->teams()->create($request->safe()->except('members'));

        foreach ($request->validated()['members'] ?? [] as $member) {
            $team->members()->create($member);
        }

        return (new TeamResource($team->load('members')))->response()->setStatusCode(201);
    }

    public function show(Tenant $tenant, Team $team): JsonResponse|TeamResource
    {
        if ($team->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Team not found.'], 404);
        }

        return new TeamResource($team->load('members'));
    }

    public function update(UpdateTeamRequest $request, Tenant $tenant, Team $team): JsonResponse|TeamResource
    {
        if ($team->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Team not found.'], 404);
        }

        $team->update($request->safe()->except('members'));

        if ($request->has('members')) {
            $team->members()->delete();

            foreach ($request->validated()['members'] ?? [] as $member) {
                $team->members()->create($member);
            }
        }

        return new TeamResource($team->load('members'));
    }

    public function destroy(Tenant $tenant, Team $team): JsonResponse
    {
        if ($team->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Team not found.'], 404);
        }

        $team->delete();

        return response()->json(null, 204);
    }
}
