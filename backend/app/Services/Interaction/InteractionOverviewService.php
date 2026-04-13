<?php

namespace App\Services\Interaction;

use App\Models\CallDeliveryAttempt;
use App\Models\CallEventLog;
use App\Models\CallSession;
use App\Models\CallTraceEvent;
use App\Models\EndpointBinding;
use App\Models\PushNotificationLog;
use App\Models\Tenant;
use App\Services\Call\CallTraceAnalyzer;

class InteractionOverviewService
{
    public function __construct(
        protected InteractionTimelineBuilder $timelineBuilder,
        protected CallTraceAnalyzer $traceAnalyzer,
    ) {}

    public function build(Tenant $tenant, CallSession $session): array
    {
        abort_unless($session->tenant_id === $tenant->id, 404);

        $session->load([
            'events',
            'traceEvents',
            'deliveryAttempts.endpointBinding',
            'winningDeliveryAttempt.endpointBinding',
            'pushNotificationLogs.endpointBinding',
        ]);

        $analysis = $this->traceAnalyzer->analyze($session);
        $timeline = $this->timelineBuilder->build($this->buildTimelineEvents($session));
        $rawWinner = $analysis['winner'] ?? null;
        $resolvedWinningAttempt = $rawWinner['attempt'] ?? null;

        return [
            'id' => $session->id,
            'call_uuid' => $session->call_uuid,
            'state' => $session->state,
            'started_at' => $session->started_at?->toIso8601String(),
            'ended_at' => $session->ended_at?->toIso8601String(),
            'summary' => [
                'status_label' => $this->humanizeState($session->state),
                'outcome_label' => $this->buildOutcomeLabel($rawWinner, $session),
                'delivery_attempt_count' => $session->deliveryAttempts->count(),
                'push_notification_count' => $session->pushNotificationLogs->count(),
                'call_event_count' => $session->events->count(),
                'trace_event_count' => $session->traceEvents->count(),
                'timeline_event_count' => count($timeline),
                'has_errors' => ($analysis['errors'] ?? []) !== [],
                'total_trace_duration_ms' => $analysis['total_duration_ms'] ?? 0,
            ],
            'timeline' => $timeline,
            'delivery_attempts' => $this->normalizeDeliveryAttempts($analysis['delivery_attempts'] ?? []),
            'push_notification_logs' => $this->normalizePushNotificationLogs($analysis['push_notification_logs'] ?? []),
            'winning_attempt' => $rawWinner === null ? null : [
                'attempt_id' => $rawWinner['attempt_id'] ?? null,
                'leg_uuid' => $rawWinner['leg_uuid'] ?? null,
                'committed_at' => $this->normalizeTimestamp(
                    data_get($rawWinner, 'committed_at')
                        ?? data_get($resolvedWinningAttempt, 'answered_at')
                        ?? data_get($resolvedWinningAttempt, 'updated_at')
                ),
                'attempt' => $this->normalizeDeliveryAttempt($resolvedWinningAttempt),
            ],
            'trace_analysis' => [
                'errors' => $this->normalizeTraceErrors($analysis['errors'] ?? []),
                'node_metrics' => $analysis['node_metrics'] ?? [],
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $attempts
     * @return array<int, array<string, mixed>>
     */
    private function normalizeDeliveryAttempts(array $attempts): array
    {
        return array_map(fn (array $attempt): array => $this->normalizeDeliveryAttempt($attempt), $attempts);
    }

    /**
     * @param  array<string, mixed>|null  $attempt
     * @return array<string, mixed>|null
     */
    private function normalizeDeliveryAttempt(?array $attempt): ?array
    {
        if ($attempt === null) {
            return null;
        }

        foreach (['started_at', 'answered_at', 'ended_at', 'created_at', 'updated_at'] as $field) {
            if (array_key_exists($field, $attempt)) {
                $attempt[$field] = $this->normalizeTimestamp($attempt[$field]);
            }
        }

        return $attempt;
    }

    /**
     * @param  array<int, array<string, mixed>>  $logs
     * @return array<int, array<string, mixed>>
     */
    private function normalizePushNotificationLogs(array $logs): array
    {
        return array_map(function (array $log): array {
            foreach (['sent_at', 'created_at', 'updated_at'] as $field) {
                if (array_key_exists($field, $log)) {
                    $log[$field] = $this->normalizeTimestamp($log[$field]);
                }
            }

            return $log;
        }, $logs);
    }

    /**
     * @param  array<int, array<string, mixed>>  $errors
     * @return array<int, array<string, mixed>>
     */
    private function normalizeTraceErrors(array $errors): array
    {
        return array_map(function (array $error): array {
            if (array_key_exists('occurred_at', $error)) {
                $error['occurred_at'] = $this->normalizeTimestamp($error['occurred_at']);
            }

            return $error;
        }, $errors);
    }

    /**
     * @return array<int, array{type: string, occurred_at: \Carbon\CarbonInterface, details: array<string, mixed>}>
     */
    private function buildTimelineEvents(CallSession $session): array
    {
        $events = [];

        foreach ($session->events as $event) {
            if (! $event->occurred_at) {
                continue;
            }

            $events[] = [
                'type' => $event->event_type,
                'occurred_at' => $event->occurred_at,
                'details' => [
                    'label' => $this->describeCallEvent($event),
                    'source' => $event->source,
                    'payload' => $event->payload ?? [],
                ],
            ];
        }

        foreach ($session->pushNotificationLogs as $pushLog) {
            if (! $pushLog->sent_at) {
                continue;
            }

            $events[] = [
                'type' => sprintf('push.%s', $pushLog->status),
                'occurred_at' => $pushLog->sent_at,
                'details' => [
                    'label' => $this->describePushNotification($pushLog),
                    'push_type' => $pushLog->push_type,
                    'status' => $pushLog->status,
                    'endpoint_type' => $pushLog->endpointBinding?->type,
                    'platform' => $pushLog->endpointBinding?->platform,
                    'response_payload' => $pushLog->response_payload ?? [],
                ],
            ];
        }

        foreach ($session->deliveryAttempts as $attempt) {
            if (! $attempt->started_at) {
                continue;
            }

            $events[] = [
                'type' => sprintf('delivery.%s.%s', $attempt->attempt_type, $attempt->status),
                'occurred_at' => $attempt->started_at,
                'details' => [
                    'label' => $this->describeDeliveryAttempt($attempt),
                    'attempt_type' => $attempt->attempt_type,
                    'status' => $attempt->status,
                    'endpoint_type' => $attempt->endpointBinding?->type,
                    'platform' => $attempt->endpointBinding?->platform,
                    'failure_reason' => $attempt->failure_reason,
                    'metadata' => $attempt->metadata ?? [],
                ],
            ];
        }

        foreach ($session->traceEvents as $trace) {
            if (! $this->shouldIncludeTraceInTimeline($trace) || ! $trace->occurred_at) {
                continue;
            }

            $events[] = [
                'type' => $trace->action,
                'occurred_at' => $trace->occurred_at,
                'details' => [
                    'label' => $this->describeTraceEvent($trace),
                    'node_id' => $trace->node_id,
                    'node_type' => $trace->node_type,
                    'payload' => $trace->payload ?? [],
                ],
            ];
        }

        return $events;
    }

    private function buildOutcomeLabel(?array $winner, CallSession $session): string
    {
        $winningAttempt = $session->deliveryAttempts->firstWhere('id', $winner['attempt_id'] ?? null)
            ?? $session->winningDeliveryAttempt;

        if ($winningAttempt instanceof CallDeliveryAttempt) {
            $endpointDescription = $this->describeEndpoint($winningAttempt->endpointBinding);

            return sprintf(
                'Answered via %s to %s',
                $this->humanizeValue($winningAttempt->attempt_type),
                $endpointDescription,
            );
        }

        return match ($session->state) {
            'bridged' => 'Call was bridged successfully',
            'completed' => 'Call completed',
            'missed' => 'Call was missed',
            default => 'Call outcome is still being processed',
        };
    }

    private function describeCallEvent(CallEventLog $event): string
    {
        return match ($event->event_type) {
            CallEventLog::EVENT_CALL_CREATED => 'Call created',
            CallEventLog::EVENT_CALL_ANSWERED => 'Call answered',
            CallEventLog::EVENT_CALL_BRIDGED => 'Call bridged',
            CallEventLog::EVENT_CALL_HANGUP => 'Call ended',
            CallEventLog::EVENT_VOICEMAIL_RECEIVED => 'Voicemail received',
            default => $this->humanizeDotSeparated($event->event_type),
        };
    }

    private function describePushNotification(PushNotificationLog $pushLog): string
    {
        $endpointDescription = $this->describeEndpoint($pushLog->endpointBinding);

        return sprintf(
            '%s push notification sent to %s',
            ucfirst($pushLog->push_type),
            $endpointDescription,
        );
    }

    private function describeDeliveryAttempt(CallDeliveryAttempt $attempt): string
    {
        $endpointDescription = $this->describeEndpoint($attempt->endpointBinding);
        $status = $attempt->status === CallDeliveryAttempt::STATUS_WON ? 'Answered' : ucfirst($this->humanizeValue($attempt->status));

        return sprintf(
            '%s via %s to %s',
            $status,
            $this->humanizeValue($attempt->attempt_type),
            $endpointDescription,
        );
    }

    private function shouldIncludeTraceInTimeline(CallTraceEvent $trace): bool
    {
        return in_array($trace->action, [
            'flow.node.executing',
            'delivery.plan.created',
            'delivery.failed',
            'flow.error',
        ], true);
    }

    private function describeTraceEvent(CallTraceEvent $trace): string
    {
        return match ($trace->action) {
            'flow.node.executing' => sprintf('Call entered %s', $this->humanizeValue($trace->node_type ?? 'call flow')),
            'delivery.plan.created' => 'Delivery plan prepared',
            'delivery.failed' => 'Delivery failed',
            'flow.error' => 'Flow processing error',
            default => $this->humanizeDotSeparated($trace->action),
        };
    }

    private function describeEndpoint(?EndpointBinding $endpoint): string
    {
        if (! $endpoint instanceof EndpointBinding) {
            return 'unknown endpoint';
        }

        $parts = array_filter([
            $endpoint->platform && $endpoint->platform !== EndpointBinding::PLATFORM_UNKNOWN ? $this->humanizeValue($endpoint->platform) : null,
            $this->humanizeValue($endpoint->type),
        ]);

        return $parts === [] ? 'unknown endpoint' : implode(' ', $parts);
    }

    private function humanizeState(?string $state): string
    {
        return $state ? ucfirst($this->humanizeValue($state)) : 'Unknown';
    }

    private function normalizeTimestamp(mixed $value): ?string
    {
        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->toIso8601String();
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function humanizeValue(string $value): string
    {
        return str_replace('_', ' ', $value);
    }

    private function humanizeDotSeparated(string $value): string
    {
        return ucfirst(str_replace(['.', '_'], ' ', $value));
    }
}
