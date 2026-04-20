<?php

namespace App\Services;

use App\Models\Gateway;
use App\Models\SipProfile;

class TelephonyRuntimeHealthService
{
    public function evaluate(?string $sofiaStatusResponse, bool $connected, ?string $errorMessage = null): array
    {
        $expectedProfiles = SipProfile::query()
            ->where('is_active', true)
            ->pluck('name')
            ->values()
            ->all();

        $loadedProfiles = $this->parseLoadedProfiles($sofiaStatusResponse);
        $missingProfiles = array_values(array_diff($expectedProfiles, $loadedProfiles));
        $expectedGatewayCount = Gateway::query()
            ->where('is_active', true)
            ->where('register', true)
            ->count();
        $loadedGatewayCount = $this->parseGatewayCount($sofiaStatusResponse);

        $status = 'healthy';
        $message = 'Telephony runtime healthy.';
        $recommendedAction = null;
        $fatalReason = null;

        if (! $connected) {
            $status = 'fatal';
            $fatalReason = 'esl_unreachable';
            $message = $errorMessage ?: 'FreeSWITCH ESL connection is unavailable.';
            $recommendedAction = 'Verify freeswitch container is running and ESL port is reachable.';
        } elseif (empty($loadedProfiles)) {
            $status = 'fatal';
            $fatalReason = 'no_loaded_profiles';
            $message = sprintf(
                'FreeSWITCH loaded 0 SIP profiles; expected %s.',
                empty($expectedProfiles) ? 'none' : implode(', ', $expectedProfiles)
            );
            $recommendedAction = 'Inspect FreeSWITCH startup logs and runtime SIP profile include paths.';
        } elseif (! empty($missingProfiles)) {
            $status = 'fatal';
            $fatalReason = 'missing_core_profiles';
            $message = sprintf(
                'FreeSWITCH missing required SIP profiles: %s.',
                implode(', ', $missingProfiles)
            );
            $recommendedAction = 'Rebuild generated SIP profiles and restart Sofia after config validation.';
        } elseif ($expectedGatewayCount > 0 && $loadedGatewayCount === 0) {
            $status = 'degraded';
            $message = sprintf(
                'FreeSWITCH loaded SIP profiles, but 0 runtime gateways are visible while %d registering gateway(s) are configured.',
                $expectedGatewayCount
            );
            $recommendedAction = 'Check gateway lifecycle logs and run Sofia rescan for external profile.';
        } elseif ($expectedGatewayCount > $loadedGatewayCount) {
            $status = 'degraded';
            $message = sprintf(
                'FreeSWITCH shows %d of %d expected registering gateway(s).',
                $loadedGatewayCount,
                $expectedGatewayCount
            );
            $recommendedAction = 'Inspect gateway credentials and Sofia gateway status for failed registrations.';
        }

        return [
            'status' => $status,
            'message' => $message,
            'fatal_reason' => $fatalReason,
            'expected_profiles' => $expectedProfiles,
            'loaded_profiles' => $loadedProfiles,
            'missing_profiles' => $missingProfiles,
            'expected_gateway_count' => $expectedGatewayCount,
            'loaded_gateway_count' => $loadedGatewayCount,
            'recommended_action' => $recommendedAction,
            'checked_at' => now()->toIso8601String(),
            'source' => 'esl',
        ];
    }

    protected function parseLoadedProfiles(?string $response): array
    {
        if (! $response) {
            return [];
        }

        $profiles = [];

        foreach (explode("\n", trim($response)) as $line) {
            $line = trim($line);

            if ($line === '' || str_contains($line, '===')) {
                continue;
            }

            if (preg_match('/^(\S+)\s+profile\s+/i', $line, $matches)) {
                $profiles[] = $matches[1];
            }
        }

        return array_values(array_unique($profiles));
    }

    protected function parseGatewayCount(?string $response): int
    {
        if (! $response) {
            return 0;
        }

        if (preg_match('/(\d+)\s+gateways?/i', $response, $matches)) {
            return (int) $matches[1];
        }

        $count = 0;
        foreach (explode("\n", trim($response)) as $line) {
            if (preg_match('/^[^:]+::\S+\s+\S+\s+\S+/i', trim($line))) {
                $count++;
            }
        }

        return $count;
    }
}
