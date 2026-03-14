<?php

namespace App\Services\Call;

use App\Models\CallSession;

class CallTraceAnalyzer
{
    /**
     * Generate a structured timeline and metrics from a call session's trace events.
     */
    public function analyze(CallSession $session): array
    {
        $traces = $session->traceEvents()->orderBy('occurred_at', 'asc')->orderBy('id', 'asc')->get();

        $timeline = [];
        $nodeMetrics = [];
        $errors = [];

        $totalDurationMs = 0;

        $previousTrace = null;

        foreach ($traces as $trace) {
            $durationMs = 0;
            if ($previousTrace) {
                // calculate duration since previous trace
                $durationMs = $trace->occurred_at->diffInMilliseconds($previousTrace->occurred_at);
                $totalDurationMs += $durationMs;

                // attribute duration to the node that was active
                if ($previousTrace->node_id) {
                    $nodeId = $previousTrace->node_id;
                    $nodeType = $previousTrace->node_type ?? 'unknown';

                    if (!isset($nodeMetrics[$nodeId])) {
                        $nodeMetrics[$nodeId] = [
                            'node_id' => $nodeId,
                            'node_type' => $nodeType,
                            'duration_ms' => 0,
                            'executions' => 0,
                        ];
                    }

                    $nodeMetrics[$nodeId]['duration_ms'] += $durationMs;
                }
            }

            if ($trace->node_id && in_array($trace->action, ['flow.node.executing', 'flow.node.executed'])) {
                if ($trace->action === 'flow.node.executing' && isset($nodeMetrics[$trace->node_id])) {
                    $nodeMetrics[$trace->node_id]['executions']++;
                } elseif ($trace->action === 'flow.node.executing' && !isset($nodeMetrics[$trace->node_id])) {
                    $nodeMetrics[$trace->node_id] = [
                        'node_id' => $trace->node_id,
                        'node_type' => $trace->node_type,
                        'duration_ms' => 0,
                        'executions' => 1,
                    ];
                }
            }

            $isError = false;
            if (isset($trace->payload['error']) || str_contains($trace->action, 'error') || str_contains($trace->action, 'failed')) {
                $isError = true;
                $errors[] = [
                    'trace_id' => $trace->id,
                    'node_id' => $trace->node_id,
                    'node_type' => $trace->node_type,
                    'action' => $trace->action,
                    'occurred_at' => $trace->occurred_at,
                    'message' => $trace->payload['error'] ?? $trace->payload['message'] ?? 'Unknown error',
                ];
            }

            $timeline[] = [
                'id' => $trace->id,
                'node_id' => $trace->node_id,
                'node_type' => $trace->node_type,
                'action' => $trace->action,
                'payload' => $trace->payload,
                'occurred_at' => $trace->occurred_at,
                'duration_from_previous_ms' => $durationMs,
                'is_error' => $isError,
            ];

            $previousTrace = $trace;
        }

        return [
            'total_duration_ms' => $totalDurationMs,
            'node_metrics' => array_values($nodeMetrics),
            'errors' => $errors,
            'timeline' => $timeline,
        ];
    }
}
