<?php

namespace App\Services;

use App\Models\SipProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Service for querying and parsing SIP registrations from FreeSWITCH.
 * Adopts parsing heuristics from FusionPBX for parity.
 */
class SipRegistrationService
{
    public function __construct(
        protected EslConnectionManager $esl
    ) {}

    /**
     * Get all registrations across all active SIP profiles.
     */
    public function getAllRegistrations(): array
    {
        $activeProfiles = SipProfile::where('is_active', true)->get();
        $allRegistrations = [];

        foreach ($activeProfiles as $profile) {
            $registrations = $this->getRegistrationsForProfile($profile->name);
            $allRegistrations = array_merge($allRegistrations, $registrations);
        }

        return $allRegistrations;
    }

    /**
     * Fetch registrations for a specific profile using XML status.
     */
    public function getRegistrationsForProfile(string $profileName): array
    {
        $response = $this->esl->api("sofia xmlstatus profile {$profileName} reg");

        if (!$response || str_contains($response, 'Invalid Profile!')) {
            return [];
        }

        return $this->parseXmlRegistrations($response, $profileName);
    }

    /**
     * Parse FreeSWITCH XML registrations into a normalized array.
     * Follows FusionPBX parsing logic from app/registrations/resources/classes/registrations.php
     */
    public function parseXmlRegistrations(string $xmlRaw, ?string $profileName = null): array
    {
        // Extract the XML part after ESL headers
        $xmlStart = strpos($xmlRaw, '<profile');
        if ($xmlStart === false) {
            return [];
        }

        $xmlString = substr($xmlRaw, $xmlStart);

        try {
            $xml = new \SimpleXMLElement($xmlString);
        } catch (\Exception $e) {
            Log::warning('Failed to parse SIP registrations XML', ['error' => $e->getMessage()]);
            return [];
        }

        $registrations = [];

        if (!isset($xml->registrations->registration)) {
            return [];
        }

        foreach ($xml->registrations->registration as $reg) {
            $user = (string) $reg->user;
            $userParts = explode('@', $user);
            $regUser = $userParts[0] ?? $user;
            $agent = (string) $reg->agent;
            $callId = (string) $reg->{'call-id'};
            $contact = (string) $reg->contact;
            $statusRaw = (string) $reg->status;

            // 1. Derive reg_user and realm
            $realm = (string) $reg->{'sip-auth-realm'};

            // 2. Derive expires (expsecs) from status string
            $expires = 0;
            if (preg_match('/expsecs\((\d+)\)/', $statusRaw, $m)) {
                $expires = (int) $m[1];
            }

            // 3. Derive LAN IP (FusionPBX heuristic)
            $lanIp = '';
            $callIdParts = explode('@', $callId);
            if (isset($callIdParts[1])) {
                $lanIp = $callIdParts[1];
                // Grandstream/Ooma translation
                if (!empty($agent) && (stripos($agent, 'grandstream') !== false || stripos($agent, 'ooma') !== false)) {
                    $lanIp = str_ireplace(
                        ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'],
                        ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                        $lanIp
                    );
                }
                // GIGASET Sculpture CL750A fix
                if (!empty($agent) && preg_match('/\ACL750A/', $agent)) {
                    $lanIp = str_replace('_', '.', $lanIp);
                }
            } elseif (preg_match('/real=\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $contact, $ipMatch)) {
                // Snom/other phones with real= parameter
                $lanIp = str_replace('real=', '', $ipMatch[0]);
            } elseif (preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $contact, $ipMatch)) {
                $lanIp = str_replace('_', '.', $ipMatch[0]);
            }

            // 4. Clean status for display
            $status = preg_replace([
                '/(\d{4})-(\d{2})-(\d{2})/',
                '/(\d{2}):(\d{2}):(\d{2})/',
                '/unknown/',
                '/expsecs\(\d+\)/',
                '/exp\(/',
                '/\(/',
                '/\)/',
                '/\s+/'
            ], ' ', $statusRaw);
            $status = trim($status);

            $registrations[] = [
                'reg_user' => $regUser,
                'user' => $user,
                'call_id' => $callId,
                'agent' => $agent,
                'contact' => $contact,
                'host' => (string) $reg->host,
                'network_ip' => (string) $reg->{'network-ip'},
                'network_port' => (string) $reg->{'network-port'},
                'sip_auth_user' => (string) $reg->{'sip-auth-user'},
                'sip_auth_realm' => $realm,
                'realm' => $realm, // Compatibility with current UI
                'lan_ip' => $lanIp,
                'status' => $status,
                'status_raw' => $statusRaw,
                'ping_time' => (string) $reg->{'ping-time'},
                'sip_profile_name' => $profileName,
                'expires' => $expires,
            ];
        }

        return $registrations;
    }
}
