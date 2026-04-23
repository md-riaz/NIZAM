<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;

class TeamController extends Controller
{
    public function index(Organization $organization)
    {
        return TeamResource::collection($organization->teams()->with(['members', 'schedule:id', 'holidayCalendar:id'])->orderByDesc('id')->paginate(15));
    }

    public function store(StoreTeamRequest $request, Organization $organization): JsonResponse
    {
        $validated = $request->validated();
        $team = $organization->teams()->create($request->safe()->except('members'));

        foreach ($validated['members'] ?? [] as $member) {
            $team->members()->create($member);
        }

        return (new TeamResource($team->load(['members', 'schedule:id', 'holidayCalendar:id'])))->response()->setStatusCode(201);
    }

    public function show(Organization $organization, Team $team): JsonResponse|TeamResource
    {
        if ($team->organization_id !== $organization->id) {
            return response()->json(['message' => 'Team not found.'], 404);
        }

        return new TeamResource($team->load(['members', 'schedule:id', 'holidayCalendar:id']));
    }

    public function update(UpdateTeamRequest $request, Organization $organization, Team $team): JsonResponse|TeamResource
    {
        if ($team->organization_id !== $organization->id) {
            return response()->json(['message' => 'Team not found.'], 404);
        }

        $validated = $request->validated();
        $team->update($request->safe()->except('members'));

        if ($request->has('members')) {
            $team->members()->delete();

            foreach ($validated['members'] ?? [] as $member) {
                $team->members()->create($member);
            }
        }

        return new TeamResource($team->load(['members', 'schedule:id', 'holidayCalendar:id']));
    }

    public function destroy(Organization $organization, Team $team): JsonResponse
    {
        if ($team->organization_id !== $organization->id) {
            return response()->json(['message' => 'Team not found.'], 404);
        }

        $team->delete();

        return response()->json(null, 204);
    }
}
