<?php

namespace App\Jobs;

use App\Models\EndpointBinding;
use App\Models\PushNotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchCallDeliveryPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 5;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 15;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $pushNotificationLogId,
        public readonly string $callSessionId,
        public readonly string $endpointBindingId,
        public readonly array $payload,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $log = PushNotificationLog::find($this->pushNotificationLogId);

        if (! $log instanceof PushNotificationLog) {
            Log::warning('DispatchCallDeliveryPush: push notification log not found', [
                'push_notification_log_id' => $this->pushNotificationLogId,
            ]);

            return;
        }

        if (! in_array($log->status, ['queued', 'retrying'], true)) {
            return;
        }

        $binding = EndpointBinding::with('tenant', 'extension')->find($this->endpointBindingId);

        if (! $binding instanceof EndpointBinding) {
            $this->markFailed($log, 'endpoint_binding_not_found');

            return;
        }

        if (! $binding->is_push_capable || ! $binding->hasPushTokenMaterial()) {
            $this->markFailed($log, 'endpoint_not_push_capable');

            return;
        }

        $this->deliverPush($log, $binding);
    }

    /**
     * Handle a job failure after all retries are exhausted.
     */
    public function failed(?\Throwable $exception): void
    {
        $log = PushNotificationLog::find($this->pushNotificationLogId);

        if ($log instanceof PushNotificationLog) {
            $this->markFailed($log, 'max_retries_exhausted: '.($exception?->getMessage() ?? 'unknown'));
        }

        Log::error('DispatchCallDeliveryPush: push delivery dead-lettered', [
            'push_notification_log_id' => $this->pushNotificationLogId,
            'call_session_id' => $this->callSessionId,
            'endpoint_binding_id' => $this->endpointBindingId,
            'error' => $exception?->getMessage(),
        ]);
    }

    /**
     * Deliver the push notification to the appropriate platform channel.
     */
    protected function deliverPush(PushNotificationLog $log, EndpointBinding $binding): void
    {
        $platform = $binding->platform ?? EndpointBinding::PLATFORM_UNKNOWN;
        $pushType = (string) ($this->payload['notification_type'] ?? $log->push_type ?? 'wake');

        try {
            if ($platform === EndpointBinding::PLATFORM_IOS && filled($binding->voip_push_token)) {
                $this->deliverApnsVoip($log, $binding, $pushType);

                return;
            }

            if (in_array($platform, [EndpointBinding::PLATFORM_ANDROID, EndpointBinding::PLATFORM_WEB], true)
                && filled($binding->push_token)) {
                $this->deliverFcm($log, $binding, $pushType);

                return;
            }

            if (filled($binding->push_token)) {
                $this->deliverFcm($log, $binding, $pushType);

                return;
            }

            if (filled($binding->voip_push_token)) {
                $this->deliverApnsVoip($log, $binding, $pushType);

                return;
            }

            $this->markFailed($log, 'no_push_token_material');
        } catch (\Throwable $e) {
            $this->markFailed($log, 'delivery_exception: '.$e->getMessage());

            Log::error('DispatchCallDeliveryPush: delivery exception', [
                'push_notification_log_id' => $log->id,
                'call_session_id' => $this->callSessionId,
                'endpoint_binding_id' => $this->endpointBindingId,
                'platform' => $platform,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Deliver an APNs VoIP push notification (iOS CallKit wake).
     *
     * The actual APNs transport is resolved from the configured push driver
     * (see config/telephony.php push_driver). In the default 'log' driver, the
     * notification is written to the application log so the delivery pipeline is
     * fully exercised without requiring real APNs credentials.
     */
    protected function deliverApnsVoip(PushNotificationLog $log, EndpointBinding $binding, string $pushType): void
    {
        $driver = (string) config('telephony.push_driver', 'log');

        $logContext = [
            'push_notification_log_id' => $log->id,
            'call_session_id' => $this->callSessionId,
            'endpoint_binding_id' => $binding->id,
            'push_type' => $pushType,
            'platform' => 'ios',
            'driver' => $driver,
            'payload_keys' => array_keys($this->payload),
        ];

        if ($driver === 'log') {
            Log::info('DispatchCallDeliveryPush: APNs VoIP push (log driver)', $logContext);
            $this->markSent($log, 'apns_voip', ['driver' => 'log']);

            return;
        }

        // Real APNs dispatch is delegated to the configured push driver.
        // Operators who need live APNs delivery should implement a custom
        // push driver and bind it via the telephony.push_driver config key.
        Log::warning('DispatchCallDeliveryPush: APNs VoIP push driver not implemented', $logContext);
        $this->markFailed($log, 'push_driver_not_implemented');
    }

    /**
     * Deliver an FCM data push notification (Android / web wake).
     *
     * @see deliverApnsVoip for driver behaviour notes.
     */
    protected function deliverFcm(PushNotificationLog $log, EndpointBinding $binding, string $pushType): void
    {
        $driver = (string) config('telephony.push_driver', 'log');

        $logContext = [
            'push_notification_log_id' => $log->id,
            'call_session_id' => $this->callSessionId,
            'endpoint_binding_id' => $binding->id,
            'push_type' => $pushType,
            'platform' => $binding->platform,
            'driver' => $driver,
            'payload_keys' => array_keys($this->payload),
        ];

        if ($driver === 'log') {
            Log::info('DispatchCallDeliveryPush: FCM push (log driver)', $logContext);
            $this->markSent($log, 'fcm', ['driver' => 'log']);

            return;
        }

        Log::warning('DispatchCallDeliveryPush: FCM push driver not implemented', $logContext);
        $this->markFailed($log, 'push_driver_not_implemented');
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function markSent(PushNotificationLog $log, string $channel, array $meta = []): void
    {
        $log->forceFill([
            'status' => 'sent',
            'response_payload' => array_merge(
                is_array($log->response_payload) ? $log->response_payload : [],
                ['sent_via' => $channel, 'sent_at' => now()->toIso8601String(), ...$meta],
            ),
        ])->save();

        Log::debug('DispatchCallDeliveryPush: push sent', [
            'push_notification_log_id' => $log->id,
            'channel' => $channel,
            'push_type' => $log->push_type,
        ]);
    }

    protected function markFailed(PushNotificationLog $log, string $reason): void
    {
        $log->forceFill([
            'status' => 'failed',
            'response_payload' => array_merge(
                is_array($log->response_payload) ? $log->response_payload : [],
                ['failure_reason' => $reason, 'failed_at' => now()->toIso8601String()],
            ),
        ])->save();

        Log::warning('DispatchCallDeliveryPush: push failed', [
            'push_notification_log_id' => $log->id,
            'reason' => $reason,
        ]);
    }
}
