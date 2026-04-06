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
                    'debug' => '0',
                    'sip-trace' => 'no',
                    'sip-capture' => 'no',
                    'rfc2833-pt' => '101',
                    'sip-port' => '5060',
                    'dialplan' => 'XML',
                    'context' => 'public',
                    'dtmf-duration' => '2000',
                    'inbound-codec-prefs' => 'PCMU,PCMA',
                    'outbound-codec-prefs' => 'PCMU,PCMA',
                    'rtp-timer-name' => 'soft',
                    'local-network-acl' => 'localnet.auto',
                    'manage-presence' => 'true',
                    'inbound-codec-negotiation' => 'generous',
                    'nonce-ttl' => '60',
                    'auth-calls' => 'true',
                    'inbound-late-negotiation' => 'true',
                    'rtp-ip' => '$${local_ip_v4}',
                    'sip-ip' => '$${local_ip_v4}',
                    'ext-rtp-ip' => 'auto-nat',
                    'ext-sip-ip' => 'auto-nat',
                    'rtp-timeout-sec' => '300',
                    'rtp-hold-timeout-sec' => '1800',
                    'tls' => 'false',
                    'tls-only' => 'false',
                ],
            ],
            'external' => [
                'hostname' => null,
                'description' => 'The External profile is used for external gateways/providers',
                'settings' => [
                    'debug' => '0',
                    'sip-trace' => 'no',
                    'sip-capture' => 'no',
                    'rfc2833-pt' => '101',
                    'sip-port' => '5080',
                    'dialplan' => 'XML',
                    'context' => 'public',
                    'dtmf-duration' => '2000',
                    'inbound-codec-prefs' => 'PCMU,PCMA',
                    'outbound-codec-prefs' => 'PCMU,PCMA',
                    'rtp-timer-name' => 'soft',
                    'local-network-acl' => 'localnet.auto',
                    'manage-presence' => 'false',
                    'inbound-codec-negotiation' => 'generous',
                    'nonce-ttl' => '60',
                    'auth-calls' => 'false',
                    'inbound-late-negotiation' => 'true',
                    'rtp-ip' => '$${local_ip_v4}',
                    'sip-ip' => '$${local_ip_v4}',
                    'ext-rtp-ip' => 'auto-nat',
                    'ext-sip-ip' => 'auto-nat',
                    'rtp-timeout-sec' => '300',
                    'rtp-hold-timeout-sec' => '1800',
                    'tls' => 'false',
                    'tls-only' => 'false',
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
                foreach ($data['settings'] as $key => $value) {
                    $profile->settings()->create([
                        'name' => $key,
                        'value' => $value,
                        'is_enabled' => true,
                    ]);
                }
            }
        }
    }
}
