<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gateway;
use App\Services\EslConnectionManager;
use App\Services\SipRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Platform admin SIP status monitoring for all profiles, gateways, and registrations.
 */
class SipStatusController extends Controller
{
    public function __construct(
        protected EslConnectionManager $esl,
        protected SipRegistrationService $registrationService
    ) {}

    /**
     * Get all SIP profiles with their status.
     */
    public function profiles(): JsonResponse
    {
        Gate::authorize('platform-admin');

        $response = $this->esl->api('sofia status');

        if (! $response) {
            return response()->json([
                'data' => [],
                'meta' => ['source' => 'esl', 'error' => 'FreeSWITCH unreachable'],
            ], 503);
        }

        $profiles = $this->parseProfiles($response);

        return response()->json([
            'data' => $profiles,
            'meta' => ['source' => 'esl', 'live' => true],
        ]);
    }

    /**
     * Get detailed status for a specific profile.
     */
    public function profileDetail(Request $request): JsonResponse
    {
        Gate::authorize('platform-admin');

        $profileName = $request->input('profile');
        if (! $profileName) {
            return response()->json(['error' => 'Profile name required'], 400);
        }

        $response = $this->esl->api("sofia status profile {$profileName}");

        if (! $response) {
            return response()->json([
                'data' => null,
                'meta' => ['source' => 'esl', 'error' => 'FreeSWITCH unreachable'],
            ], 503);
        }

        return response()->json([
            'data' => ['raw' => $response],
            'meta' => ['source' => 'esl', 'live' => true],
        ]);
    }

    /**
     * Get all gateways across all profiles.
     */
    public function gateways(): JsonResponse
    {
        Gate::authorize('platform-admin');

        $response = $this->esl->api('sofia status gateway');

        if (! $response) {
            return response()->json([
                'data' => [],
                'meta' => ['source' => 'esl', 'error' => 'FreeSWITCH unreachable'],
            ], 503);
        }

        $gateways = $this->parseGateways($response);

        return response()->json([
            'data' => $gateways,
            'meta' => ['source' => 'esl', 'live' => true],
        ]);
    }

    /**
     * Get all registrations across all active SIP profiles.
     */
    public function registrations(): JsonResponse
    {
        Gate::authorize('platform-admin');

        $registrations = $this->registrationService->getAllRegistrations();
        $profilesScanned = \App\Models\SipProfile::where('is_active', true)->pluck('name');

        return response()->json([
            'data' => $registrations,
            'meta' => [
                'source' => 'esl',
                'live' => true,
                'profiles_scanned' => $profilesScanned,
            ],
        ]);
    }

    /**
     * Reload a SIP profile.
     */
    public function reloadProfile(Request $request): JsonResponse
    {
        Gate::authorize('platform-admin');

        $profileName = $request->input('profile');
        if (! $profileName) {
            return response()->json(['error' => 'Profile name required'], 400);
        }

        $response = $this->esl->api("sofia profile {$profileName} rescan");

        return response()->json([
            'success' => true,
            'message' => "Profile {$profileName} reloaded",
            'response' => $response,
        ]);
    }

    /**
     * Start a SIP profile.
     */
    public function startProfile(Request $request): JsonResponse
    {
        Gate::authorize('platform-admin');

        $profileName = $request->input('profile');
        if (! $profileName) {
            return response()->json(['error' => 'Profile name required'], 400);
        }

        $response = $this->esl->api("sofia profile {$profileName} start");

        return response()->json([
            'success' => true,
            'message' => "Profile {$profileName} started",
            'response' => $response,
        ]);
    }

    /**
     * Stop a SIP profile.
     */
    public function stopProfile(Request $request): JsonResponse
    {
        Gate::authorize('platform-admin');

        $profileName = $request->input('profile');
        if (! $profileName) {
            return response()->json(['error' => 'Profile name required'], 400);
        }

        $response = $this->esl->api("sofia profile {$profileName} stop");

        return response()->json([
            'success' => true,
            'message' => "Profile {$profileName} stopped",
            'response' => $response,
        ]);
    }

    /**
     * Kill a specific registration.
     */
    public function killRegistration(Request $request): JsonResponse
    {
        Gate::authorize('platform-admin');

        $user = $request->input('user');
        $realm = $request->input('realm');

        if (! $user || ! $realm) {
            return response()->json(['error' => 'User and realm required'], 400);
        }

        $response = $this->esl->api("sofia profile internal flush_inbound_reg {$user}@{$realm}");

        return response()->json([
            'success' => true,
            'message' => "Registration {$user}@{$realm} killed",
            'response' => $response,
        ]);
    }

    /**
     * Kill a gateway registration.
     */
    public function killGateway(Request $request): JsonResponse
    {
        Gate::authorize('platform-admin');

        $gateway = $request->input('gateway');
        if (! $gateway) {
            return response()->json(['error' => 'Gateway name required'], 400);
        }

        $response = $this->esl->api("sofia profile external killgw {$gateway}");

        return response()->json([
            'success' => true,
            'message' => "Gateway {$gateway} killed",
            'response' => $response,
        ]);
    }

    /**
     * Parse sofia status output into profile array.
     */
    protected function parseProfiles(string $raw): array
    {
        $profiles = [];
        $lines = explode("\n", $raw);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_contains($line, '===') || str_contains($line, 'Name') && str_contains($line, 'Type')) {
                continue;
            }

            // Match profile lines like:
            // internal profile sip:mod_sofia@172.20.0.8:5060 RUNNING (0)
            if (preg_match('/^(\S+)\s+(profile|gateway|alias)\s+(\S+)\s+(.+?)(?:\s+\((\d+)\))?$/', $line, $matches)) {
                $profiles[] = [
                    'name' => $matches[1],
                    'type' => $matches[2],
                    'uri' => $matches[3] ?? null,
                    'status' => trim($matches[4] ?? 'unknown'),
                    'calls' => isset($matches[5]) ? (int) $matches[5] : 0,
                ];
            }
        }

        return $profiles;
    }

    /**
     * Parse sofia status gateway output.
     */
    protected function parseGateways(string $raw): array
    {
        $gateways = [];
        $lines = explode("\n", $raw);
        $gatewayNames = Gateway::query()
            ->select(['id', 'name'])
            ->get()
            ->mapWithKeys(fn (Gateway $gateway) => ['v_'.$gateway->id => $gateway->name]);

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line) || str_contains($line, '===') || str_contains($line, 'Profile::Gateway-Name') || str_contains($line, 'gateways:')) {
                continue;
            }

            // Match gateway line: Profile::GatewayName Data State Ping
            // Example: internal::my_gw  sip:user@host  REGED
            if (preg_match('/^([^:]+)::(\S+)\s+(\S+)\s+(\S+)/', $line, $matches)) {
                $freeswitchName = $matches[2];

                $gateways[] = [
                    'profile' => $matches[1],
                    'name' => $gatewayNames[$freeswitchName] ?? $freeswitchName,
                    'freeswitch_name' => $freeswitchName,
                    'uri' => $matches[3],
                    'status' => $matches[4],
                ];
            }
        }

        return $gateways;
    }

    /**
     * Parse JSON response from FreeSWITCH.
     */
    protected function parseJsonResponse(string $raw): array
    {
        $jsonStart = strpos($raw, '{');
        if ($jsonStart === false) {
            return [];
        }

        $json = substr($raw, $jsonStart);
        $decoded = json_decode($json, true);

        return $decoded['rows'] ?? $decoded['registrations'] ?? [];
    }
}

