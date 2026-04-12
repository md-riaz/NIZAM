<?php

namespace Database\Seeders;

use App\Models\SipProfile;
use Illuminate\Database\Seeder;

class SipProfileSeeder extends Seeder
{
    public function run(): void
    {
        $profiles = [
            'internal' => [
                'hostname' => null,
                'description' => 'The Internal profile by default requires registration which is used by the endpoints',
                'settings' => [
                    ['name' => 'debug', 'value' => '0', 'is_enabled' => true],
                    ['name' => 'sip-trace', 'value' => 'no', 'is_enabled' => true],
                    ['name' => 'sip-capture', 'value' => 'no', 'is_enabled' => true],
                    ['name' => 'rfc2833-pt', 'value' => '101', 'is_enabled' => true],
                    ['name' => 'sip-port', 'value' => '5060', 'is_enabled' => true],
                    ['name' => 'dialplan', 'value' => 'XML', 'is_enabled' => true],
                    ['name' => 'context', 'value' => '${domain_name}', 'is_enabled' => true],
                    ['name' => 'dtmf-duration', 'value' => '2000', 'is_enabled' => true],
                    ['name' => 'inbound-codec-prefs', 'value' => 'PCMU,PCMA', 'is_enabled' => true],
                    ['name' => 'outbound-codec-prefs', 'value' => 'PCMU,PCMA', 'is_enabled' => true],
                    ['name' => 'rtp-timer-name', 'value' => 'soft', 'is_enabled' => true],
                    ['name' => 'local-network-acl', 'value' => 'localnet.auto', 'is_enabled' => true],
                    ['name' => 'aggressive-nat-detection', 'value' => 'true', 'is_enabled' => true],
                    ['name' => 'multiple-registrations', 'value' => 'contact', 'is_enabled' => true],
                    ['name' => 'max-registrations-per-extension', 'value' => '5', 'is_enabled' => true],
                    ['name' => 'manage-presence', 'value' => 'true', 'is_enabled' => true],
                    ['name' => 'inbound-codec-negotiation', 'value' => 'generous', 'is_enabled' => true],
                    ['name' => 'nonce-ttl', 'value' => '60', 'is_enabled' => true],
                    ['name' => 'auth-calls', 'value' => 'true', 'is_enabled' => true],
                    ['name' => 'inbound-late-negotiation', 'value' => 'true', 'is_enabled' => true],
                    ['name' => 'rtp-ip', 'value' => '$${local_ip_v4}', 'is_enabled' => true],
                    ['name' => 'sip-ip', 'value' => '$${local_ip_v4}', 'is_enabled' => true],
                    ['name' => 'ext-rtp-ip', 'value' => 'auto-nat', 'is_enabled' => true],
                    ['name' => 'ext-sip-ip', 'value' => 'auto-nat', 'is_enabled' => true],
                    ['name' => 'rtp-timeout-sec', 'value' => '300', 'is_enabled' => true],
                    ['name' => 'rtp-hold-timeout-sec', 'value' => '1800', 'is_enabled' => true],
                    ['name' => 'tls', 'value' => 'false', 'is_enabled' => true],
                    ['name' => 'tls-only', 'value' => 'false', 'is_enabled' => true],
                    ['name' => 'ws-binding', 'value' => ':5066', 'is_enabled' => false],
                    ['name' => 'wss-binding', 'value' => ':7443', 'is_enabled' => false],
                    ['name' => 'tls-bind-params', 'value' => 'transport=wss', 'is_enabled' => false],
                    ['name' => 'tls-sip-port', 'value' => '7443', 'is_enabled' => false],
                    ['name' => 'tls-cert-dir', 'value' => config('telephony.webrtc.dtls_cert_dir', '/usr/local/freeswitch/certs'), 'is_enabled' => false],
                    ['name' => 'tls-version', 'value' => 'tlsv1.2', 'is_enabled' => false],
                    ['name' => 'tls-verify-date', 'value' => 'true', 'is_enabled' => false],
                    ['name' => 'tls-verify-policy', 'value' => 'none', 'is_enabled' => false],
                    ['name' => 'tls-verify-depth', 'value' => '2', 'is_enabled' => false],
                    ['name' => 'dtls-srtp', 'value' => 'true', 'is_enabled' => false],
                    ['name' => 'dtls-verify-policy', 'value' => 'fingerprint', 'is_enabled' => false],
                    ['name' => 'enable-ice', 'value' => 'true', 'is_enabled' => false],
                    ['name' => 'ws-only', 'value' => 'true', 'is_enabled' => false],
                ],
            ],
            'external' => [
                'hostname' => null,
                'description' => 'The External profile is used for external gateways/providers',
                'settings' => [
                    ['name' => 'debug', 'value' => '0', 'is_enabled' => true],
                    ['name' => 'sip-trace', 'value' => 'no', 'is_enabled' => true],
                    ['name' => 'sip-capture', 'value' => 'no', 'is_enabled' => true],
                    ['name' => 'rfc2833-pt', 'value' => '101', 'is_enabled' => true],
                    ['name' => 'sip-port', 'value' => '5080', 'is_enabled' => true],
                    ['name' => 'dialplan', 'value' => 'XML', 'is_enabled' => true],
                    ['name' => 'context', 'value' => 'public', 'is_enabled' => true],
                    ['name' => 'dtmf-duration', 'value' => '2000', 'is_enabled' => true],
                    ['name' => 'inbound-codec-prefs', 'value' => 'PCMU,PCMA', 'is_enabled' => true],
                    ['name' => 'outbound-codec-prefs', 'value' => 'PCMU,PCMA', 'is_enabled' => true],
                    ['name' => 'rtp-timer-name', 'value' => 'soft', 'is_enabled' => true],
                    ['name' => 'local-network-acl', 'value' => 'localnet.auto', 'is_enabled' => true],
                    ['name' => 'manage-presence', 'value' => 'false', 'is_enabled' => true],
                    ['name' => 'inbound-codec-negotiation', 'value' => 'generous', 'is_enabled' => true],
                    ['name' => 'nonce-ttl', 'value' => '60', 'is_enabled' => true],
                    ['name' => 'auth-calls', 'value' => 'false', 'is_enabled' => true],
                    ['name' => 'inbound-late-negotiation', 'value' => 'true', 'is_enabled' => true],
                    ['name' => 'rtp-ip', 'value' => '$${local_ip_v4}', 'is_enabled' => true],
                    ['name' => 'sip-ip', 'value' => '$${local_ip_v4}', 'is_enabled' => true],
                    ['name' => 'ext-rtp-ip', 'value' => 'auto-nat', 'is_enabled' => true],
                    ['name' => 'ext-sip-ip', 'value' => 'auto-nat', 'is_enabled' => true],
                    ['name' => 'rtp-timeout-sec', 'value' => '300', 'is_enabled' => true],
                    ['name' => 'rtp-hold-timeout-sec', 'value' => '1800', 'is_enabled' => true],
                    ['name' => 'tls', 'value' => 'false', 'is_enabled' => true],
                    ['name' => 'tls-only', 'value' => 'false', 'is_enabled' => true],
                ],
            ],
        ];

        foreach ($profiles as $name => $data) {
            $profile = SipProfile::firstOrCreate(
                ['name' => $name],
                [
                    'hostname' => $data['hostname'],
                    'description' => $data['description'],
                    'is_active' => true,
                ]
            );

            // Create settings if they don't exist
            if ($profile->wasRecentlyCreated) {
                foreach ($data['settings'] as $setting) {
                    $profile->settings()->create([
                        'name' => $setting['name'],
                        'value' => $setting['value'],
                        'is_enabled' => $setting['is_enabled'],
                    ]);
                }
            }
        }
    }
}
