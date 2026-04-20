<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Queue;
use App\Models\Organization;
use App\Services\MetricsService;
use App\Services\WallboardProjectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QueueMetricsController extends Controller
{
    public function __construct(
        protected MetricsService $metricsService,
        protected WallboardProjectionService $wallboardProjectionService,
    ) {}

    /**
     * Get real-time metrics for a specific queue.
     */
    public function realtime(Organization $organization, Queue $queue): JsonResponse
    {
        if ($queue->organization_id !== $organization->id) {
            return response()->json(['message' => 'Queue not found.'], 404);
        }

        return response()->json([
            'data' => $this->metricsService->getRealTimeMetrics($queue),
        ]);
    }

    /**
     * Aggregate metrics for a queue (trigger historical snapshot).
     */
    public function aggregate(Request $request, Organization $organization, Queue $queue): JsonResponse
    {
        if ($queue->organization_id !== $organization->id) {
            return response()->json(['message' => 'Queue not found.'], 404);
        }

        $request->validate([
            'period' => 'sometimes|in:hourly,daily',
        ]);

        $metric = $this->metricsService->aggregateMetrics(
            $queue,
            $request->input('period', 'hourly')
        );

        return response()->json(['data' => $metric]);
    }

    /**
     * Get historical metrics for a queue.
     */
    public function history(Request $request, Organization $organization, Queue $queue): JsonResponse
    {
        if ($queue->organization_id !== $organization->id) {
            return response()->json(['message' => 'Queue not found.'], 404);
        }

        $metrics = $queue->metrics()
            ->when($request->period, fn ($q) => $q->where('period', $request->period))
            ->orderByDesc('period_start')
            ->paginate(15);

        return response()->json($metrics);
    }

    /**
     * Get wallboard data for an organization.
     */
    public function wallboard(Organization $organization): JsonResponse
    {
        return response()->json([
            'data' => $this->wallboardProjectionService->getWallboardData($organization->id),
        ]);
    }

    /**
     * Get agent states summary for an organization.
     */
    public function agentStates(Organization $organization): JsonResponse
    {
        return response()->json([
            'data' => $this->metricsService->getAgentStatesSummary($organization->id),
        ]);
    }
}
