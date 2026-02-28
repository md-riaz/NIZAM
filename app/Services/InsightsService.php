<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\CallDetailRecord;
use App\Models\CallEventLog;
use App\Models\QueueEntry;
use App\Models\WebhookDeliveryAttempt;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class InsightsService
{
    /**
     * Extract features from call events into the analytics event store.
     * Idempotent: keyed by call_uuid + version.
     */
    public function extractFeatures(string $callUuid, string $tenantId, int $version = 1): ?AnalyticsEvent
    {
        $existing = AnalyticsEvent::where('call_uuid', $callUuid)
            ->where('version', $version)
            ->first();

        if ($existing) {
            return $existing;
        }

        $cdr = CallDetailRecord::where('uuid', $callUuid)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $cdr) {
            return null;
        }

        $events = CallEventLog::where('call_uuid', $callUuid)
            ->where('tenant_id', $tenantId)
            ->orderBy('occurred_at')
            ->get();

        $queueEntry = QueueEntry::where('call_uuid', $callUuid)->first();

        $webhookFailures = WebhookDeliveryAttempt::where('tenant_id', $tenantId)
            ->where('event_type', 'like', 'call.%')
            ->where('created_at', '>=', $cdr->start_stamp)
            ->where('created_at', '<=', $cdr->end_stamp ?? now())
            ->where('status', 'failed')
            ->count();

        $waitTime = $queueEntry ? $queueEntry->wait_duration : null;
        $talkTime = $cdr->billsec ? (float) $cdr->billsec : null;
        $abandon = $queueEntry ? $queueEntry->status === QueueEntry::STATUS_ABANDONED : false;
        $retries = $events->where('event_type', CallEventLog::EVENT_CALL_CREATED)->count();

        return AnalyticsEvent::create([
            'tenant_id' => $tenantId,
            'call_uuid' => $callUuid,
            'version' => $version,
            'wait_time' => $waitTime,
            'talk_time' => $talkTime,
            'abandon' => $abandon,
            'agent_id' => $queueEntry?->agent_id,
            'queue_id' => $queueEntry?->queue_id,
            'hangup_cause' => $cdr->hangup_cause,
            'retries' => max(0, $retries - 1),
            'webhook_failures' => $webhookFailures,
        ]);
    }

    /**
     * Compute a health score for a single analytics event.
     * Rule-based scoring v1.
     */
    public function scoreEvent(AnalyticsEvent $event): AnalyticsEvent
    {
        $breakdown = $this->computeScoreBreakdown($event);
        $healthScore = $this->aggregateScores($breakdown);

        $event->update([
            'health_score' => $healthScore,
            'score_breakdown' => $breakdown,
        ]);

        return $event;
    }

    /**
     * Compute per-tenant health score across recent analytics events.
     */
    public function computeTenantHealthScore(string $tenantId, ?Carbon $since = null): array
    {
        $since = $since ?? now()->subHours(24);

        $events = AnalyticsEvent::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $since)
            ->whereNotNull('health_score')
            ->get();

        if ($events->isEmpty()) {
            return [
                'tenant_id' => $tenantId,
                'health_score' => 100.0,
                'sample_size' => 0,
                'period_start' => $since->toIso8601String(),
                'breakdown' => $this->emptyBreakdown(),
            ];
        }

        $avgScore = round($events->avg('health_score'), 2);
        $breakdowns = $events->pluck('score_breakdown')->filter();

        $avgBreakdown = [];
        if ($breakdowns->isNotEmpty()) {
            $keys = array_keys($breakdowns->first());
            foreach ($keys as $key) {
                $avgBreakdown[$key] = round($breakdowns->avg($key), 2);
            }
        }

        return [
            'tenant_id' => $tenantId,
            'health_score' => $avgScore,
            'sample_size' => $events->count(),
            'period_start' => $since->toIso8601String(),
            'breakdown' => $avgBreakdown,
        ];
    }

    /**
     * Batch process: extract and score all unprocessed CDRs for a tenant.
     */
    public function processTenantCalls(string $tenantId, ?Carbon $since = null): Collection
    {
        $since = $since ?? now()->subHours(24);

        $cdrs = CallDetailRecord::where('tenant_id', $tenantId)
            ->where('start_stamp', '>=', $since)
            ->get();

        $processed = collect();

        foreach ($cdrs as $cdr) {
            $event = $this->extractFeatures($cdr->uuid, $tenantId);
            if ($event) {
                $this->scoreEvent($event);
                $processed->push($event->fresh());
            }
        }

        return $processed;
    }

    /**
     * Compute score breakdown using rule-based scoring.
     * Each dimension scores 0-100 (100 = best).
     */
    protected function computeScoreBreakdown(AnalyticsEvent $event): array
    {
        return [
            'wait_time_score' => $this->scoreWaitTime($event->wait_time),
            'talk_time_score' => $this->scoreTalkTime($event->talk_time),
            'abandon_score' => $event->abandon ? 0.0 : 100.0,
            'hangup_cause_score' => $this->scoreHangupCause($event->hangup_cause),
            'retry_score' => $this->scoreRetries($event->retries),
            'webhook_score' => $this->scoreWebhookFailures($event->webhook_failures),
        ];
    }

    /**
     * Aggregate individual dimension scores into a single health score.
     * Weighted average.
     */
    protected function aggregateScores(array $breakdown): float
    {
        $weights = [
            'wait_time_score' => 0.20,
            'talk_time_score' => 0.10,
            'abandon_score' => 0.30,
            'hangup_cause_score' => 0.15,
            'retry_score' => 0.10,
            'webhook_score' => 0.15,
        ];

        $weighted = 0;
        foreach ($weights as $key => $weight) {
            $weighted += ($breakdown[$key] ?? 100) * $weight;
        }

        return round($weighted, 2);
    }

    protected function scoreWaitTime(?float $waitTime): float
    {
        if ($waitTime === null) {
            return 100.0;
        }
        if ($waitTime <= 15) {
            return 100.0;
        }
        if ($waitTime <= 30) {
            return 80.0;
        }
        if ($waitTime <= 60) {
            return 60.0;
        }
        if ($waitTime <= 120) {
            return 30.0;
        }

        return 0.0;
    }

    protected function scoreTalkTime(?float $talkTime): float
    {
        if ($talkTime === null) {
            return 50.0;
        }
        if ($talkTime >= 10 && $talkTime <= 600) {
            return 100.0;
        }
        if ($talkTime < 10) {
            return 40.0;
        }

        return 70.0;
    }

    protected function scoreHangupCause(?string $cause): float
    {
        return match ($cause) {
            'NORMAL_CLEARING' => 100.0,
            'ORIGINATOR_CANCEL' => 70.0,
            'USER_BUSY' => 50.0,
            'NO_ANSWER' => 30.0,
            'CALL_REJECTED' => 20.0,
            default => 60.0,
        };
    }

    protected function scoreRetries(int $retries): float
    {
        return match (true) {
            $retries === 0 => 100.0,
            $retries === 1 => 70.0,
            $retries === 2 => 40.0,
            default => 10.0,
        };
    }

    protected function scoreWebhookFailures(int $failures): float
    {
        return match (true) {
            $failures === 0 => 100.0,
            $failures <= 2 => 70.0,
            $failures <= 5 => 40.0,
            default => 10.0,
        };
    }

    protected function emptyBreakdown(): array
    {
        return [
            'wait_time_score' => 100.0,
            'talk_time_score' => 100.0,
            'abandon_score' => 100.0,
            'hangup_cause_score' => 100.0,
            'retry_score' => 100.0,
            'webhook_score' => 100.0,
        ];
    }
}
