<?php

namespace App\Services\Call;

use App\Models\CallDeliveryAttempt;
use App\Models\CallSession;
use App\Services\Media\FreeSwitchCommandService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CallWinnerService
{
    public function __construct(
        protected TraceWriter $traceWriter,
        protected FreeSwitchCommandService $freeSwitchCommandService,
        protected AnsweredElsewhereService $answeredElsewhereService,
    ) {}

    /**
     * @return array{status:string,winner_attempt_id:?string,attempt_id:string,call_session_id:string}
     */
    public function electWinner(CallSession $callSession, CallDeliveryAttempt $candidateAttempt): array
    {
        $callSession = $callSession->fresh();
        $candidateAttempt = $candidateAttempt->fresh(['endpointBinding']);

        if (! $callSession instanceof CallSession || ! $candidateAttempt instanceof CallDeliveryAttempt) {
            throw new \InvalidArgumentException('Call session and candidate attempt must exist before winner election.');
        }

        if ($candidateAttempt->call_session_id !== $callSession->id) {
            throw new \InvalidArgumentException('Candidate attempt does not belong to the provided call session.');
        }

        if (filled(data_get($callSession->variables, 'winner_attempt_id'))) {
            return $this->electAgainstExistingWinner($callSession, $candidateAttempt);
        }

        if (! in_array($candidateAttempt->status, [
            CallDeliveryAttempt::STATUS_ANSWERED,
            CallDeliveryAttempt::STATUS_CONFIRMED,
        ], true)) {
            throw new \InvalidArgumentException('Candidate attempt must be answered or confirmed before winner election.');
        }

        if ($this->requiresConfirmation($candidateAttempt) && $candidateAttempt->status !== CallDeliveryAttempt::STATUS_CONFIRMED) {
            $this->markAttemptAwaitingConfirmation($callSession, $candidateAttempt);

            return [
                'status' => 'awaiting_confirmation',
                'winner_attempt_id' => data_get($callSession->variables, 'winner_attempt_id'),
                'attempt_id' => $candidateAttempt->id,
                'call_session_id' => $callSession->id,
            ];
        }

        $winnerContext = DB::transaction(function () use ($callSession, $candidateAttempt): array {
            $session = CallSession::query()
                ->with(['deliveryAttempts.endpointBinding'])
                ->findOrFail($callSession->id);

            $attempt = $session->deliveryAttempts->firstWhere('id', $candidateAttempt->id);

            if (! $attempt instanceof CallDeliveryAttempt) {
                throw new \RuntimeException('Candidate attempt was not found for winner election.');
            }

            $existingWinnerAttemptId = data_get($session->variables, 'winner_attempt_id');
            $expectedVersion = $session->lock_version;

            if ($existingWinnerAttemptId) {
                $existingWinner = $session->deliveryAttempts->firstWhere('id', $existingWinnerAttemptId)
                    ?? CallDeliveryAttempt::query()->with('endpointBinding')->find($existingWinnerAttemptId);

                if ($attempt->status !== CallDeliveryAttempt::STATUS_LOST) {
                    $this->terminalizeAttempt(
                        $attempt,
                        CallDeliveryAttempt::STATUS_LOST,
                        'winner_already_committed',
                        $session,
                    );
                }

                return [
                    'status' => 'existing_winner',
                    'session' => $session,
                    'winner' => $existingWinner,
                    'candidate' => $attempt->fresh(['endpointBinding']),
                    'losers' => collect(),
                ];
            }

            $winnerCommittedAt = $attempt->answered_at ?? $attempt->updated_at ?? now();
            $sessionVariables = [
                ...($session->variables ?? []),
                'winner_attempt_id' => $attempt->id,
                'winner_leg_uuid' => $attempt->freeswitch_leg_uuid,
                'winner_committed_at' => $winnerCommittedAt instanceof Carbon
                    ? $winnerCommittedAt->toIso8601String()
                    : Carbon::parse($winnerCommittedAt)->toIso8601String(),
                'winner_endpoint_binding_id' => $attempt->endpoint_binding_id,
                'winner_attempt_type' => $attempt->attempt_type,
                'winner_lock_version' => $expectedVersion + 1,
            ];

            $updated = CallSession::query()
                ->whereKey($session->id)
                ->where('lock_version', $expectedVersion)
                ->update([
                    'variables' => $sessionVariables,
                    'state' => 'bridged',
                    'lock_version' => $expectedVersion + 1,
                    'updated_at' => now(),
                ]);

            if ($updated !== 1) {
                $freshSession = CallSession::query()->with(['deliveryAttempts.endpointBinding'])->findOrFail($session->id);
                $freshWinnerAttemptId = data_get($freshSession->variables, 'winner_attempt_id');
                $freshWinner = $freshWinnerAttemptId
                    ? ($freshSession->deliveryAttempts->firstWhere('id', $freshWinnerAttemptId)
                        ?? CallDeliveryAttempt::query()->with('endpointBinding')->find($freshWinnerAttemptId))
                    : null;

                $this->terminalizeAttempt(
                    $attempt,
                    CallDeliveryAttempt::STATUS_LOST,
                    'winner_already_committed',
                    $freshSession,
                );

                return [
                    'status' => 'race_lost',
                    'session' => $freshSession,
                    'winner' => $freshWinner,
                    'candidate' => $attempt->fresh(['endpointBinding']),
                    'losers' => collect(),
                ];
            }

            $attempt = $attempt->fresh(['endpointBinding']);
            $attempt->forceFill([
                'status' => CallDeliveryAttempt::STATUS_WON,
                'answered_at' => $attempt->answered_at ?? now(),
                'ended_at' => null,
                'failure_reason' => null,
                'metadata' => [
                    ...($attempt->metadata ?? []),
                    'winner_committed_at' => $sessionVariables['winner_committed_at'],
                    'winner_lock_version' => $expectedVersion + 1,
                ],
            ])->save();

            $session = CallSession::query()->with(['deliveryAttempts.endpointBinding'])->findOrFail($session->id);

            $losers = $session->deliveryAttempts
                ->filter(fn (CallDeliveryAttempt $deliveryAttempt): bool => $deliveryAttempt->id !== $attempt->id)
                ->filter(fn (CallDeliveryAttempt $deliveryAttempt): bool => in_array($deliveryAttempt->status, [
                    CallDeliveryAttempt::STATUS_PLANNED,
                    CallDeliveryAttempt::STATUS_INITIATED,
                    CallDeliveryAttempt::STATUS_RINGING,
                    CallDeliveryAttempt::STATUS_ANSWERED,
                    CallDeliveryAttempt::STATUS_CONFIRMED,
                ], true));

            foreach ($losers as $losingAttempt) {
                $status = $this->loserStatus($losingAttempt);
                $reason = $status === CallDeliveryAttempt::STATUS_CANCELLED
                    ? 'answered_elsewhere'
                    : 'winner_already_committed';

                $this->terminalizeAttempt($losingAttempt, $status, $reason, $session);
            }

            return [
                'status' => 'winner_committed',
                'session' => $session->fresh(['deliveryAttempts.endpointBinding']),
                'winner' => $attempt->fresh(['endpointBinding']),
                'candidate' => $attempt->fresh(['endpointBinding']),
                'losers' => $losers->map(fn (CallDeliveryAttempt $losingAttempt) => $losingAttempt->fresh(['endpointBinding'])),
            ];
        });

        /** @var CallSession $session */
        $session = $winnerContext['session'];
        /** @var CallDeliveryAttempt|null $winner */
        $winner = $winnerContext['winner'];

        if ($winner instanceof CallDeliveryAttempt && $winnerContext['status'] === 'winner_committed') {
            $this->traceWriter->write($session, 'delivery.winner.committed', [
                'attempt_id' => $winner->id,
                'endpoint_binding_id' => $winner->endpoint_binding_id,
                'leg_uuid' => $winner->freeswitch_leg_uuid,
                'lock_version' => $session->lock_version,
            ]);

            $this->answeredElsewhereService->notifyAnsweredElsewhere(
                $session,
                $winner,
                $winnerContext['losers'],
            );
        }

        return [
            'status' => $winnerContext['status'],
            'winner_attempt_id' => $winner?->id,
            'attempt_id' => $candidateAttempt->id,
            'call_session_id' => $session->id,
        ];
    }

    public function cleanupAfterWinnerHangup(CallSession $callSession, CallDeliveryAttempt $winnerAttempt): void
    {
        $session = $callSession->fresh(['deliveryAttempts.endpointBinding']) ?? $callSession;

        $session->deliveryAttempts
            ->filter(fn (CallDeliveryAttempt $attempt): bool => $attempt->id !== $winnerAttempt->id)
            ->filter(fn (CallDeliveryAttempt $attempt): bool => in_array($attempt->status, CallDeliveryAttempt::ACTIVE_STATUSES, true))
            ->each(function (CallDeliveryAttempt $attempt) use ($session): void {
                $status = $this->loserStatus($attempt);
                $reason = $status === CallDeliveryAttempt::STATUS_CANCELLED
                    ? 'winner_hangup_cleanup'
                    : 'winner_hangup_cleanup';

                $this->terminalizeAttempt($attempt, $status, $reason, $session);
            });
    }

    /**
     * @return array{status:string,winner_attempt_id:?string,attempt_id:string,call_session_id:string}
     */
    protected function electAgainstExistingWinner(CallSession $callSession, CallDeliveryAttempt $candidateAttempt): array
    {
        $winnerAttemptId = data_get($callSession->variables, 'winner_attempt_id');
        $existingWinner = $winnerAttemptId
            ? CallDeliveryAttempt::query()->with('endpointBinding')->find($winnerAttemptId)
            : null;

        $this->terminalizeAttempt(
            $candidateAttempt,
            CallDeliveryAttempt::STATUS_LOST,
            'winner_already_committed',
            $callSession,
        );

        return [
            'status' => 'existing_winner',
            'winner_attempt_id' => $existingWinner?->id,
            'attempt_id' => $candidateAttempt->id,
            'call_session_id' => $callSession->id,
        ];
    }

    protected function requiresConfirmation(CallDeliveryAttempt $attempt): bool
    {
        return $attempt->attempt_type === CallDeliveryAttempt::TYPE_PSTN
            && (bool) data_get($attempt->metadata, 'requires_confirmation', false);
    }

    protected function markAttemptAwaitingConfirmation(CallSession $callSession, CallDeliveryAttempt $attempt): void
    {
        $attempt->forceFill([
            'failure_reason' => 'awaiting_confirmation',
            'metadata' => [
                ...($attempt->metadata ?? []),
                'awaiting_confirmation' => true,
            ],
        ])->save();

        $this->traceWriter->write($callSession, 'delivery.winner.awaiting_confirmation', [
            'attempt_id' => $attempt->id,
            'endpoint_binding_id' => $attempt->endpoint_binding_id,
        ]);
    }

    protected function loserStatus(CallDeliveryAttempt $attempt): string
    {
        return in_array($attempt->attempt_type, [CallDeliveryAttempt::TYPE_PUSH, CallDeliveryAttempt::TYPE_PSTN], true)
            ? CallDeliveryAttempt::STATUS_CANCELLED
            : CallDeliveryAttempt::STATUS_LOST;
    }

    protected function terminalizeAttempt(
        CallDeliveryAttempt $attempt,
        string $status,
        string $failureReason,
        CallSession $callSession,
    ): void {
        if ($attempt->status === $status && $attempt->failure_reason === $failureReason) {
            return;
        }

        $attempt->forceFill([
            'status' => $status,
            'ended_at' => $attempt->ended_at ?? now(),
            'failure_reason' => $failureReason,
            'metadata' => [
                ...($attempt->metadata ?? []),
                'winner_cleanup' => true,
            ],
        ])->save();

        if (filled($attempt->freeswitch_leg_uuid)) {
            $execution = $this->freeSwitchCommandService->execute('uuid_kill', [
                $attempt->freeswitch_leg_uuid,
                'LOSE_RACE',
            ]);

            $this->traceWriter->write($callSession, 'delivery.loser.cancel.requested', [
                'attempt_id' => $attempt->id,
                'endpoint_binding_id' => $attempt->endpoint_binding_id,
                'leg_uuid' => $attempt->freeswitch_leg_uuid,
                'status' => $status,
                'failure_reason' => $failureReason,
                'execution' => $execution,
            ]);

            return;
        }

        $this->traceWriter->write($callSession, 'delivery.loser.terminalized', [
            'attempt_id' => $attempt->id,
            'endpoint_binding_id' => $attempt->endpoint_binding_id,
            'status' => $status,
            'failure_reason' => $failureReason,
        ]);
    }
}
