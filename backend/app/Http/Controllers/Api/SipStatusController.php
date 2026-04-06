<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EslConnectionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Platform admin SIP status monitoring for all profiles, gateways, and registrations.
 */
class SipStatusController extends Controller
{
    public function __construct(
        protected EslConnectionManager $esl
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
     * Get all registrations across all profiles.
     */
    public function registrations(): JsonResponse
    {
        Gate::authorize('platform-admin');

        $response = $this->esl->api('show registrations as json');

        if (! $response) {
            return response()->json([
                'data' => [],
                'meta' => ['source' => 'esl', 'error' => 'FreeSWITCH unreachable'],
            ], 503);
        }

        $registrations = $this->parseJsonResponse($response);

        return response()->json([
            'data' => $registrations,
            'meta' => ['source' => 'esl', 'live' => true],
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
            
            // Match profile lines like: "internal                 profile  sip:mod_sofia@192.168.1.100:5060  RUNNING (0)"
            if (preg_match('/^(\S+)\s+(profile|gateway|alias)\s+(\S+)\s+(\S+)(?:\s+\((\d+)\))?/', $line, $matches)) {
                $profiles[] = [
                    'name' => $matches[1],
                    'type' => $matches[2],
                    'uri' => $matches[3] ?? null,
                    'status' => $matches[4] ?? 'unknown',
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

        foreach ($lines as $line) {
            $line = trim($line);
            
            // Match gateway lines
            if (preg_match('/^(\S+)\s+(\S+)\s+(\S+)/', $line, $matches)) {
                if ($matches[1] === 'Gateway' || $matches[1] === 'Name') {
                    continue; // Skip header
                }

                $gateways[] = [
                    'name' => $matches[1],
                    'profile' => $matches[2] ?? null,
                    'status' => $matches[3] ?? 'unknown',
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

