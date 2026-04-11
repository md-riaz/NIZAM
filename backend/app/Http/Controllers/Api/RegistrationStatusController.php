<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Extension;
use App\Models\Gateway;
use App\Models\Tenant;
use App\Services\EslConnectionManager;
use App\Services\SipRegistrationService;
use Illuminate\Http\JsonResponse;

/**
 * API controller for querying real-time SIP registration status from FreeSWITCH via ESL.
 */
class RegistrationStatusController extends Controller
{
    public function __construct(
        protected EslConnectionManager $esl,
        protected SipRegistrationService $registrationService
    ) {}

    /**
     * Get bulk registration status for all extensions in a tenant.
     *
     * Queries FreeSWITCH each active SIP profile using XML status
     * and filters for the tenant's domain, returning a map of extension => status.
     */
    public function bulkExtensionStatus(Tenant $tenant): JsonResponse
    {
        $allRegistrations = $this->registrationService->getAllRegistrations();
        $domain = $tenant->domain;

        $statusMap = [];
        foreach ($tenant->extensions as $ext) {
            $statusMap[$ext->extension] = [
                'extension' => $ext->extension,
                'registered' => false,
                'user_agent' => null,
                'network_ip' => null,
                'network_port' => null,
            ];
        }

        foreach ($allRegistrations as $reg) {
            $regUser = $reg['reg_user'] ?? '';
            $regHost = $reg['sip_auth_realm'] ?? '';

            if ($regHost === $domain && isset($statusMap[$regUser])) {
                $statusMap[$regUser]['registered'] = true;
                $statusMap[$regUser]['user_agent'] = $reg['agent'] ?? null;
                $statusMap[$regUser]['network_ip'] = $reg['network_ip'] ?? null;
                $statusMap[$regUser]['network_port'] = $reg['network_port'] ?? null;
            }
        }

        return response()->json([
            'data' => array_values($statusMap),
            'meta' => ['source' => 'esl', 'domain' => $domain],
        ]);
    }

    /**
     * Get registration status for a single extension.
     */
    public function extensionStatus(Tenant $tenant, Extension $extension): JsonResponse
    {
        if ($extension->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Extension not found.'], 404);
        }

        $command = "sofia status profile internal reg {$extension->extension}@{$tenant->domain}";
        $response = $this->esl->api($command);

        if (! $response) {
            return response()->json([
                'data' => ['registered' => false],
                'meta' => ['source' => 'esl', 'error' => 'FreeSWITCH unreachable'],
            ], 503);
        }

        $registered = ! str_contains($response, 'Cannot find registration');

        $data = [
            'extension' => $extension->extension,
            'registered' => $registered,
            'user_agent' => null,
            'network_ip' => null,
        ];

        if ($registered) {
            // Parse agent and IP from the ESL text response
            if (preg_match('/Agent:\s*(.+)/i', $response, $m)) {
                $data['user_agent'] = trim($m[1]);
            }
            if (preg_match('/IP:\s*(\S+)/i', $response, $m)) {
                $data['network_ip'] = trim($m[1]);
            }
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Get registration status for a gateway.
     */
    public function gatewayStatus(Tenant $tenant, Gateway $gateway): JsonResponse
    {
        if ($gateway->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Gateway not found.'], 404);
        }

        $command = "sofia status gateway {$gateway->name}";
        $response = $this->esl->api($command);

        if (! $response) {
            return response()->json([
                'data' => ['state' => 'unknown'],
                'meta' => ['source' => 'esl', 'error' => 'FreeSWITCH unreachable'],
            ], 503);
        }

        $state = 'unknown';
        if (preg_match('/State:\s*(\S+)/i', $response, $m)) {
            $state = strtolower(trim($m[1]));
        }

        $status = 'unknown';
        if (preg_match('/Status:\s*(\S+)/i', $response, $m)) {
            $status = strtolower(trim($m[1]));
        }

        return response()->json([
            'data' => [
                'gateway' => $gateway->name,
                'state' => $state,
                'status' => $status,
                'registered' => str_contains($state, 'reged') || $state === 'register',
            ],
            'meta' => [
                'source' => 'esl',
                'live' => true,
            ],
        ]);
    }

    /**
     * Parse a JSON response from FreeSWITCH's `show ... as json` commands.
     */
    protected function parseJsonResponse(string $raw): array
    {
        // ESL responses have headers before the JSON body
        $jsonStart = strpos($raw, '{');
        if ($jsonStart === false) {
            return [];
        }

        $json = substr($raw, $jsonStart);
        $decoded = json_decode($json, true);

        return $decoded['rows'] ?? $decoded['registrations'] ?? [];
    }
}
