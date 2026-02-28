<?php

namespace App\Services;

use App\Events\CallEvent;
use App\Models\CallDetailRecord;
use App\Models\CallEventLog;
use App\Models\Tenant;
use App\Models\UsageRecord;
use Illuminate\Support\Facades\Log;

class EventProcessor
{
    public function __construct(
        protected WebhookDispatcher $webhookDispatcher,
        protected ?UsageMeteringService $meteringService = null
    ) {}

    /**
     * Process a raw FreeSWITCH event.
     */
    public function process(array $event): void
    {
        $eventName = $event['Event-Name'] ?? '';

        match ($eventName) {
            'CHANNEL_CREATE' => $this->handleChannelCreate($event),
            'CHANNEL_ANSWER' => $this->handleChannelAnswer($event),
            'CHANNEL_BRIDGE' => $this->handleChannelBridge($event),
            'CHANNEL_HANGUP_COMPLETE' => $this->handleChannelHangup($event),
            'CUSTOM' => $this->handleCustomEvent($event),
            default => null,
        };
    }

    protected function handleChannelCreate(array $event): void
    {
        $tenantId = $this->resolveTenantId($event);
        if (! $tenantId) {
            return;
        }

        $data = $this->extractCallData($event);

        CallEvent::dispatch($tenantId, 'started', $data);
        $this->webhookDispatcher->dispatch($tenantId, 'call.started', $data);
        $this->recordEvent($tenantId, 'started', $data);

        Log::debug('Call started', ['uuid' => $data['uuid'] ?? 'unknown']);
    }

    protected function handleChannelAnswer(array $event): void
    {
        $tenantId = $this->resolveTenantId($event);
        if (! $tenantId) {
            return;
        }

        $data = $this->extractCallData($event);

        CallEvent::dispatch($tenantId, 'answered', $data);
        $this->webhookDispatcher->dispatch($tenantId, 'call.answered', $data);
        $this->recordEvent($tenantId, 'answered', $data);

        Log::debug('Call answered', ['uuid' => $data['uuid'] ?? 'unknown']);
    }

    protected function handleChannelBridge(array $event): void
    {
        $tenantId = $this->resolveTenantId($event);
        if (! $tenantId) {
            return;
        }

        $data = $this->extractCallData($event);
        $data['other_leg_uuid'] = $event['Other-Leg-Unique-ID'] ?? '';

        CallEvent::dispatch($tenantId, 'bridge', $data);
        $this->webhookDispatcher->dispatch($tenantId, 'call.bridge', $data);
        $this->recordEvent($tenantId, 'bridge', $data);

        Log::debug('Call bridged', ['uuid' => $data['uuid'] ?? 'unknown', 'other_leg' => $data['other_leg_uuid']]);
    }

    protected function handleChannelHangup(array $event): void
    {
        $tenantId = $this->resolveTenantId($event);
        if (! $tenantId) {
            return;
        }

        $data = $this->extractCallData($event);
        $data['hangup_cause'] = $event['Hangup-Cause'] ?? 'NORMAL_CLEARING';
        $data['duration'] = (int) ($event['variable_duration'] ?? 0);
        $data['billsec'] = (int) ($event['variable_billsec'] ?? 0);

        // Create CDR record
        $this->createCdr($tenantId, $data, $event);

        // Record call minutes for usage metering
        $this->recordCallMinutes($tenantId, $data['billsec']);

        CallEvent::dispatch($tenantId, 'hangup', $data);
        $this->webhookDispatcher->dispatch($tenantId, 'call.hangup', $data);
        $this->recordEvent($tenantId, 'hangup', $data);

        // Check for missed call (no answer)
        if (($data['hangup_cause'] ?? '') === 'NO_ANSWER') {
            $this->webhookDispatcher->dispatch($tenantId, 'call.missed', $data);
        }

        Log::debug('Call hangup', ['uuid' => $data['uuid'] ?? 'unknown', 'cause' => $data['hangup_cause']]);
    }

    protected function handleCustomEvent(array $event): void
    {
        $subclass = $event['Event-Subclass'] ?? '';

        match ($subclass) {
            'vm::maintenance' => $this->handleVoicemail($event),
            'sofia::register' => $this->handleRegistration($event, 'registered'),
            'sofia::unregister' => $this->handleRegistration($event, 'unregistered'),
            default => null,
        };
    }

    protected function handleVoicemail(array $event): void
    {
        $action = $event['VM-Action'] ?? '';
        if ($action !== 'leave-message') {
            return;
        }

        $tenantId = $this->resolveTenantId($event);
        if (! $tenantId) {
            return;
        }

        $data = [
            'user' => $event['VM-User'] ?? '',
            'domain' => $event['VM-Domain'] ?? '',
            'caller_id_number' => $event['VM-Caller-ID-Number'] ?? '',
            'caller_id_name' => $event['VM-Caller-ID-Name'] ?? '',
            'message_len' => $event['VM-Message-Len'] ?? '0',
        ];

        $this->webhookDispatcher->dispatch($tenantId, 'voicemail.received', $data);
        Log::debug('Voicemail received', $data);
    }

    protected function handleRegistration(array $event, string $action): void
    {
        $domain = $event['domain'] ?? $event['realm'] ?? null;
        if (! $domain) {
            return;
        }

        $tenant = \App\Models\Tenant::where('domain', $domain)->where('is_active', true)->first();
        if (! $tenant) {
            return;
        }

        $data = [
            'user' => $event['from-user'] ?? $event['username'] ?? '',
            'domain' => $domain,
            'contact' => $event['contact'] ?? '',
            'user_agent' => $event['user-agent'] ?? '',
            'network_ip' => $event['network-ip'] ?? '',
            'action' => $action,
        ];

        CallEvent::dispatch($tenant->id, $action, $data);
        $this->webhookDispatcher->dispatch($tenant->id, "registration.{$action}", $data);
        $this->recordEvent($tenant->id, $action, $data);

        Log::debug("SIP {$action}", ['user' => $data['user'], 'domain' => $domain]);
    }

    /**
     * Resolve tenant ID from a FreeSWITCH event.
     */
    protected function resolveTenantId(array $event): ?string
    {
        $domain = $event['variable_domain_name']
            ?? $event['variable_sip_req_host']
            ?? $event['FreeSWITCH-Hostname']
            ?? null;

        if (! $domain) {
            return null;
        }

        $tenant = \App\Models\Tenant::where('domain', $domain)->where('is_active', true)->first();

        if (! $tenant || ! $tenant->isOperational()) {
            return null;
        }

        return $tenant->id;
    }

    /**
     * Extract common call data from event.
     */
    protected function extractCallData(array $event): array
    {
        return [
            'uuid' => $event['Unique-ID'] ?? $event['variable_uuid'] ?? '',
            'caller_id_name' => $event['Caller-Caller-ID-Name'] ?? '',
            'caller_id_number' => $event['Caller-Caller-ID-Number'] ?? '',
            'destination_number' => $event['Caller-Destination-Number'] ?? '',
            'direction' => $event['Call-Direction'] ?? 'unknown',
        ];
    }

    /**
     * Create a CDR from hangup event.
     */
    protected function createCdr(string $tenantId, array $data, array $event): void
    {
        try {
            CallDetailRecord::create([
                'tenant_id' => $tenantId,
                'uuid' => $data['uuid'],
                'caller_id_name' => $data['caller_id_name'],
                'caller_id_number' => $data['caller_id_number'],
                'destination_number' => $data['destination_number'],
                'context' => $event['Caller-Context'] ?? null,
                'start_stamp' => $event['variable_start_stamp'] ?? now(),
                'answer_stamp' => $event['variable_answer_stamp'] ?? null,
                'end_stamp' => $event['variable_end_stamp'] ?? now(),
                'duration' => $data['duration'],
                'billsec' => $data['billsec'],
                'hangup_cause' => $data['hangup_cause'],
                'direction' => in_array($data['direction'], ['inbound', 'outbound', 'local'])
                    ? $data['direction']
                    : 'local',
                'recording_path' => $event['variable_record_file_path'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create CDR', ['error' => $e->getMessage(), 'uuid' => $data['uuid']]);
        }
    }

    /**
     * Persist a call event for replay/audit.
     */
    protected function recordEvent(string $tenantId, string $eventType, array $data): void
    {
        try {
            CallEventLog::create([
                'tenant_id' => $tenantId,
                'call_uuid' => $data['uuid'] ?? $data['user'] ?? '',
                'event_type' => $eventType,
                'payload' => $data,
                'occurred_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to record call event', ['error' => $e->getMessage(), 'event_type' => $eventType]);
        }
    }

    /**
     * Record call minutes for usage metering.
     */
    protected function recordCallMinutes(string $tenantId, int $billsec): void
    {
        if ($billsec <= 0 || ! $this->meteringService) {
            return;
        }

        try {
            $tenant = Tenant::find($tenantId);
            if ($tenant) {
                $this->meteringService->record(
                    $tenant,
                    UsageRecord::METRIC_CALL_MINUTES,
                    round($billsec / 60, 4)
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to record call minutes', ['error' => $e->getMessage(), 'tenant_id' => $tenantId]);
        }
    }
}
