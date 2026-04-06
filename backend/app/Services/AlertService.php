<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\AlertPolicy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AlertService
{
    /**
     * Route an alert to configured channels.
     * Returns delivery results for each channel.
     */
    public function routeAlert(Alert $alert): array
    {
        $policy = $alert->policy;

        if (! $policy) {
            return ['error' => 'No associated policy found'];
        }

        $results = [];

        foreach ($policy->channels as $channel) {
            $results[$channel] = $this->deliverToChannel($alert, $channel, $policy);
        }

        return $results;
    }

    /**
     * Route multiple alerts.
     */
    public function routeAlerts(Collection $alerts): array
    {
        $results = [];

        foreach ($alerts as $alert) {
            $results[$alert->id] = $this->routeAlert($alert);
        }

        return $results;
    }

    /**
     * Deliver alert to a specific channel.
     */
    protected function deliverToChannel(Alert $alert, string $channel, AlertPolicy $policy): array
    {
        return match ($channel) {
            AlertPolicy::CHANNEL_EMAIL => $this->deliverEmail($alert, $policy),
            AlertPolicy::CHANNEL_WEBHOOK => $this->deliverWebhook($alert, $policy),
            AlertPolicy::CHANNEL_SLACK => $this->deliverSlack($alert, $policy),
            default => ['status' => 'skipped', 'reason' => 'Unknown channel: '.$channel],
        };
    }

    /**
     * Deliver alert via email.
     */
    protected function deliverEmail(Alert $alert, AlertPolicy $policy): array
    {
        $recipients = $this->getRecipientsByChannel($policy, AlertPolicy::CHANNEL_EMAIL);

        if (empty($recipients)) {
            return ['status' => 'skipped', 'reason' => 'No email recipients configured'];
        }

        Log::info('Alert email delivery', [
            'alert_id' => $alert->id,
            'recipients' => $recipients,
            'message' => $alert->message,
        ]);

        return [
            'status' => 'queued',
            'channel' => 'email',
            'recipients' => $recipients,
        ];
    }

    /**
     * Deliver alert via webhook.
     */
    protected function deliverWebhook(Alert $alert, AlertPolicy $policy): array
    {
        $recipients = $this->getRecipientsByChannel($policy, AlertPolicy::CHANNEL_WEBHOOK);

        if (empty($recipients)) {
            return ['status' => 'skipped', 'reason' => 'No webhook URLs configured'];
        }

        $payload = $this->buildWebhookPayload($alert);

        Log::info('Alert webhook delivery', [
            'alert_id' => $alert->id,
            'urls' => $recipients,
            'payload' => $payload,
        ]);

        return [
            'status' => 'queued',
            'channel' => 'webhook',
            'urls' => $recipients,
            'payload' => $payload,
        ];
    }

    /**
     * Deliver alert via Slack (interface-ready, not connected yet).
     */
    protected function deliverSlack(Alert $alert, AlertPolicy $policy): array
    {
        $recipients = $this->getRecipientsByChannel($policy, AlertPolicy::CHANNEL_SLACK);

        if (empty($recipients)) {
            return ['status' => 'skipped', 'reason' => 'No Slack channels configured'];
        }

        $payload = $this->buildSlackPayload($alert);

        Log::info('Alert Slack delivery', [
            'alert_id' => $alert->id,
            'channels' => $recipients,
            'payload' => $payload,
        ]);

        return [
            'status' => 'queued',
            'channel' => 'slack',
            'slack_channels' => $recipients,
            'payload' => $payload,
        ];
    }

    /**
     * Get recipients filtered for a specific channel.
     */
    protected function getRecipientsByChannel(AlertPolicy $policy, string $channel): array
    {
        $recipients = $policy->recipients ?? [];

        if (empty($recipients)) {
            return [];
        }

        // If recipients is a flat array, return all
        if (! isset($recipients[$channel]) && ! is_array(reset($recipients))) {
            return $recipients;
        }

        return $recipients[$channel] ?? [];
    }

    /**
     * Build webhook payload for alert.
     */
    protected function buildWebhookPayload(Alert $alert): array
    {
        return [
            'alert_id' => $alert->id,
            'tenant_id' => $alert->tenant_id,
            'severity' => $alert->severity,
            'metric' => $alert->metric,
            'current_value' => $alert->current_value,
            'threshold_value' => $alert->threshold_value,
            'message' => $alert->message,
            'status' => $alert->status,
            'created_at' => $alert->created_at?->toIso8601String(),
            'context' => $alert->context,
        ];
    }

    /**
     * Build Slack message payload.
     */
    protected function buildSlackPayload(Alert $alert): array
    {
        $emoji = match ($alert->severity) {
            Alert::SEVERITY_CRITICAL => ':rotating_light:',
            Alert::SEVERITY_WARNING => ':warning:',
            default => ':information_source:',
        };

        return [
            'text' => sprintf('%s *%s Alert*: %s', $emoji, ucfirst($alert->severity), $alert->message),
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => sprintf(
                            "%s *%s*\n*Metric:* %s\n*Current:* %.2f | *Threshold:* %.2f\n*Tenant:* %s",
                            $emoji,
                            $alert->message,
                            $alert->metric,
                            (float) $alert->current_value,
                            (float) $alert->threshold_value,
                            $alert->tenant_id
                        ),
                    ],
                ],
            ],
        ];
    }
}
