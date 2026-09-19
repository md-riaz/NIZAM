<?php

namespace App\Services\Recording;

/**
 * Decides whether a call is recorded, from the policies of the scopes it touches.
 *
 * The scopes are walked most specific first, and the first one with an opinion
 * about *this* call decides. Two things count as an opinion:
 *
 * - `off` — an administrator has said this scope is not to be recorded. It wins
 *   over anything broader, which is what an opt-out has to do.
 * - a mode matching the call's direction — this scope asks for the recording.
 *
 * A mode that does not match the direction is not an opinion about this call.
 * That is the distinction this resolver used to miss: it answered for the whole
 * call at the first scope with any non-`inherit` value, so an extension set to
 * `incoming` suppressed that extension's outbound calls outright rather than
 * simply not asking for them. The number's and the organization's policies were
 * never consulted, though either might have wanted the call recorded.
 *
 * FusionPBX reaches the same answer by a different route, and comparing them is
 * a useful check. It has no `off`: `user_record` on an extension and
 * `destination_record` on a number are evaluated independently, in different
 * dialplan contexts, and neither consults the other — so a number set to record
 * records whatever the extension says. With no value able to veto, "first
 * opinion wins" and "any scope may ask" agree in every case it can express. The
 * veto is what `off` adds on top.
 */
class RecordingPolicyResolver
{
    /**
     * @param  array<string, mixed>  $context
     * @return array{
     *     resolved_mode: string,
     *     should_record: bool,
     *     winning_scope: string|null,
     *     resolution_chain: array<int, string>,
     *     reason: string
     * }
     */
    public function resolve(array $context): array
    {
        $direction = ($context['direction'] ?? 'inbound') === 'outbound' ? 'outbound' : 'inbound';
        $resolutionChain = [];

        // The nearest scope that had a mode set but not for this direction. It
        // decides nothing, but it is the most useful thing to report when no
        // scope asked: "the organization records incoming calls" explains an
        // unrecorded outbound call far better than "no policy".
        $nearestScope = null;
        $nearestMode = null;

        foreach ($this->candidatesFor((string) ($context['answered_target_type'] ?? ''), $context) as $scope => $policy) {
            $normalized = RecordingPolicy::normalize($policy);
            $resolutionChain[] = sprintf('%s:%s', $scope, $normalized);

            if ($normalized === RecordingPolicy::INHERIT) {
                continue;
            }

            if ($normalized === RecordingPolicy::OFF) {
                return [
                    'resolved_mode' => $normalized,
                    'should_record' => false,
                    'winning_scope' => $scope,
                    'resolution_chain' => $resolutionChain,
                    'reason' => sprintf('%s policy disables recording', $scope),
                ];
            }

            if (RecordingPolicy::matchesDirection($normalized, $direction)) {
                return [
                    'resolved_mode' => $normalized,
                    'should_record' => true,
                    'winning_scope' => $scope,
                    'resolution_chain' => $resolutionChain,
                    'reason' => sprintf('%s policy enables %s recording', $scope, $direction),
                ];
            }

            // A mode set for the other direction. Not an opinion about this
            // call, so the walk continues — but worth remembering for the
            // explanation if nothing further out asks either.
            $nearestScope ??= $scope;
            $nearestMode ??= $normalized;
        }

        if ($nearestScope !== null) {
            return [
                'resolved_mode' => $nearestMode,
                'should_record' => false,
                'winning_scope' => $nearestScope,
                'resolution_chain' => $resolutionChain,
                'reason' => sprintf('%s policy does not match %s direction', $nearestScope, $direction),
            ];
        }

        return [
            'resolved_mode' => RecordingPolicy::OFF,
            'should_record' => false,
            'winning_scope' => null,
            'resolution_chain' => $resolutionChain,
            'reason' => sprintf('no policy requests %s recording', $direction),
        ];
    }

    /**
     * The scopes this call passes through, most specific first.
     *
     * A call answered by an extension carries that extension's policy as well as
     * the number's and the organization's. One that ended somewhere else — a
     * voicemail box, an unanswered number — has no extension to ask.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function candidatesFor(string $answeredTargetType, array $context): array
    {
        if ($this->usesExtensionPrecedence($answeredTargetType)) {
            return [
                'extension' => $context['extension_policy'] ?? null,
                'did' => $context['did_policy'] ?? null,
                'organization' => $context['organization_policy'] ?? null,
            ];
        }

        return [
            'did' => $context['did_policy'] ?? null,
            'organization' => $context['organization_policy'] ?? null,
        ];
    }

    protected function usesExtensionPrecedence(string $answeredTargetType): bool
    {
        return $answeredTargetType === 'extension';
    }
}
