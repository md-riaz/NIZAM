<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Http\Resources\ScheduleResource;
use App\Models\Schedule;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class ScheduleController extends Controller
{
    public function index(Tenant $tenant)
    {
        return ScheduleResource::collection(
            $tenant->schedules()->with(['rules', 'breaks', 'exceptions'])->paginate(15)
        );
    }

    public function store(StoreScheduleRequest $request, Tenant $tenant): JsonResponse
    {
        $schedule = $tenant->schedules()->create($request->safe()->except(['rules', 'breaks', 'exceptions']));

        $this->syncScheduleRelations($schedule, $request->validated());

        return (new ScheduleResource($schedule->load(['rules', 'breaks', 'exceptions'])))->response()->setStatusCode(201);
    }

    public function show(Tenant $tenant, Schedule $schedule): JsonResponse|ScheduleResource
    {
        if ($schedule->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Schedule not found.'], 404);
        }

        return new ScheduleResource($schedule->load(['rules', 'breaks', 'exceptions']));
    }

    public function update(UpdateScheduleRequest $request, Tenant $tenant, Schedule $schedule): JsonResponse|ScheduleResource
    {
        if ($schedule->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Schedule not found.'], 404);
        }

        $schedule->update($request->safe()->except(['rules', 'breaks', 'exceptions']));
        $this->syncScheduleRelations($schedule, $request->validated(), replaceExisting: true);

        return new ScheduleResource($schedule->load(['rules', 'breaks', 'exceptions']));
    }

    public function destroy(Tenant $tenant, Schedule $schedule): JsonResponse
    {
        if ($schedule->tenant_id !== $tenant->id) {
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
