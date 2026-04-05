<?php

namespace App\Services;

use App\Models\WebRtcTlsSetting;

class WebRtcTlsSettingsService
{
    public const MODE_TRUSTED_CA = 'trusted_ca';

    public const MODE_SELF_SIGNED = 'self_signed';

    public function getSettings(): array
    {
        $defaults = $this->defaultSettings();
        $stored = WebRtcTlsSetting::query()->first();

        if (! $stored) {
            return $this->decorate($defaults);
        }

        return $this->decorate(array_merge($defaults, $stored->only([
            'webrtc_enabled',
            'active_mode',
            'trusted_ca_enabled',
            'trusted_ca_cert_dir',
            'self_signed_enabled',
            'self_signed_cert_dir',
        ])));
    }

    public function update(array $attributes): array
    {
        $setting = WebRtcTlsSetting::query()->firstOrNew([]);
        $setting->fill($attributes);
        $setting->save();

        return $this->getSettings();
    }

    public function activeMode(): string
    {
        return $this->getSettings()['active_mode'];
    }

    public function isWebRtcEnabled(): bool
    {
        return (bool) $this->getSettings()['webrtc_enabled'];
    }

    public function effectiveCertDirectory(): string
    {
        $settings = $this->getSettings();

        return $settings['modes'][$settings['active_mode']]['cert_dir'];
    }

    public function profileOverrides(): array
    {
        $settings = $this->getSettings();
        $activeMode = $settings['modes'][$settings['active_mode']];
        $webrtcConfig = config('telephony.webrtc', []);
        $media = config('telephony.media', []);

        return [
            'ws-binding' => ':5066',
            'wss-binding' => sprintf(':%s', $webrtcConfig['wss_port'] ?? 7443),
            'tls' => 'true',
            'tls-only' => 'false',
            'tls-bind-params' => 'transport=wss',
            'tls-sip-port' => (string) ($webrtcConfig['wss_port'] ?? 7443),
            'tls-cert-dir' => $activeMode['cert_dir'],
            'tls-version' => 'tlsv1.2',
            'tls-verify-date' => 'true',
            'tls-verify-policy' => $settings['active_mode'] === self::MODE_TRUSTED_CA ? 'none' : 'none',
            'tls-verify-depth' => '2',
            'sip-port' => '5066',
            'dialplan' => 'XML',
            'context' => 'public',
            'dtmf-duration' => '2000',
            'dtmf-type' => $media['dtmf_type'] ?? 'rfc2833',
            'rfc2833-pt' => '101',
            'inbound-codec-prefs' => $webrtcConfig['codec_prefs'] ?? 'OPUS,PCMU,PCMA,G722',
            'outbound-codec-prefs' => $webrtcConfig['codec_prefs'] ?? 'OPUS,PCMU,PCMA,G722',
            'inbound-codec-negotiation' => 'generous',
            'rtp-timer-name' => 'soft',
            'rtp-ip' => $media['rtp_ip'] ?? 'auto',
            'sip-ip' => $media['sip_ip'] ?? 'auto',
            'ext-rtp-ip' => $media['ext_rtp_ip'] ?? 'auto-nat',
            'ext-sip-ip' => $media['ext_sip_ip'] ?? 'auto-nat',
            'rtp-timeout-sec' => '300',
            'rtp-hold-timeout-sec' => '1800',
            'dtls-srtp' => 'true',
            'dtls-verify-policy' => 'fingerprint',
            'enable-ice' => 'true',
            'auth-calls' => 'true',
            'apply-inbound-acl' => 'domains',
            'inbound-late-negotiation' => 'true',
            'local-network-acl' => $media['local_network_acl'] ?? 'localnet.auto',
            'manage-presence' => 'true',
            'aggressive-nat-detection' => ($media['aggressive_nat_detection'] ?? false) ? 'true' : 'false',
            'NDLB-received-in-nat-reg-contact' => 'true',
            'NDLB-force-rport' => 'true',
            'NDLB-broken-auth-hash' => 'true',
            'enable-timer' => 'false',
            'minimum-session-expires' => '120',
            'session-timeout' => '1800',
            'disable-srv' => 'true',
            'disable-srv503' => 'true',
            'challenge-realm' => 'auto_from',
            'nonce-ttl' => '60',
            'auth-all-packets' => 'false',
            'user-agent-string' => 'NIZAM WebRTC',
        ];
    }

    public function defaultSettings(): array
    {
        return [
            'webrtc_enabled' => (bool) config('telephony.webrtc.enabled', false),
            'active_mode' => self::MODE_TRUSTED_CA,
            'trusted_ca_enabled' => true,
            'trusted_ca_cert_dir' => (string) config('telephony.webrtc.dtls_cert_dir', '/usr/local/freeswitch/certs'),
            'self_signed_enabled' => true,
            'self_signed_cert_dir' => (string) config('telephony.webrtc.dtls_cert_dir', '/usr/local/freeswitch/certs'),
        ];
    }

    protected function decorate(array $settings): array
    {
        if (! in_array($settings['active_mode'], [self::MODE_TRUSTED_CA, self::MODE_SELF_SIGNED], true)) {
            $settings['active_mode'] = self::MODE_TRUSTED_CA;
        }

        if ($settings['active_mode'] === self::MODE_TRUSTED_CA && ! $settings['trusted_ca_enabled'] && $settings['self_signed_enabled']) {
            $settings['active_mode'] = self::MODE_SELF_SIGNED;
        }

        if ($settings['active_mode'] === self::MODE_SELF_SIGNED && ! $settings['self_signed_enabled'] && $settings['trusted_ca_enabled']) {
            $settings['active_mode'] = self::MODE_TRUSTED_CA;
        }

        $trustedDir = $settings['trusted_ca_cert_dir'] ?: (string) config('telephony.webrtc.dtls_cert_dir', '/usr/local/freeswitch/certs');
        $selfSignedDir = $settings['self_signed_cert_dir'] ?: (string) config('telephony.webrtc.dtls_cert_dir', '/usr/local/freeswitch/certs');

        return [
            'webrtc_enabled' => (bool) $settings['webrtc_enabled'],
            'active_mode' => $settings['active_mode'],
            'modes' => [
                self::MODE_TRUSTED_CA => [
                    'key' => self::MODE_TRUSTED_CA,
                    'label' => 'Trusted/public CA certificates',
                    'enabled' => (bool) $settings['trusted_ca_enabled'],
                    'cert_dir' => $trustedDir,
                    'production_ready' => true,
                    'summary' => 'Use browser-trusted certificates for production WSS and WebRTC.',
                    'details' => 'Browsers require a trusted HTTPS and WSS certificate chain for production WebRTC. Place FreeSWITCH certificate files in the configured directory and point DNS at the trusted domain.',
                    'expected_files' => ['wss.pem', 'wss.key', 'agent.pem', 'cafile.pem'],
                ],
                self::MODE_SELF_SIGNED => [
                    'key' => self::MODE_SELF_SIGNED,
                    'label' => 'Self-signed / development certificates',
                    'enabled' => (bool) $settings['self_signed_enabled'],
                    'cert_dir' => $selfSignedDir,
                    'production_ready' => false,
                    'summary' => 'Use self-signed certificates for labs, staging, or local testing.',
                    'details' => 'Self-signed certificates can work for controlled testing, but browsers will warn or block users unless the certificate chain is manually trusted on each client device.',
                    'expected_files' => ['wss.pem', 'wss.key', 'agent.pem', 'cafile.pem'],
                ],
            ],
        ];
    }
}
