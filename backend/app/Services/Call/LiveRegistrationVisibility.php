<?php

namespace App\Services\Call;

use App\Models\Organization;
use App\Services\EslConnectionManager;

class LiveRegistrationVisibility
{
    public function __construct(
        protected EslConnectionManager $esl,
    ) {}

    /**
     * @return array<string, array<string, mixed>>|null
     */
    public function forOrganization(Organization $organization): ?array
    {
        $response = $this->esl->api('show registrations as json');

        if (! $response) {
            return null;
        }

        $domain = strtolower((string) $organization->domain);
        $registrations = [];

        foreach ($this->parseJsonResponse($response) as $registration) {
            $realm = strtolower((string) ($registration['realm'] ?? $registration['hostname'] ?? ''));
            $user = strtolower((string) ($registration['reg_user'] ?? $registration['user'] ?? ''));

            if ($realm === '' || $user === '' || $realm !== $domain) {
                continue;
            }

            $registrations[$user] = [
                'registered' => true,
                'registration_user' => $user,
                'realm' => $realm,
                'contact' => $registration['contact'] ?? null,
                'user_agent' => $registration['agent'] ?? null,
                'network_ip' => $registration['network_ip'] ?? null,
                'network_port' => $registration['network_port'] ?? null,
                'source' => 'esl_live',
            ];
        }

        return $registrations;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function parseJsonResponse(string $raw): array
    {
        $jsonStart = strpos($raw, '{');

        if ($jsonStart === false) {
            return [];
        }

        $json = substr($raw, $jsonStart);
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return [];
        }

        $rows = $decoded['rows'] ?? $decoded['registrations'] ?? [];

        return is_array($rows) ? array_values($rows) : [];
    }
}
