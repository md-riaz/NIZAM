<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Http\Resources\ScheduleResource;
use App\Models\Schedule;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;

class ScheduleController extends Controller
{
    public function index(Organization $organization)
    {
        return ScheduleResource::collection(
            $organization->schedules()->with(['rules', 'breaks', 'exceptions'])->orderByDesc('id')->paginate(15)
        );
    }

    public function store(StoreScheduleRequest $request, Organization $organization): JsonResponse
    {
        $schedule = $organization->schedules()->create($request->safe()->except(['rules', 'breaks', 'exceptions']));

        $this->syncScheduleRelations($schedule, $request->validated());

        return (new ScheduleResource($schedule->load(['rules', 'breaks', 'exceptions'])))->response()->setStatusCode(201);
    }

    public function show(Organization $organization, Schedule $schedule): JsonResponse|ScheduleResource
    {
        if ($schedule->organization_id !== $organization->id) {
            return response()->json(['message' => 'Schedule not found.'], 404);
        }

        return new ScheduleResource($schedule->load(['rules', 'breaks', 'exceptions']));
    }

    public function update(UpdateScheduleRequest $request, Organization $organization, Schedule $schedule): JsonResponse|ScheduleResource
    {
        if ($schedule->organization_id !== $organization->id) {
            return response()->json(['message' => 'Schedule not found.'], 404);
        }

        $schedule->update($request->safe()->except(['rules', 'breaks', 'exceptions']));
        $this->syncScheduleRelations($schedule, $request->validated(), replaceExisting: true);

        if ($request->hasAny(['rules', 'breaks', 'exceptions'])) {
            app(\App\Services\OrganizationManifestBuilder::class)->buildAndActivate($schedule->organization);
        }

        return new ScheduleResource($schedule->load(['rules', 'breaks', 'exceptions']));
    }

    public function destroy(Organization $organization, Schedule $schedule): JsonResponse
    {
        if ($schedule->organization_id !== $organization->id) {
            return response()->json(['message' => 'Schedule not found.'], 404);
        }

        $schedule->delete();

        return response()->json(null, 204);
    }

    protected function syncScheduleRelations(Schedule $schedule, array $payload, bool $replaceExisting = false): void
    {
        if ($replaceExisting) {
            $schedule->rules()->delete();
            $schedule->breaks()->delete();
            $schedule->exceptions()->delete();
        }

        foreach ($payload['rules'] ?? [] as $rule) {
            $schedule->rules()->create($rule);
        }

        foreach ($payload['breaks'] ?? [] as $break) {
            $schedule->breaks()->create($break);
        }

        foreach ($payload['exceptions'] ?? [] as $exception) {
            $schedule->exceptions()->create($exception);
        }
    }
}
