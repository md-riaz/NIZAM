<?php

namespace App\Services;

class SipProfileCompiler
{
    /**
     * Compile SIP profile XML for FreeSWITCH configuration section.
     *
     * @param string $profileName The name of the SIP profile (e.g., 'external', 'internal')
     * @return string The compiled XML
     */
    public function compileProfile(string $profileName = 'external'): string
    {
        $media = config('telephony.media', []);
        
        // Load persistent profile settings from database if available
        $model = \App\Models\SipProfile::where('name', $profileName)
            ->where('is_active', true)
            ->first();

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>'."\n";
        $xml .= '<document type="freeswitch/xml">'."\n";
        $xml .= '  <section name="configuration">'."\n";
        $xml .= '    <configuration name="sofia.conf" description="Sofia SIP">'."\n";
        $xml .= '      <profiles>'."\n";
        $xml .= '        <profile name="'.htmlspecialchars($profileName, ENT_QUOTES | ENT_XML1).'">'."\n";
        $xml .= '          <settings>'."\n";

        // Collect parameters
        $params = [
            'debug' => '0',
            'sip-trace' => 'no',
            'sip-capture' => 'no',
            'rfc2833-pt' => '101',
            'sip-port' => $profileName === 'internal' ? '5060' : '5080',
            'dialplan' => 'XML',
            'context' => 'public',
            'dtmf-duration' => '2000',
            'inbound-codec-prefs' => 'PCMU,PCMA',
            'outbound-codec-prefs' => 'PCMU,PCMA',
            'rtp-timer-name' => 'soft',
            'local-network-acl' => $media['local_network_acl'] ?? 'localnet.auto',
            'manage-presence' => 'false',
            'inbound-codec-negotiation' => 'generous',
            'nonce-ttl' => '60',
            'auth-calls' => 'false',
            'inbound-late-negotiation' => 'true',
            'rtp-ip' => $media['rtp_ip'] ?? 'auto',
            'sip-ip' => $media['sip_ip'] ?? 'auto',
            'ext-rtp-ip' => $media['ext_rtp_ip'] ?? 'auto-nat',
            'ext-sip-ip' => $media['ext_sip_ip'] ?? 'auto-nat',
            'rtp-timeout-sec' => '300',
            'rtp-hold-timeout-sec' => '1800',
            'tls' => 'false',
            'tls-only' => 'false',
        ];

        if ($media['aggressive_nat_detection'] ?? false) {
            $params['aggressive-nat-detection'] = 'true';
        }

        // Override with model settings if present
        if ($model && !empty($model->settings)) {
            $params = array_merge($params, $model->settings);
        }

        foreach ($params as $name => $value) {
            $xml .= '            <param name="'.htmlspecialchars($name, ENT_QUOTES | ENT_XML1).'" value="'.htmlspecialchars((string)$value, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        }

        $xml .= '          </settings>'."\n";
        $xml .= '        </profile>'."\n";
        $xml .= '      </profiles>'."\n";
        $xml .= '    </configuration>'."\n";
        $xml .= '  </section>'."\n";
        $xml .= '</document>';

        return $xml;
    }

    /**
     * Generate an empty configuration response.
     */
    public function emptyConfigurationResponse(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="no"?>'."\n"
             .'<document type="freeswitch/xml">'."\n"
             .'  <section name="configuration"></section>'."\n"
             .'</document>';
    }
}
