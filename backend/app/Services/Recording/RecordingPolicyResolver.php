<?php

namespace App\Services\Recording;

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
        $answeredTargetType = (string) ($context['answered_target_type'] ?? '');
        $candidates = $this->candidatesFor($answeredTargetType, $context);
        $resolutionChain = [];

        foreach ($candidates as $scope => $policy) {
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

            return [
                'resolved_mode' => $normalized,
                'should_record' => false,
                'winning_scope' => $scope,
                'resolution_chain' => $resolutionChain,
                'reason' => sprintf('%s policy does not match %s direction', $scope, $direction),
            ];
        }

        return [
            'resolved_mode' => RecordingPolicy::OFF,
            'should_record' => false,
            'winning_scope' => null,
            'resolution_chain' => $resolutionChain,
            'reason' => 'no recording policy resolved',
        ];
    }

    /**
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
