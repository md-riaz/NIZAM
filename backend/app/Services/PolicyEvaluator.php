<?php

namespace App\Services;

use App\Models\CallRoutingPolicy;
use App\Models\Tenant;
use Carbon\Carbon;

class PolicyEvaluator
{
    /** Policy decision constants */
    public const DECISION_ALLOW = 'allow';

    public const DECISION_REDIRECT = 'redirect';

    public const DECISION_REJECT = 'reject';

    public const DECISION_MODIFY = 'modify';

    /**
     * Evaluate a policy and return a structured decision.
     *
     * @return array{decision: string, redirect_to?: string, metadata?: array, reason?: string}
     */
    public function evaluatePolicy(CallRoutingPolicy $policy, array $context = []): array
    {
        // Check tenant suspension
        if (isset($context['tenant_id'])) {
            $tenant = Tenant::find($context['tenant_id']);
            if ($tenant && ! $tenant->isOperational()) {
                return [
                    'decision' => self::DECISION_REJECT,
                    'reason' => 'Tenant is suspended or terminated.',
                ];
            }
        }

        // Evaluate blacklist first
        foreach ($policy->conditions ?? [] as $condition) {
            if (($condition['type'] ?? '') === 'blacklist') {
                $callerId = $context['caller_id'] ?? '';
                $numbers = $condition['params']['numbers'] ?? [];
                if (in_array($callerId, $numbers)) {
                    return [
                        'decision' => self::DECISION_REJECT,
                        'reason' => 'Caller is blacklisted.',
                    ];
                }
            }
        }

        $matched = $this->evaluate($policy, $context);

        if ($matched) {
            if ($policy->match_destination_type && $policy->match_destination_id) {
                return [
                    'decision' => self::DECISION_REDIRECT,
                    'redirect_to' => [
                        'type' => $policy->match_destination_type,
                        'id' => $policy->match_destination_id,
                    ],
                ];
            }

            return ['decision' => self::DECISION_ALLOW];
        }

        if ($policy->no_match_destination_type && $policy->no_match_destination_id) {
            return [
                'decision' => self::DECISION_REDIRECT,
                'redirect_to' => [
                    'type' => $policy->no_match_destination_type,
                    'id' => $policy->no_match_destination_id,
                ],
            ];
        }

        return ['decision' => self::DECISION_ALLOW];
    }

    /**
     * Evaluate all conditions in a policy. Returns true if all conditions match.
     */
    public function evaluate(CallRoutingPolicy $policy, array $context = []): bool
    {
        $conditions = $policy->conditions ?? [];

        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            if (! $this->evaluateCondition($condition, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a single condition against the provided context.
     */
    protected function evaluateCondition(array $condition, array $context): bool
    {
        $type = $condition['type'] ?? '';
        $params = $condition['params'] ?? [];

        return match ($type) {
            'time_of_day' => $this->evaluateTimeOfDay($params, $context),
            'day_of_week' => $this->evaluateDayOfWeek($params, $context),
            'caller_id_pattern' => $this->evaluateCallerIdPattern($params, $context),
            'blacklist' => $this->evaluateBlacklist($params, $context),
            'geo_prefix' => $this->evaluateGeoPrefix($params, $context),
            default => false,
        };
    }

    /**
     * Check if current time is within the specified range.
     */
    protected function evaluateTimeOfDay(array $params, array $context): bool
    {
        $now = $context['now'] ?? Carbon::now();
        $currentTime = $now->format('H:i');

        $start = $params['start'] ?? '00:00';
        $end = $params['end'] ?? '23:59';

        return $currentTime >= $start && $currentTime <= $end;
    }

    /**
     * Check if current day is in the allowed days list.
     */
    protected function evaluateDayOfWeek(array $params, array $context): bool
    {
        $now = $context['now'] ?? Carbon::now();
        $currentDay = strtolower($now->format('D'));

        $days = array_map('strtolower', $params['days'] ?? []);

        return in_array($currentDay, $days);
    }

    /**
     * Check if caller ID matches a pattern.
     */
    protected function evaluateCallerIdPattern(array $params, array $context): bool
    {
        $callerId = $context['caller_id'] ?? '';
        $pattern = $params['pattern'] ?? '';

        if (empty($callerId) || empty($pattern)) {
            return false;
        }

        // Convert simple wildcard pattern to regex
        $regex = '/^'.str_replace('\*', '.*', preg_quote($pattern, '/')).'$/';

        return (bool) preg_match($regex, $callerId);
    }

    /**
     * Check if caller ID is NOT in the blacklist.
     */
    protected function evaluateBlacklist(array $params, array $context): bool
    {
        $callerId = $context['caller_id'] ?? '';
        $numbers = $params['numbers'] ?? [];

        // Blacklist condition passes when caller is NOT in the list
        return ! in_array($callerId, $numbers);
    }

    /**
     * Check if caller ID starts with a geographic prefix.
     */
    protected function evaluateGeoPrefix(array $params, array $context): bool
    {
        $callerId = $context['caller_id'] ?? '';
        $prefixes = $params['prefixes'] ?? [];

        if (empty($callerId) || empty($prefixes)) {
            return false;
        }

        foreach ($prefixes as $prefix) {
            if (str_starts_with($callerId, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
