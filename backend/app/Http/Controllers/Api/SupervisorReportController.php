<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\SupervisorReports\CallSummaryReportService;
use App\Services\SupervisorReports\MissedReturnedCallsReportService;
use App\Services\SupervisorReports\VoicemailsNeedingFollowUpReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupervisorReportController extends Controller
{
    public function __construct(
        protected CallSummaryReportService $callSummaryReportService,
        protected MissedReturnedCallsReportService $missedReturnedCallsReportService,
        protected VoicemailsNeedingFollowUpReportService $voicemailsNeedingFollowUpReportService,
    ) {}

    public function callSummary(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\CallDetailRecord::class);

        return response()->json([
            'data' => $this->callSummaryReportService->generate(
                $organization,
                $this->from($request),
                $this->to($request),
            ),
        ]);
    }

    public function missedReturnedCalls(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\CallDetailRecord::class);

        return response()->json([
            'data' => $this->missedReturnedCallsReportService->generate(
                $organization,
                $this->from($request),
                $this->to($request),
                $this->windowDays($request),
            ),
        ]);
    }

    public function voicemailsNeedingFollowUp(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\Recording::class);

        return response()->json([
            'data' => $this->voicemailsNeedingFollowUpReportService->generate(
                $organization,
                $this->from($request),
                $this->to($request),
                $this->windowDays($request),
            ),
        ]);
    }

    protected function from(Request $request): Carbon
    {
        return Carbon::parse($request->input('date_from', now()->subDays(30)->toDateString()));
    }

    protected function to(Request $request): Carbon
    {
        return Carbon::parse($request->input('date_to', now()->toDateString()));
    }

    protected function windowDays(Request $request): ?int
    {
        return $request->filled('window_days') ? max(1, (int) $request->input('window_days')) : null;
    }
}
