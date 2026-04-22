<?php

namespace App\Services;

use App\Models\Extension;
use App\Models\SipProfile;

class WebRtcConfigService
{
    public function forExtension(Extension $extension, string $appUrl): array
    {
        $webrtcConfig = config('telephony.webrtc');
        $internalProfile = SipProfile::query()
            ->with(['settings' => function ($query) {
                $query->where('is_enabled', true);
            }])
            ->where('name', 'internal')
            ->first();

        $enabledSettings = $internalProfile?->settings
            ? $internalProfile->settings->pluck('value', 'name')->all()
            : [];

        $wssBinding = (string) ($enabledSettings['wss-binding'] ?? '');
        $wsBinding = (string) ($enabledSettings['ws-binding'] ?? '');
        $sipPort = (string) ($enabledSettings['sip-port'] ?? '5060');

        // Override ports if explicitly defined in telephony config (e.g., Docker port mapping)
        $externalSipPort = config('telephony.freeswitch.sip_port');
        if ($externalSipPort && $externalSipPort !== 5060) {
            $sipPort = (string) $externalSipPort;
        }

        $tlsEnabled = ($enabledSettings['tls'] ?? null) === 'true';
        $tlsSipPort = (string) ($enabledSettings['tls-sip-port'] ?? '');
        $dtlsSrtpEnabled = ($enabledSettings['dtls-srtp'] ?? null) === 'true';

        // WebRTC is enabled if WSS is active (requires TLS/certs) OR if plain WS is active.
        // If only plain WS is enabled, we don't force a WSS/TLS requirement.
        $webrtcEnabled = ($wssBinding !== '' && $dtlsSrtpEnabled)
            || $wsBinding !== '';

        $wssPort = $this->extractPort($wssBinding) ?? ($webrtcConfig['wss_port'] ?? 7443);
        $wsPort = $this->extractPort($wsBinding) ?? 5066;

        // Override WSS port if explicitly defined in telephony config (e.g., Docker port mapping)
        $externalWssPort = config('telephony.freeswitch.wss_port');
        if ($externalWssPort && $externalWssPort !== 7443) {
            $wssPort = $externalWssPort;
        }

        $host = parse_url($appUrl, PHP_URL_HOST) ?: 'localhost';

        // Protocol determination: prefer WSS if available, fallback to WS
        $wsUrl = null;
        $websocketTransport = null;
        if ($webrtcEnabled) {
            $websocketTransport = $wssBinding !== '' ? 'WSS' : 'WS';
            $wsUrl = ($wssBinding !== '')
                ? sprintf('wss://%s:%s', $host, $wssPort)
                : sprintf('ws://%s:%s', $host, $wsPort);
        }

        // Derive available SIP transports from profile settings.
        $legacyTransports = ['UDP', 'TCP'];
        $softphoneTransports = [];

        if ($websocketTransport !== null) {
            $softphoneTransports[] = $websocketTransport;
        }

        if ($tlsEnabled && $tlsSipPort !== '') {
            $legacyTransports[] = 'TLS';
            $softphoneTransports[] = 'TLS';
        }

        $softphoneTransports[] = 'TCP';
        $softphoneTransports[] = 'UDP';
        $softphoneTransports = array_values(array_unique($softphoneTransports));

        $iceServers = [];

        if (! empty($webrtcConfig['stun_server'])) {
            $iceServers[] = ['urls' => $webrtcConfig['stun_server']];
        }

        if (! empty($webrtcConfig['turn_server'])) {
            $turnEntry = ['urls' => $webrtcConfig['turn_server']];

            if (! empty($webrtcConfig['turn_username'])) {
                $turnEntry['username'] = $webrtcConfig['turn_username'];
            }

            if (! empty($webrtcConfig['turn_password'])) {
                $turnEntry['credential'] = $webrtcConfig['turn_password'];
            }

            $iceServers[] = $turnEntry;
        }

        $codecPrefs = array_values(array_filter(array_map(
            static fn (string $codec): string => trim($codec),
            explode(',', $enabledSettings['inbound-codec-prefs'] ?? ($webrtcConfig['codec_prefs'] ?? 'OPUS,PCMU,PCMA,G722')),
        )));

        $mobilePush = [
            'driver' => (string) config('telephony.push_driver', 'log'),
            'enabled' => $this->mobilePushConfigured(),
            'providers' => [
                'apns' => $this->apnsConfigured(),
                'fcm' => $this->fcmConfigured(),
            ],
        ];

        return [
            'enabled' => $webrtcEnabled,
            'websocket_url' => $wsUrl,
            'sip_server' => sprintf('%s:%s', $host, $sipPort),
            'sip_transport' => implode(' / ', $legacyTransports),
            'sip_tls_server' => ($tlsEnabled && $tlsSipPort !== '')
                ? sprintf('%s:%s', $host, $tlsSipPort)
                : null,
            'sip_uri' => sprintf('sip:%s@%s', $extension->extension, $extension->organization->domain),
            'sip_username' => $extension->extension,
            'sip_password' => $extension->password,
            'sip_domain' => $extension->organization->domain,
            'sip_realm' => $extension->organization->domain,
            'display_name' => trim(($extension->first_name ?? '').' '.($extension->last_name ?? '')),
            'ice_servers' => $iceServers,
            'codec_prefs' => $codecPrefs,
            'source_profile' => 'internal',
            'endpoint_strategy' => [
                'primary' => $webrtcEnabled ? 'webrtc' : 'softphone',
                'secondary' => $webrtcEnabled ? 'softphone' : 'hardware',
                'hardware_provisioning' => 'optional',
                'recommended_clients' => $webrtcEnabled
                    ? ['webrtc', 'softphone', 'mobile_push']
                    : ['softphone', 'hardware'],
            ],
            'client_profiles' => [
                'webrtc' => [
                    'enabled' => $webrtcEnabled,
                    'websocket_url' => $wsUrl,
                    'transport' => $websocketTransport,
                    'ice_servers' => $iceServers,
                    'dtls_srtp' => $dtlsSrtpEnabled,
                ],
                'softphone' => [
                    'enabled' => true,
                    'recommended' => true,
                    'preferred_transport' => $softphoneTransports[0] ?? 'TCP',
                    'transports' => $softphoneTransports,
                    'sip_server' => sprintf('%s:%s', $host, $sipPort),
                    'sip_tls_server' => ($tlsEnabled && $tlsSipPort !== '')
                        ? sprintf('%s:%s', $host, $tlsSipPort)
                        : null,
                ],
                'hardware' => [
                    'enabled' => true,
                    'recommended' => false,
                    'provisioning' => 'optional',
                    'sip_server' => sprintf('%s:%s', $host, $sipPort),
                ],
                'mobile_push' => $mobilePush,
            ],
            'mobile_push' => $mobilePush,
            'transport' => [
                'ws_binding' => $wsBinding !== '' ? $wsBinding : null,
                'wss_binding' => $wssBinding !== '' ? $wssBinding : null,
                'tls_cert_dir' => $enabledSettings['tls-cert-dir'] ?? null,
                'dtls_srtp' => $dtlsSrtpEnabled,
                'enable_ice' => ($enabledSettings['enable-ice'] ?? null) === 'true',
                'tls_version' => $enabledSettings['tls-version'] ?? null,
            ],
        ];
    }

    protected function extractPort(string $binding): ?int
    {
        if ($binding === '') {
            return null;
        }

        $port = ltrim($binding, ':');

        return ctype_digit($port) ? (int) $port : null;
    }

    protected function mobilePushConfigured(): bool
    {
        return $this->apnsConfigured() || $this->fcmConfigured();
    }

    protected function apnsConfigured(): bool
    {
        return filled(config('telephony.push.apns.key_id'))
            && filled(config('telephony.push.apns.team_id'))
            && filled(config('telephony.push.apns.bundle_id'))
            && (filled(config('telephony.push.apns.private_key')) || filled(config('telephony.push.apns.private_key_path')));
    }

    protected function fcmConfigured(): bool
    {
        return filled(config('telephony.push.fcm.project_id'))
            && (filled(config('telephony.push.fcm.service_account_json')) || filled(config('telephony.push.fcm.service_account_path')));
    }
}
