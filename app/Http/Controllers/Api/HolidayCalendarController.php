<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHolidayCalendarRequest;
use App\Http\Requests\UpdateHolidayCalendarRequest;
use App\Http\Resources\HolidayCalendarResource;
use App\Models\HolidayCalendar;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class HolidayCalendarController extends Controller
{
    public function index(Tenant $tenant)
    {
        return HolidayCalendarResource::collection(
            $tenant->holidayCalendars()->with('holidays')->paginate(15)
        );
    }

    public function store(StoreHolidayCalendarRequest $request, Tenant $tenant): JsonResponse
    {
        $calendar = $tenant->holidayCalendars()->create($request->safe()->except(['holidays']));

        foreach ($request->validated()['holidays'] ?? [] as $holiday) {
            $calendar->holidays()->create($holiday);
        }

        return (new HolidayCalendarResource($calendar->load('holidays')))->response()->setStatusCode(201);
    }

    public function show(Tenant $tenant, HolidayCalendar $holidayCalendar): JsonResponse|HolidayCalendarResource
    {
        if ($holidayCalendar->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Holiday calendar not found.'], 404);
        }

        return new HolidayCalendarResource($holidayCalendar->load('holidays'));
    }

    public function update(UpdateHolidayCalendarRequest $request, Tenant $tenant, HolidayCalendar $holidayCalendar): JsonResponse|HolidayCalendarResource
    {
        if ($holidayCalendar->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Holiday calendar not found.'], 404);
        }

        $holidayCalendar->update($request->safe()->except(['holidays']));

        if ($request->has('holidays')) {
            $holidayCalendar->holidays()->delete();

            foreach ($request->validated()['holidays'] ?? [] as $holiday) {
                $holidayCalendar->holidays()->create($holiday);
            }
        }

        return new HolidayCalendarResource($holidayCalendar->load('holidays'));
    }

    public function destroy(Tenant $tenant, HolidayCalendar $holidayCalendar): JsonResponse
    {
        if ($holidayCalendar->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Holiday calendar not found.'], 404);
        }

        $holidayCalendar->delete();

        return response()->json(null, 204);
    }
}
