<?php

namespace App\Jobs;

use App\Models\EndpointBinding;
use App\Models\PushNotificationLog;
use App\Services\Push\PushDeliveryResult;
use App\Services\Push\PushDriverManager;
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
    public function handle(PushDriverManager $manager): void
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
            $this->persistResult($log, PushDeliveryResult::failed('endpoint_binding_not_found'));

            return;
        }

        if (! $binding->is_push_capable || ! $binding->hasPushTokenMaterial()) {
            $this->persistResult($log, PushDeliveryResult::failed('endpoint_not_push_capable'));

            return;
        }

        $pushType = (string) ($this->payload['notification_type'] ?? $log->push_type ?? 'wake');

        try {
            $result = $manager->deliver($binding, $pushType, $this->payload);
        } catch (\Throwable $e) {
            Log::error('DispatchCallDeliveryPush: uncaught delivery exception', [
                'push_notification_log_id' => $log->id,
                'call_session_id' => $this->callSessionId,
                'endpoint_binding_id' => $this->endpointBindingId,
                'error' => $e->getMessage(),
            ]);

            $this->persistResult($log, PushDeliveryResult::failed('delivery_exception: '.$e->getMessage()));

            throw $e;
        }

        $this->persistResult($log, $result);
    }

    /**
     * Handle a job failure after all retries are exhausted.
     */
    public function failed(?\Throwable $exception): void
    {
        $log = PushNotificationLog::find($this->pushNotificationLogId);

        if ($log instanceof PushNotificationLog && in_array($log->status, ['queued', 'retrying'], true)) {
            $this->persistResult(
                $log,
                PushDeliveryResult::failed('max_retries_exhausted: '.($exception?->getMessage() ?? 'unknown')),
            );
        }

        Log::error('DispatchCallDeliveryPush: push delivery dead-lettered', [
            'push_notification_log_id' => $this->pushNotificationLogId,
            'call_session_id' => $this->callSessionId,
            'endpoint_binding_id' => $this->endpointBindingId,
            'error' => $exception?->getMessage(),
        ]);
    }

    protected function persistResult(PushNotificationLog $log, PushDeliveryResult $result): void
    {
        $status = $result->success ? 'sent' : 'failed';

        $responsePatch = $result->toArray();
        if ($result->success) {
            $responsePatch['sent_at'] = now()->toIso8601String();
        } else {
            $responsePatch['failed_at'] = now()->toIso8601String();
        }

        $log->forceFill([
            'status' => $status,
            'response_payload' => array_merge(
                is_array($log->response_payload) ? $log->response_payload : [],
                $responsePatch,
            ),
        ])->save();

        if ($result->success) {
            Log::debug('DispatchCallDeliveryPush: push sent', [
                'push_notification_log_id' => $log->id,
                'channel' => $result->channel,
                'provider_message_id' => $result->providerMessageId,
                'push_type' => $log->push_type,
            ]);
        } else {
            Log::warning('DispatchCallDeliveryPush: push failed', [
                'push_notification_log_id' => $log->id,
                'error' => $result->error,
            ]);
        }
    }
}
