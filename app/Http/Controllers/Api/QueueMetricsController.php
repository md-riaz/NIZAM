<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Queue;
use App\Models\Tenant;
use App\Services\MetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QueueMetricsController extends Controller
{
    public function __construct(
        protected MetricsService $metricsService
    ) {}

    /**
     * Get real-time metrics for a specific queue.
     */
    public function realtime(Tenant $tenant, Queue $queue): JsonResponse
    {
        if ($queue->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Queue not found.'], 404);
        }

        return response()->json([
            'data' => $this->metricsService->getRealTimeMetrics($queue),
        ]);
    }

    /**
     * Aggregate metrics for a queue (trigger historical snapshot).
     */
    public function aggregate(Request $request, Tenant $tenant, Queue $queue): JsonResponse
    {
        if ($queue->tenant_id !== $tenant->id) {
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
    public function history(Request $request, Tenant $tenant, Queue $queue): JsonResponse
    {
        if ($queue->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Queue not found.'], 404);
        }

        $metrics = $queue->metrics()
            ->when($request->period, fn ($q) => $q->where('period', $request->period))
            ->orderByDesc('period_start')
            ->paginate(15);

        return response()->json($metrics);
    }

    /**
     * Get wallboard data for a tenant.
     */
    public function wallboard(Tenant $tenant): JsonResponse
    {
        return response()->json([
            'data' => $this->metricsService->getWallboardData($tenant->id),
        ]);
    }

    /**
     * Get agent states summary for a tenant.
     */
    public function agentStates(Tenant $tenant): JsonResponse
    {
        return response()->json([
            'data' => $this->metricsService->getAgentStatesSummary($tenant->id),
        ]);
    }
}
