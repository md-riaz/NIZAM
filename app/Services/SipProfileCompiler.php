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
        $media = config('nizam.media', []);

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>'."\n";
        $xml .= '<document type="freeswitch/xml">'."\n";
        $xml .= '  <section name="configuration">'."\n";
        $xml .= '    <configuration name="sofia.conf" description="Sofia SIP">'."\n";
        $xml .= '      <profiles>'."\n";
        $xml .= '        <profile name="'.htmlspecialchars($profileName, ENT_QUOTES | ENT_XML1).'">'."\n";
        $xml .= '          <settings>'."\n";

        // Basic SIP settings
        $xml .= '            <param name="debug" value="0"/>'."\n";
        $xml .= '            <param name="sip-trace" value="no"/>'."\n";
        $xml .= '            <param name="sip-capture" value="no"/>'."\n";
        $xml .= '            <param name="rfc2833-pt" value="101"/>'."\n";
        $xml .= '            <param name="sip-port" value="5080"/>'."\n";
        $xml .= '            <param name="dialplan" value="XML"/>'."\n";
        $xml .= '            <param name="context" value="public"/>'."\n";
        $xml .= '            <param name="dtmf-duration" value="2000"/>'."\n";
        $xml .= '            <param name="inbound-codec-prefs" value="PCMU,PCMA"/>'."\n";
        $xml .= '            <param name="outbound-codec-prefs" value="PCMU,PCMA"/>'."\n";
        $xml .= '            <param name="rtp-timer-name" value="soft"/>'."\n";

        // NAT settings from config
        $localNetworkAcl = $media['local_network_acl'] ?? 'localnet.auto';
        $xml .= '            <param name="local-network-acl" value="'.htmlspecialchars($localNetworkAcl, ENT_QUOTES | ENT_XML1).'"/>'."\n";

        $aggressiveNat = $media['aggressive_nat_detection'] ?? false;
        if ($aggressiveNat) {
            $xml .= '            <param name="aggressive-nat-detection" value="true"/>'."\n";
        }

        $xml .= '            <param name="manage-presence" value="false"/>'."\n";
        $xml .= '            <param name="inbound-codec-negotiation" value="generous"/>'."\n";
        $xml .= '            <param name="nonce-ttl" value="60"/>'."\n";
        $xml .= '            <param name="auth-calls" value="false"/>'."\n";
        $xml .= '            <param name="inbound-late-negotiation" value="true"/>'."\n";

        // IP address settings
        $rtpIp = $media['rtp_ip'] ?? 'auto';
        $sipIp = $media['sip_ip'] ?? 'auto';
        $extRtpIp = $media['ext_rtp_ip'] ?? 'auto-nat';
        $extSipIp = $media['ext_sip_ip'] ?? 'auto-nat';

        $xml .= '            <param name="rtp-ip" value="'.htmlspecialchars($rtpIp, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        $xml .= '            <param name="sip-ip" value="'.htmlspecialchars($sipIp, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        $xml .= '            <param name="ext-rtp-ip" value="'.htmlspecialchars($extRtpIp, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        $xml .= '            <param name="ext-sip-ip" value="'.htmlspecialchars($extSipIp, ENT_QUOTES | ENT_XML1).'"/>'."\n";

        $xml .= '            <param name="rtp-timeout-sec" value="300"/>'."\n";
        $xml .= '            <param name="rtp-hold-timeout-sec" value="1800"/>'."\n";

        // TLS settings (disabled by default)
        $xml .= '            <param name="tls" value="false"/>'."\n";
        $xml .= '            <param name="tls-only" value="false"/>'."\n";

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
