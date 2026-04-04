<?php

namespace App\Services;

use App\Models\Extension;
use App\Models\SslSetting;
use Illuminate\Support\Facades\Cache;

class WebRtcConfigService
{
    public function forExtension(Extension $extension, string $appUrl): array
    {
        $webrtcConfig = config('telephony.webrtc');
        $host = parse_url($appUrl, PHP_URL_HOST) ?: 'localhost';
        $sslDomains = Cache::remember('webrtc:active-ssl-domains', 60, function (): array {
            return SslSetting::query()
                ->where('is_enabled', true)
                ->where('status', 'active')
                ->value('domains') ?? [];
        });

        if ($sslDomains !== []) {
            $host = $sslDomains[0];
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
            'enabled' => (bool) ($webrtcConfig['enabled'] ?? false),
            'websocket_url' => sprintf('wss://%s:%s', $host, $webrtcConfig['wss_port'] ?? 7443),
            'sip_uri' => sprintf('sip:%s@%s', $extension->extension, $extension->tenant->domain),
            'sip_username' => $extension->extension,
            'sip_password' => $extension->password,
            'sip_domain' => $extension->tenant->domain,
            'display_name' => trim(($extension->directory_first_name ?? '').' '.($extension->directory_last_name ?? '')),
            'ice_servers' => $iceServers,
            'codec_prefs' => explode(',', $webrtcConfig['codec_prefs'] ?? 'OPUS,PCMU,PCMA,G722'),
        ];
    }
}
