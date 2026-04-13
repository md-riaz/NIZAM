<?php

namespace App\Services;

use App\Events\CallDetailRecordCreated;
use App\Events\CallEvent;
use App\Models\CallDeliveryAttempt;
use App\Models\CallDetailRecord;
use App\Models\CallEventLog;
use App\Models\CallSession;
use App\Models\EndpointBinding;
use App\Models\Tenant;
use App\Models\UsageRecord;
use App\Modules\ModuleRegistry;
use App\Modules\Voicemail\VoicemailEventService;
use App\Services\Call\CallEventIngestionService;
use App\Services\Call\CallOfferExecutor;
use App\Services\Call\CallWinnerService;
use App\Services\Call\DeliveryPlan;
use App\Services\Call\DeliveryPlanItem;
use App\Services\Call\EndpointCandidate;
use App\Services\Call\ReachabilityCache;
use App\Services\Call\ReachabilityDecision;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class EventProcessor
{
    public function __construct(
        protected WebhookDispatcher $webhookDispatcher,
        protected ?UsageMeteringService $meteringService = null,
        protected ?CallEventIngestionService $callEventIngestionService = null,
        protected ?CallWinnerService $callWinnerService = null,
        protected ?ReachabilityCache $reachabilityCache = null,
        protected ?CallOfferExecutor $callOfferExecutor = null,
        protected ?VoicemailEventService $voicemailEventService = null,
        protected ?ModuleRegistry $moduleRegistry = null,
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

        $context = $this->resolveOrchestrationContext($tenantId, $event);
        $this->markAttemptRinging($context['attempt']);

        $data = $this->buildEventPayload(
            $tenantId,
            CallEventLog::EVENT_CALL_CREATED,
            $this->augmentCallData($this->extractCallData($event), $context),
        );

        CallEvent::dispatch($tenantId, CallEventLog::EVENT_CALL_CREATED, $data);
        $this->webhookDispatcher->dispatch($tenantId, CallEventLog::EVENT_CALL_CREATED, $data);
        $this->recordEvent($tenantId, CallEventLog::EVENT_CALL_CREATED, $data, $context['call_session']);

        Log::debug('Call started', ['uuid' => $data['call_uuid'] ?? 'unknown']);
    }

    protected function handleChannelAnswer(array $event): void
    {
        $tenantId = $this->resolveTenantId($event);
        if (! $tenantId) {
            return;
        }

        $context = $this->resolveOrchestrationContext($tenantId, $event);
        $this->markAttemptAnswered($context['attempt']);
        $this->electWinnerForAnsweredAttempt($context['call_session'], $context['attempt']);

        $data = $this->buildEventPayload(
            $tenantId,
            CallEventLog::EVENT_CALL_ANSWERED,
            $this->augmentCallData($this->extractCallData($event), $context),
        );

        CallEvent::dispatch($tenantId, CallEventLog::EVENT_CALL_ANSWERED, $data);
        $this->webhookDispatcher->dispatch($tenantId, CallEventLog::EVENT_CALL_ANSWERED, $data);
        $this->recordEvent($tenantId, CallEventLog::EVENT_CALL_ANSWERED, $data, $context['call_session']);

        Log::debug('Call answered', ['uuid' => $data['call_uuid'] ?? 'unknown']);
    }

    protected function handleChannelBridge(array $event): void
    {
        $tenantId = $this->resolveTenantId($event);
        if (! $tenantId) {
            return;
        }

        $context = $this->resolveOrchestrationContext($tenantId, $event);
        $callData = $this->extractCallData($event);
        $callData['other_leg_uuid'] = $event['Other-Leg-Unique-ID'] ?? '';
        $callData = $this->augmentCallData($callData, $context);

        $this->persistBridgeContext(
            $context['call_session'],
            $context['attempt'],
            $context['peer_attempt'],
            $callData['uuid'] ?? null,
            $callData['other_leg_uuid'] ?: null,
        );
        $this->finalizeWinningBridge(
            $context['call_session'],
            $context['attempt'],
            $context['peer_attempt'],
            $callData['uuid'] ?? null,
            $callData['other_leg_uuid'] ?: null,
        );

        $data = $this->buildEventPayload($tenantId, CallEventLog::EVENT_CALL_BRIDGED, $callData);

        CallEvent::dispatch($tenantId, CallEventLog::EVENT_CALL_BRIDGED, $data);
        $this->webhookDispatcher->dispatch($tenantId, CallEventLog::EVENT_CALL_BRIDGED, $data);
        $this->recordEvent($tenantId, CallEventLog::EVENT_CALL_BRIDGED, $data, $context['call_session']);

        Log::debug('Call bridged', ['uuid' => $data['call_uuid'] ?? 'unknown', 'other_leg' => $callData['other_leg_uuid']]);
    }

    protected function handleChannelHangup(array $event): void
    {
        $tenantId = $this->resolveTenantId($event);
        if (! $tenantId) {
            return;
        }

        $context = $this->resolveOrchestrationContext($tenantId, $event);
        $callData = $this->extractCallData($event);
        $callData['hangup_cause'] = $event['Hangup-Cause'] ?? 'NORMAL_CLEARING';
        $callData['duration'] = (int) ($event['variable_duration'] ?? 0);
        $callData['billsec'] = (int) ($event['variable_billsec'] ?? 0);
        $callData = $this->augmentCallData($callData, $context);

        $this->finalizeAttemptFromHangup($context['call_session'], $context['attempt'], $callData['hangup_cause']);
        $this->cleanupWinningHangup($context['call_session'], $context['attempt']);

        $data = $this->buildEventPayload($tenantId, CallEventLog::EVENT_CALL_HANGUP, $callData);

        $this->createCdr($tenantId, $data, $event);
        $this->recordCallMinutes($tenantId, $callData['billsec']);

        CallEvent::dispatch($tenantId, CallEventLog::EVENT_CALL_HANGUP, $data);
        $this->webhookDispatcher->dispatch($tenantId, CallEventLog::EVENT_CALL_HANGUP, $data);
        $this->recordEvent($tenantId, CallEventLog::EVENT_CALL_HANGUP, $data, $context['call_session']);

        if (($callData['hangup_cause'] ?? '') === 'NO_ANSWER') {
            $this->webhookDispatcher->dispatch($tenantId, 'call.missed', $data);
        }

        Log::debug('Call hangup', ['uuid' => $data['call_uuid'] ?? 'unknown', 'cause' => $callData['hangup_cause']]);
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
        $data = $this->voicemailEventService()->handleMaintenanceEvent($event);

        if (! is_array($data)) {
            return;
        }

        $tenantId = (string) ($data['tenant_id'] ?? '');
        if ($tenantId === '') {
            return;
        }

        $this->moduleRegistry()->dispatchEvent(CallEventLog::EVENT_VOICEMAIL_RECEIVED, $data);
        $this->webhookDispatcher->dispatch($tenantId, CallEventLog::EVENT_VOICEMAIL_RECEIVED, $data);
        $this->recordEvent($tenantId, CallEventLog::EVENT_VOICEMAIL_RECEIVED, $data);

        Log::debug('Voicemail received', $data['metadata'] ?? []);
    }

    protected function handleRegistration(array $event, string $action): void
    {
        $domain = $event['domain'] ?? $event['realm'] ?? null;
        if (! $domain) {
            return;
        }

        $tenant = Tenant::where('domain', $domain)->where('is_active', true)->first();
        if (! $tenant) {
            return;
        }

        $regData = [
            'user' => $event['from-user'] ?? $event['username'] ?? '',
            'domain' => $domain,
            'contact' => $event['contact'] ?? '',
            'user_agent' => $event['user-agent'] ?? '',
            'network_ip' => $event['network-ip'] ?? '',
            'action' => $action,
        ];

        $binding = $this->resolveRegistrationBinding($tenant->id, $regData['user']);
        $this->updateReachabilityFromRegistration($tenant->id, $binding, $regData, $action);

        if ($action === 'registered') {
            $this->tryLateJoinForRegistration($tenant->id, $binding, $domain, $regData);
        }

        $eventType = $action === 'registered'
            ? CallEventLog::EVENT_DEVICE_REGISTERED
            : CallEventLog::EVENT_DEVICE_UNREGISTERED;

        $data = $this->buildEventPayload($tenant->id, $eventType, $regData + [
            'endpoint_binding_id' => $binding?->id,
        ]);

        CallEvent::dispatch($tenant->id, $eventType, $data);
        $this->webhookDispatcher->dispatch($tenant->id, $eventType, $data);
        $this->recordEvent($tenant->id, $eventType, $data);

        Log::debug("SIP {$action}", ['user' => $regData['user'], 'domain' => $domain, 'endpoint_binding_id' => $binding?->id]);
    }

    protected function buildEventPayload(string $tenantId, string $eventType, array $metadata): array
    {
        return [
            'tenant_id' => $tenantId,
            'call_uuid' => $metadata['uuid'] ?? $metadata['user'] ?? '',
            'event_type' => $eventType,
            'timestamp' => now()->toIso8601String(),
            'schema_version' => CallEventLog::SCHEMA_VERSION,
            'metadata' => $metadata,
        ];
    }

    protected function resolveTenantId(array $event): ?string
    {
        $domain = $event['variable_domain_name']
            ?? $event['variable_sip_req_host']
            ?? $event['FreeSWITCH-Hostname']
            ?? null;

        if (! $domain) {
            return null;
        }

        $tenant = Tenant::where('domain', $domain)->where('is_active', true)->first();

        if (! $tenant || ! $tenant->isOperational()) {
            return null;
        }

        return $tenant->id;
    }

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
     * @return array{call_session:?CallSession,attempt:?CallDeliveryAttempt,peer_attempt:?CallDeliveryAttempt,caller_leg_uuid:?string}
     */
    protected function resolveOrchestrationContext(string $tenantId, array $event): array
    {
        $legUuid = $event['Unique-ID'] ?? $event['variable_uuid'] ?? null;
        $otherLegUuid = $event['Other-Leg-Unique-ID'] ?? null;
        $sessionId = (string) ($event['variable_sip_h_X-Nizam-Call-Session-Id'] ?? $event['sip_h_X-Nizam-Call-Session-Id'] ?? '');
        $callerLegUuid = $event['variable_nizam_call_uuid'] ?? $event['variable_origination_caller_channel_name'] ?? null;

        $attempt = $this->findAttemptByLegUuid($tenantId, is_string($legUuid) ? $legUuid : null);
        $peerAttempt = $this->findAttemptByLegUuid($tenantId, is_string($otherLegUuid) ? $otherLegUuid : null);

        $callSession = null;

        if ($sessionId !== '') {
            $callSession = CallSession::query()
                ->whereKey($sessionId)
                ->where('tenant_id', $tenantId)
                ->first();
        }

        if (! $callSession && $attempt?->callSession instanceof CallSession) {
            $callSession = $attempt->callSession;
        }

        if (! $callSession && $peerAttempt?->callSession instanceof CallSession) {
            $callSession = $peerAttempt->callSession;
        }

        if (! $callSession && is_string($callerLegUuid) && $callerLegUuid !== '') {
            $callSession = CallSession::query()
                ->where('tenant_id', $tenantId)
                ->where('call_uuid', $callerLegUuid)
                ->first();
        }

        if (! $callSession && is_string($legUuid) && $legUuid !== '') {
            $callSession = CallSession::query()
                ->where('tenant_id', $tenantId)
                ->where('call_uuid', $legUuid)
                ->first();
        }

        return [
            'call_session' => $callSession,
            'attempt' => $attempt,
            'peer_attempt' => $peerAttempt,
            'caller_leg_uuid' => is_string($callerLegUuid) && $callerLegUuid !== '' ? $callerLegUuid : null,
        ];
    }

    protected function findAttemptByLegUuid(string $tenantId, ?string $legUuid): ?CallDeliveryAttempt
    {
        if (! is_string($legUuid) || $legUuid === '') {
            return null;
        }

        return CallDeliveryAttempt::query()
            ->with('callSession')
            ->where('freeswitch_leg_uuid', $legUuid)
            ->whereHas('callSession', function ($query) use ($tenantId): void {
                $query->where('tenant_id', $tenantId);
            })
            ->latest('created_at')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $callData
     * @param  array{call_session:?CallSession,attempt:?CallDeliveryAttempt,peer_attempt:?CallDeliveryAttempt,caller_leg_uuid:?string}  $context
     * @return array<string, mixed>
     */
    protected function augmentCallData(array $callData, array $context): array
    {
        $session = $context['call_session'];
        $attempt = $context['attempt'];
        $peerAttempt = $context['peer_attempt'];

        if ($session instanceof CallSession) {
            $callData['call_session_id'] = $session->id;
            $callData['orchestration_call_uuid'] = $session->call_uuid;
        }

        if ($context['caller_leg_uuid']) {
            $callData['caller_leg_uuid'] = $context['caller_leg_uuid'];
        }

        if ($attempt instanceof CallDeliveryAttempt) {
            $callData['delivery_attempt_id'] = $attempt->id;
            $callData['endpoint_binding_id'] = $attempt->endpoint_binding_id;
            $callData['delivery_attempt_type'] = $attempt->attempt_type;
        }

        if ($peerAttempt instanceof CallDeliveryAttempt) {
            $callData['other_leg_attempt_id'] = $peerAttempt->id;
            $callData['other_leg_endpoint_binding_id'] = $peerAttempt->endpoint_binding_id;
            $callData['other_leg_attempt_type'] = $peerAttempt->attempt_type;
        }

        return $callData;
    }

    protected function markAttemptRinging(?CallDeliveryAttempt $attempt): void
    {
        if (! $attempt instanceof CallDeliveryAttempt) {
            return;
        }

        if (! in_array($attempt->status, [
            CallDeliveryAttempt::STATUS_PLANNED,
            CallDeliveryAttempt::STATUS_INITIATED,
        ], true)) {
            return;
        }

        $attempt->forceFill([
            'status' => CallDeliveryAttempt::STATUS_RINGING,
            'started_at' => $attempt->started_at ?? now(),
            'failure_reason' => null,
        ])->save();
    }

    protected function markAttemptAnswered(?CallDeliveryAttempt $attempt): void
    {
        if (! $attempt instanceof CallDeliveryAttempt) {
            return;
        }

        if (! in_array($attempt->status, [
            CallDeliveryAttempt::STATUS_PLANNED,
            CallDeliveryAttempt::STATUS_INITIATED,
            CallDeliveryAttempt::STATUS_RINGING,
        ], true)) {
            return;
        }

        $attempt->forceFill([
            'status' => CallDeliveryAttempt::STATUS_ANSWERED,
            'answered_at' => $attempt->answered_at ?? now(),
            'failure_reason' => null,
        ])->save();
    }

    protected function finalizeAttemptFromHangup(?CallSession $callSession, ?CallDeliveryAttempt $attempt, string $hangupCause): void
    {
        if (! $callSession instanceof CallSession || ! $attempt instanceof CallDeliveryAttempt) {
            return;
        }

        if (filled(data_get($callSession->variables, 'winner_attempt_id'))) {
            return;
        }

        if (! in_array($attempt->status, CallDeliveryAttempt::ACTIVE_STATUSES, true)) {
            return;
        }

        [$status, $failureReason, $metadata] = $this->hangupOutcome($attempt, $hangupCause);

        $attempt->forceFill([
            'status' => $status,
            'ended_at' => $attempt->ended_at ?? now(),
            'failure_reason' => $failureReason,
            'metadata' => [
                ...($attempt->metadata ?? []),
                ...$metadata,
            ],
        ])->save();
    }

    protected function hangupStatus(string $hangupCause): string
    {
        return match ($hangupCause) {
            'NO_ANSWER', 'NO_USER_RESPONSE', 'ALLOTTED_TIMEOUT' => CallDeliveryAttempt::STATUS_TIMED_OUT,
            'ORIGINATOR_CANCEL', 'LOSE_RACE' => CallDeliveryAttempt::STATUS_CANCELLED,
            default => CallDeliveryAttempt::STATUS_FAILED,
        };
    }

    /**
     * @return array{0:string,1:string,2:array<string,mixed>}
     */
    protected function hangupOutcome(CallDeliveryAttempt $attempt, string $hangupCause): array
    {
        if ($this->isAwaitingPstnConfirmation($attempt)) {
            return [
                CallDeliveryAttempt::STATUS_FAILED,
                'confirmation_not_received',
                [
                    'awaiting_confirmation' => false,
                    'confirmation_failed_at' => now()->toIso8601String(),
                    'confirmation_failure_cause' => $hangupCause,
                ],
            ];
        }

        return [
            $this->hangupStatus($hangupCause),
            strtolower($hangupCause),
            [],
        ];
    }

    protected function isAwaitingPstnConfirmation(CallDeliveryAttempt $attempt): bool
    {
        return $attempt->attempt_type === CallDeliveryAttempt::TYPE_PSTN
            && (bool) data_get($attempt->metadata, 'requires_confirmation', false)
            && (bool) data_get($attempt->metadata, 'awaiting_confirmation', false);
    }

    protected function persistBridgeContext(
        ?CallSession $callSession,
        ?CallDeliveryAttempt $attempt,
        ?CallDeliveryAttempt $peerAttempt,
        ?string $bridgeLegUuid,
        ?string $otherLegUuid,
    ): void {
        if (! $callSession instanceof CallSession) {
            return;
        }

        $bridgeAttempt = $attempt instanceof CallDeliveryAttempt
            ? $attempt
            : ($peerAttempt instanceof CallDeliveryAttempt ? $peerAttempt : null);

        $callSession->forceFill([
            'variables' => [
                ...($callSession->variables ?? []),
                'delivery_bridge_last_event_at' => now()->toIso8601String(),
                'delivery_bridge_leg_uuid' => $bridgeLegUuid,
                'delivery_bridge_other_leg_uuid' => $otherLegUuid,
                'delivery_bridge_attempt_id' => $bridgeAttempt?->id,
            ],
        ])->save();

        if (! $bridgeAttempt instanceof CallDeliveryAttempt) {
            return;
        }

        $bridgeAttempt->forceFill([
            'metadata' => [
                ...($bridgeAttempt->metadata ?? []),
                'bridge_last_event_at' => now()->toIso8601String(),
                'bridge_leg_uuid' => $bridgeLegUuid,
                'bridge_other_leg_uuid' => $otherLegUuid,
            ],
        ])->save();
    }

    protected function electWinnerForAnsweredAttempt(?CallSession $callSession, ?CallDeliveryAttempt $attempt): void
    {
        if (! $callSession instanceof CallSession || ! $attempt instanceof CallDeliveryAttempt) {
            return;
        }

        if (! in_array($attempt->status, [
            CallDeliveryAttempt::STATUS_ANSWERED,
            CallDeliveryAttempt::STATUS_CONFIRMED,
        ], true)) {
            return;
        }

        if (filled(data_get($callSession->variables, 'winner_attempt_id'))) {
            return;
        }

        $this->winnerService()->electWinner($callSession, $attempt);
    }

    protected function finalizeWinningBridge(
        ?CallSession $callSession,
        ?CallDeliveryAttempt $attempt,
        ?CallDeliveryAttempt $peerAttempt,
        ?string $bridgeLegUuid,
        ?string $otherLegUuid,
    ): void {
        if (! $callSession instanceof CallSession) {
            return;
        }

        $winnerAttemptId = data_get($callSession->variables, 'winner_attempt_id');

        if (! filled($winnerAttemptId)) {
            return;
        }

        $bridgeAttempt = $attempt instanceof CallDeliveryAttempt
            ? $attempt
            : ($peerAttempt instanceof CallDeliveryAttempt ? $peerAttempt : null);

        if (! $bridgeAttempt instanceof CallDeliveryAttempt || $bridgeAttempt->id !== $winnerAttemptId) {
            return;
        }

        $callSession->forceFill([
            'variables' => [
                ...($callSession->variables ?? []),
                'winner_bridge_leg_uuid' => $bridgeLegUuid,
                'winner_bridge_other_leg_uuid' => $otherLegUuid,
                'winner_bridge_completed_at' => now()->toIso8601String(),
            ],
        ])->save();

        $bridgeAttempt->forceFill([
            'metadata' => [
                ...($bridgeAttempt->metadata ?? []),
                'winner_bridge_leg_uuid' => $bridgeLegUuid,
                'winner_bridge_other_leg_uuid' => $otherLegUuid,
                'winner_bridge_completed_at' => now()->toIso8601String(),
            ],
        ])->save();
    }

    protected function cleanupWinningHangup(?CallSession $callSession, ?CallDeliveryAttempt $attempt): void
    {
        if (! $callSession instanceof CallSession || ! $attempt instanceof CallDeliveryAttempt) {
            return;
        }

        $winnerAttemptId = data_get($callSession->variables, 'winner_attempt_id');

        if (! filled($winnerAttemptId) || $winnerAttemptId !== $attempt->id) {
            return;
        }

        $attempt->forceFill([
            'status' => $attempt->status === CallDeliveryAttempt::STATUS_WON ? CallDeliveryAttempt::STATUS_WON : $attempt->status,
            'ended_at' => $attempt->ended_at ?? now(),
        ])->save();

        $this->winnerService()->cleanupAfterWinnerHangup($callSession, $attempt);

        $freshSession = $callSession->fresh(['deliveryAttempts']);

        if (! $freshSession instanceof CallSession || $freshSession->activeDeliveryAttempts()->exists()) {
            return;
        }

        $freshSession->forceFill([
            'state' => 'ended',
            'ended_at' => $freshSession->ended_at ?? now(),
            'variables' => [
                ...($freshSession->variables ?? []),
                'winner_hangup_completed_at' => now()->toIso8601String(),
            ],
        ])->save();
    }

    protected function resolveRegistrationBinding(string $tenantId, string $user): ?EndpointBinding
    {
        if ($user === '') {
            return null;
        }

        return EndpointBinding::query()
            ->with(['tenant', 'extension'])
            ->where('tenant_id', $tenantId)
            ->where('is_enabled', true)
            ->whereHas('extension', function ($query) use ($user): void {
                $query->where('extension', $user)->where('is_active', true);
            })
            ->orderByDesc('allow_late_join_after_push')
            ->orderBy('type')
            ->first();
    }

    protected function updateReachabilityFromRegistration(string $tenantId, ?EndpointBinding $binding, array $regData, string $action): void
    {
        if (! $binding instanceof EndpointBinding) {
            return;
        }

        $candidate = $this->candidateForBinding($binding);
        $attributes = [
            'registration_user' => strtolower((string) $regData['user']),
            'realm' => strtolower((string) $regData['domain']),
            'contact' => $regData['contact'] ?: null,
            'user_agent' => $regData['user_agent'] ?: null,
            'network_ip' => $regData['network_ip'] ?: null,
        ];

        if ($action === 'registered') {
            $this->reachabilityCache()->markRegistered($tenantId, $candidate, $attributes);
            $binding->forceFill(['last_registered_at' => now()])->save();

            return;
        }

        $this->reachabilityCache()->markUnregistered($tenantId, $candidate, $attributes);
    }

    protected function tryLateJoinForRegistration(string $tenantId, ?EndpointBinding $binding, string $domain, array $regData): void
    {
        if (! $binding instanceof EndpointBinding || ! $binding->allow_late_join_after_push) {
            return;
        }

        $sessions = CallSession::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('ended_at')
            ->where(function ($query): void {
                $query->where('state', 'parked')->orWhere('state', 'bridged');
            })
            ->get();

        foreach ($sessions as $callSession) {
            if (! $this->canLateJoinSession($callSession, $binding)) {
                continue;
            }

            $this->originateLateJoinAttempt($callSession, $binding, $domain, $regData);
        }
    }

    protected function canLateJoinSession(CallSession $callSession, EndpointBinding $binding): bool
    {
        if (filled(data_get($callSession->variables, 'winner_attempt_id'))) {
            return false;
        }

        $windowUntil = data_get($callSession->variables, 'delivery_wake_window_until');
        if (! is_string($windowUntil) || $windowUntil === '') {
            return false;
        }

        try {
            if (Carbon::parse($windowUntil)->isPast()) {
                return false;
            }
        } catch (\Throwable) {
            return false;
        }

        $lateJoinBindings = data_get($callSession->variables, 'delivery_late_join_bindings', []);
        if (! is_array($lateJoinBindings) || ! array_key_exists($binding->id, $lateJoinBindings)) {
            return false;
        }

        if (! $callSession->deliveryAttempts()->exists()) {
            return false;
        }

        return ! $callSession->deliveryAttempts()
            ->where('endpoint_binding_id', $binding->id)
            ->whereIn('attempt_type', [CallDeliveryAttempt::TYPE_SIP, CallDeliveryAttempt::TYPE_LATE_SIP])
            ->whereIn('status', CallDeliveryAttempt::ACTIVE_STATUSES)
            ->exists();
    }

    protected function originateLateJoinAttempt(CallSession $callSession, EndpointBinding $binding, string $domain, array $regData): void
    {
        $candidate = $this->candidateForBinding($binding);
        $windowUntil = data_get($callSession->variables, 'delivery_late_join_bindings.'.$binding->id.'.late_join_window_until')
            ?? data_get($callSession->variables, 'delivery_wake_window_until');

        $plan = new DeliveryPlan(
            callSessionId: $callSession->id,
            wakeWindowSeconds: 0,
            immediateSipWave: [
                new DeliveryPlanItem(
                    candidate: $candidate,
                    decision: new ReachabilityDecision(
                        endpointBindingId: $binding->id,
                        status: ReachabilityDecision::STATUS_ONLINE_SIP,
                        canRingNow: true,
                        shouldSendPush: false,
                        allowLateJoinWindowUntil: is_string($windowUntil) ? $windowUntil : null,
                        shouldOfferPstn: false,
                        decisionReason: 'late_join_registration',
                        metadata: ['source' => 'sofia_register'],
                    ),
                    wave: 'late_join',
                    attemptType: CallDeliveryAttempt::TYPE_LATE_SIP,
                    lateJoinWindowUntil: is_string($windowUntil) ? $windowUntil : null,
                    metadata: [
                        'origin' => 'sofia_register',
                        'registration_user' => $regData['user'],
                    ],
                ),
            ],
        );

        $this->callOfferExecutor()->executePlan($plan, [
            'caller_leg_uuid' => $callSession->call_uuid,
            'caller_id_name' => (string) data_get($callSession->variables, 'caller_id_name', 'Inbound Call'),
            'caller_id_number' => (string) data_get($callSession->variables, 'caller_id_number', 'unknown'),
            'tenant_domain' => $domain,
        ]);
    }

    protected function candidateForBinding(EndpointBinding $binding): EndpointCandidate
    {
        $extension = $binding->extension;
        $sipAor = $extension && $binding->tenant?->domain
            ? sprintf('sip:%s@%s', $extension->extension, $binding->tenant->domain)
            : null;

        return new EndpointCandidate(
            endpointBindingId: $binding->id,
            ownerType: $binding->agent_id ? 'agent' : 'extension',
            ownerId: $binding->agent_id ?: (string) $binding->extension_id,
            candidateType: $binding->type,
            sipAor: $sipAor,
            pushCapable: $binding->is_push_capable && $binding->hasPushTokenMaterial(),
            allowLateJoinAfterPush: $binding->allow_late_join_after_push,
            forwardNumber: $binding->type === EndpointBinding::TYPE_PSTN_FORWARD ? $binding->forward_number : null,
            forwardRequiresConfirm: $binding->type === EndpointBinding::TYPE_PSTN_FORWARD ? $binding->forward_requires_confirm : false,
            priority: 0,
            sourcePath: [['type' => 'registration', 'id' => $binding->id]],
        );
    }

    protected function extractQualityMetrics(array $event): array
    {
        $rtpQuality = $event['variable_rtp_audio_in_quality_percentage'] ?? null;
        $mosScore = $event['variable_rtp_audio_in_mos'] ?? null;

        $packetsReceived = (int) ($event['variable_rtp_audio_in_packet_count'] ?? 0);
        $packetsLost = (int) ($event['variable_rtp_audio_in_skip_packet_count'] ?? 0);
        $packetLoss = ($packetsReceived + $packetsLost) > 0
            ? round(($packetsLost / ($packetsReceived + $packetsLost)) * 100, 2)
            : null;

        $jitter = $event['variable_rtp_audio_in_jitter_max_variance'] ?? null;
        $latency = $event['variable_rtp_audio_in_mean_interval'] ?? null;

        return [
            'quality_score' => $rtpQuality !== null ? (int) round((float) $rtpQuality) : null,
            'mos_score' => $mosScore !== null ? round((float) $mosScore, 2) : null,
            'packet_loss' => $packetLoss,
            'jitter' => $jitter !== null ? (int) round((float) $jitter) : null,
            'latency' => $latency !== null ? (int) round((float) $latency) : null,
        ];
    }

    protected function classifyCallType(array $event, string $direction): ?string
    {
        $appName = $event['variable_current_application'] ?? '';
        if ($appName === 'conference') {
            return 'conference';
        }

        $callerDomain = $event['variable_domain_name'] ?? '';
        $destDomain = $event['variable_dialed_domain'] ?? '';
        if ($callerDomain && $destDomain && $callerDomain === $destDomain) {
            return 'internal';
        }

        return match ($direction) {
            'inbound' => 'inbound',
            'outbound' => 'outbound',
            default => null,
        };
    }

    protected function createCdr(string $tenantId, array $data, array $event): void
    {
        try {
            $meta = $data['metadata'] ?? $data;
            $direction = in_array($meta['direction'] ?? '', ['inbound', 'outbound', 'local'])
                ? $meta['direction']
                : 'local';

            $qualityMetrics = $this->extractQualityMetrics($event);
            $callType = $this->classifyCallType($event, $direction);

            $cdr = CallDetailRecord::create([
                'tenant_id' => $tenantId,
                'uuid' => $meta['uuid'] ?? $data['call_uuid'] ?? '',
                'caller_id_name' => $meta['caller_id_name'] ?? '',
                'caller_id_number' => $meta['caller_id_number'] ?? '',
                'destination_number' => $meta['destination_number'] ?? '',
                'context' => $event['Caller-Context'] ?? null,
                'start_stamp' => $event['variable_start_stamp'] ?? now(),
                'answer_stamp' => $event['variable_answer_stamp'] ?? null,
                'end_stamp' => $event['variable_end_stamp'] ?? now(),
                'duration' => $meta['duration'] ?? 0,
                'billsec' => $meta['billsec'] ?? 0,
                'hangup_cause' => $meta['hangup_cause'] ?? 'NORMAL_CLEARING',
                'direction' => $direction,
                'recording_path' => $event['variable_record_file_path'] ?? null,
                'sip_user_agent' => $event['variable_sip_user_agent'] ?? null,
                'remote_media_ip' => $event['variable_remote_media_ip'] ?? null,
                'call_type' => $callType,
                'quality_score' => $qualityMetrics['quality_score'],
                'mos_score' => $qualityMetrics['mos_score'],
                'packet_loss' => $qualityMetrics['packet_loss'],
                'jitter' => $qualityMetrics['jitter'],
                'latency' => $qualityMetrics['latency'],
            ]);

            CallDetailRecordCreated::dispatch($cdr);
        } catch (\Exception $e) {
            Log::error('Failed to create CDR', ['error' => $e->getMessage(), 'uuid' => $data['call_uuid'] ?? 'unknown']);
        }
    }

    protected function recordEvent(string $tenantId, string $eventType, array $data, ?CallSession $callSession = null): void
    {
        try {
            $tenant = Tenant::find($tenantId);

            if ($tenant) {
                $this->eventIngestionService()->ingest(
                    $tenant,
                    $eventType,
                    (string) ($data['call_uuid'] ?? $data['uuid'] ?? $data['user'] ?? ''),
                    $data,
                    $callSession,
                );

                return;
            }

            CallEventLog::create([
                'call_session_id' => $callSession?->id,
                'tenant_id' => $tenantId,
                'call_uuid' => $data['call_uuid'] ?? $data['uuid'] ?? $data['user'] ?? '',
                'event_type' => $eventType,
                'payload' => $data,
                'schema_version' => CallEventLog::SCHEMA_VERSION,
                'occurred_at' => now(),
                'received_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to record call event', ['error' => $e->getMessage(), 'event_type' => $eventType]);
        }
    }

    protected function eventIngestionService(): CallEventIngestionService
    {
        return $this->callEventIngestionService ??= app(CallEventIngestionService::class);
    }

    protected function winnerService(): CallWinnerService
    {
        return $this->callWinnerService ??= app(CallWinnerService::class);
    }

    protected function reachabilityCache(): ReachabilityCache
    {
        return $this->reachabilityCache ??= app(ReachabilityCache::class);
    }

    protected function callOfferExecutor(): CallOfferExecutor
    {
        return $this->callOfferExecutor ??= app(CallOfferExecutor::class);
    }

    protected function voicemailEventService(): VoicemailEventService
    {
        return $this->voicemailEventService ??= app(VoicemailEventService::class);
    }

    protected function moduleRegistry(): ModuleRegistry
    {
        return $this->moduleRegistry ??= app(ModuleRegistry::class);
    }

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
