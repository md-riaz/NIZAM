<?php

namespace App\Services;

use App\Events\CallEvent;
use App\Models\CallDetailRecord;
use App\Models\Extension;
use Illuminate\Support\Facades\Log;

class EventProcessor
{
    public function __construct(
        protected WebhookDispatcher $webhookDispatcher
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

        Log::debug('Call answered', ['uuid' => $data['uuid'] ?? 'unknown']);
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

        CallEvent::dispatch($tenantId, 'hangup', $data);
        $this->webhookDispatcher->dispatch($tenantId, 'call.hangup', $data);

        // Check for missed call (no answer)
        if (($data['hangup_cause'] ?? '') === 'NO_ANSWER') {
            $this->webhookDispatcher->dispatch($tenantId, 'call.missed', $data);
        }

        Log::debug('Call hangup', ['uuid' => $data['uuid'] ?? 'unknown', 'cause' => $data['hangup_cause']]);
    }

    protected function handleCustomEvent(array $event): void
    {
        $subclass = $event['Event-Subclass'] ?? '';

        if ($subclass === 'vm::maintenance') {
            $this->handleVoicemail($event);
        }
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

        $extension = Extension::whereHas('tenant', function ($q) use ($domain) {
            $q->where('domain', $domain)->where('is_active', true);
        })->first();

        return $extension?->tenant_id;
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
}
