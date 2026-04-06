<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\AlertPolicy;
use App\Models\AnalyticsEvent;
use App\Models\CallEventLog;
use App\Models\QueueMetric;
use App\Models\WebhookDeliveryAttempt;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AnomalyDetectorService
{
    /**
     * Run all anomaly detectors for a tenant.
     */
    public function detectAnomalies(string $tenantId): Collection
    {
        $policies = AlertPolicy::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        $alerts = collect();

        foreach ($policies as $policy) {
            $alert = $this->evaluatePolicy($policy);
            if ($alert) {
                $alerts->push($alert);
            }
        }

        return $alerts;
    }

    /**
     * Evaluate a single alert policy against current metrics.
     */
    public function evaluatePolicy(AlertPolicy $policy): ?Alert
    {
        if ($policy->isInCooldown()) {
            return null;
        }

        $currentValue = $this->computeMetricValue($policy);

        if ($currentValue === null) {
            return null;
        }

        if (! $policy->evaluateCondition($currentValue)) {
            return null;
        }

        return $this->createAlert($policy, $currentValue);
    }

    /**
     * Compute the current value for a given metric.
     */
    public function computeMetricValue(AlertPolicy $policy): ?float
    {
        $windowStart = now()->subMinutes($policy->window_minutes);

        return match ($policy->metric) {
            AlertPolicy::METRIC_ABANDON_RATE => $this->computeAbandonRate($policy->tenant_id, $windowStart),
            AlertPolicy::METRIC_WEBHOOK_FAILURES => $this->computeWebhookFailures($policy->tenant_id, $windowStart),
            AlertPolicy::METRIC_GATEWAY_FLAPPING => $this->computeGatewayFlapping($policy->tenant_id, $windowStart),
            AlertPolicy::METRIC_SLA_DROP => $this->computeSlaLevel($policy->tenant_id, $windowStart),
            default => null,
        };
    }

    /**
     * Compute abandon rate in the given window.
     */
    protected function computeAbandonRate(string $tenantId, Carbon $windowStart): float
    {
        $events = AnalyticsEvent::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $windowStart)
            ->get();

        if ($events->isEmpty()) {
            return 0.0;
        }

        $totalCalls = $events->count();
        $abandonedCalls = $events->where('abandon', true)->count();

        return round(($abandonedCalls / $totalCalls) * 100, 2);
    }

    /**
     * Compute webhook failure count in the given window.
     */
    protected function computeWebhookFailures(string $tenantId, Carbon $windowStart): float
    {
        return (float) WebhookDeliveryAttempt::where('tenant_id', $tenantId)
            ->where('status', 'failed')
            ->where('created_at', '>=', $windowStart)
            ->count();
    }

    /**
     * Compute gateway registration flapping (register/unregister cycles).
     */
    protected function computeGatewayFlapping(string $tenantId, Carbon $windowStart): float
    {
        $registrations = CallEventLog::where('tenant_id', $tenantId)
            ->whereIn('event_type', [
                CallEventLog::EVENT_DEVICE_REGISTERED,
                CallEventLog::EVENT_DEVICE_UNREGISTERED,
            ])
            ->where('occurred_at', '>=', $windowStart)
            ->count();

        return (float) $registrations;
    }

    /**
     * Compute current SLA level (inverse: alert when it drops below threshold).
     */
    protected function computeSlaLevel(string $tenantId, Carbon $windowStart): float
    {
        $metrics = QueueMetric::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $windowStart)
            ->get();

        if ($metrics->isEmpty()) {
            return 100.0;
        }

        return round((float) $metrics->avg('service_level'), 2);
    }

    /**
     * Create an alert from a triggered policy.
     */
    protected function createAlert(AlertPolicy $policy, float $currentValue): Alert
    {
        $severity = $this->determineSeverity($policy, $currentValue);

        $alert = Alert::create([
            'tenant_id' => $policy->tenant_id,
            'alert_policy_id' => $policy->id,
            'severity' => $severity,
            'metric' => $policy->metric,
            'current_value' => $currentValue,
            'threshold_value' => $policy->threshold,
            'status' => Alert::STATUS_OPEN,
            'message' => $this->buildAlertMessage($policy, $currentValue),
            'context' => [
                'window_minutes' => $policy->window_minutes,
                'condition' => $policy->condition,
                'channels' => $policy->channels,
            ],
        ]);

        $policy->update(['last_triggered_at' => now()]);

        return $alert;
    }

    /**
     * Determine alert severity based on how far the value exceeds the threshold.
     */
    protected function determineSeverity(AlertPolicy $policy, float $currentValue): string
    {
        $threshold = (float) $policy->threshold;
        $deviation = abs($currentValue - $threshold);
        $percentDeviation = $threshold > 0 ? ($deviation / $threshold) * 100 : $deviation;

        if ($percentDeviation >= 50) {
            return Alert::SEVERITY_CRITICAL;
        }

        if ($percentDeviation >= 25) {
            return Alert::SEVERITY_WARNING;
        }

        return Alert::SEVERITY_INFO;
    }

    /**
     * Build a human-readable alert message.
     */
    protected function buildAlertMessage(AlertPolicy $policy, float $currentValue): string
    {
        $metricLabel = str_replace('_', ' ', $policy->metric);

        return sprintf(
            '%s: %s is %.2f (threshold: %s %.2f) over the last %d minutes',
            $policy->name,
            $metricLabel,
            $currentValue,
            $policy->condition,
            (float) $policy->threshold,
            $policy->window_minutes
        );
    }
}
