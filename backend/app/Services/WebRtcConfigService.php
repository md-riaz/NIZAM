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
        $webrtcEnabled = ($wssBinding !== '' && (($enabledSettings['dtls-srtp'] ?? null) === 'true'))
            || $wsBinding !== '';

        $wssPort = $this->extractPort($wssBinding) ?? ($webrtcConfig['wss_port'] ?? 7443);

        // Override WSS port if explicitly defined in telephony config (e.g., Docker port mapping)
        $externalWssPort = config('telephony.freeswitch.wss_port');
        if ($externalWssPort && $externalWssPort !== 7443) {
            $wssPort = $externalWssPort;
        }

        $host = parse_url($appUrl, PHP_URL_HOST) ?: 'localhost';

        // Derive available SIP transports from profile settings
        $transports = ['UDP', 'TCP'];
        if ($tlsEnabled && $tlsSipPort !== '') {
            $transports[] = 'TLS';
        }

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

        return [
            'enabled' => $webrtcEnabled,
            'websocket_url' => $webrtcEnabled ? sprintf('wss://%s:%s', $host, $wssPort) : null,
            'sip_server' => sprintf('%s:%s', $host, $sipPort),
            'sip_transport' => implode(' / ', $transports),
            'sip_tls_server' => ($tlsEnabled && $tlsSipPort !== '')
                ? sprintf('%s:%s', $host, $tlsSipPort)
                : null,
            'sip_uri' => sprintf('sip:%s@%s', $extension->extension, $extension->tenant->domain),
            'sip_username' => $extension->extension,
            'sip_password' => $extension->password,
            'sip_domain' => $extension->tenant->domain,
            'sip_realm' => $extension->tenant->domain,
            'display_name' => trim(($extension->directory_first_name ?? '').' '.($extension->directory_last_name ?? '')),
            'ice_servers' => $iceServers,
            'codec_prefs' => explode(',', $enabledSettings['inbound-codec-prefs'] ?? ($webrtcConfig['codec_prefs'] ?? 'OPUS,PCMU,PCMA,G722')),
            'source_profile' => 'internal',
            'transport' => [
                'ws_binding' => $wsBinding !== '' ? $wsBinding : null,
                'wss_binding' => $wssBinding !== '' ? $wssBinding : null,
                'tls_cert_dir' => $enabledSettings['tls-cert-dir'] ?? null,
                'dtls_srtp' => ($enabledSettings['dtls-srtp'] ?? null) === 'true',
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
}
