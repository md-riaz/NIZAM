<?php

namespace App\Services;

use App\Models\DeviceProfile;
use App\Models\Extension;

class ProvisioningService
{
    /**
     * Render a provisioning configuration for a device.
     */
    public function renderConfig(DeviceProfile $profile): string
    {
        $template = $profile->template ?? $this->getDefaultTemplate($profile->vendor);

        $extension = $profile->extension;
        $organization = $profile->organization;
        $softphoneProvisioning = $this->softphoneProvisioningDefaults($organization?->domain);

        $variables = [
            '{{DEVICE_NAME}}' => $profile->name,
            '{{VENDOR}}' => $profile->vendor,
            '{{MAC_ADDRESS}}' => $profile->mac_address ?? '',
            '{{DOMAIN}}' => $organization->domain ?? '',
            '{{ORGANIZATION_NAME}}' => $organization->name ?? '',
            '{{PROVISIONING_MODE}}' => 'optional_hardware',
            '{{ENDPOINT_STRATEGY}}' => 'softphone_first',
            '{{SOFTPHONE_SIP_SERVER}}' => $softphoneProvisioning['sip_server'],
            '{{SOFTPHONE_TRANSPORT}}' => $softphoneProvisioning['preferred_transport'],
            '{{SOFTPHONE_TLS_SERVER}}' => $softphoneProvisioning['sip_tls_server'],
            '{{SOFTPHONE_WEBSOCKET_URL}}' => $softphoneProvisioning['websocket_url'],
            '{{HARDWARE_ENABLED}}' => 'true',
            '{{HARDWARE_RECOMMENDED}}' => 'false',
        ];

        // Add extension variables if assigned
        if ($extension) {
            $variables = array_merge($variables, [
                '{{EXTENSION}}' => $extension->extension,
                '{{PASSWORD}}' => $extension->password,
                '{{DISPLAY_NAME}}' => trim(($extension->first_name ?? '').' '.($extension->last_name ?? '')),
                '{{CALLER_ID_NAME}}' => $extension->effective_caller_id_name ?? $extension->first_name ?? '',
                '{{CALLER_ID_NUMBER}}' => $this->resolveProvisioningCallerIdNumber($extension),
                '{{VOICEMAIL_ENABLED}}' => $extension->voicemail_enabled ? 'true' : 'false',
            ]);
        }

        return str_replace(array_keys($variables), array_values($variables), $template);
    }

    protected function resolveProvisioningCallerIdNumber(Extension $extension): string
    {
        $extension->loadMissing(['defaultOutboundDid', 'allowedOutboundDids']);

        $defaultDid = $extension->defaultOutboundDid;

        if ($defaultDid !== null
            && $defaultDid->organization_id === $extension->organization_id
            && $defaultDid->is_active
            && $extension->hasAllowedOutboundDid($defaultDid->id)) {
            return $defaultDid->normalized_number ?? $defaultDid->number;
        }

        $did = $extension->allowedOutboundDids()
            ->where('dids.organization_id', $extension->organization_id)
            ->where('dids.is_active', true)
            ->orderBy('dids.number')
            ->first();

        return $did?->normalized_number ?? $did?->number ?? '';
    }

    /**
     * Find a device profile by MAC address.
     */
    public function findByMac(string $macAddress): ?DeviceProfile
    {
        // Normalize MAC address (strip separators, lowercase)
        $normalized = strtolower(preg_replace('/[^a-fA-F0-9]/', '', $macAddress));

        return DeviceProfile::where('is_active', true)
            ->get()
            ->first(function (DeviceProfile $profile) use ($normalized) {
                if (! $profile->mac_address) {
                    return false;
                }
                $profileMac = strtolower(preg_replace('/[^a-fA-F0-9]/', '', $profile->mac_address));

                return $profileMac === $normalized;
            });
    }

    /**
     * Describe the endpoint strategy exposed by the provisioning service.
     *
     * @return array<string, mixed>
     */
    public function endpointStrategy(?string $domain = null): array
    {
        $softphone = $this->softphoneProvisioningDefaults($domain);

        return [
            'default_endpoint' => 'softphone',
            'hardware_provisioning' => 'optional',
            'softphone' => [
                'recommended' => true,
                'sip_server' => $softphone['sip_server'],
                'sip_tls_server' => $softphone['sip_tls_server'],
                'preferred_transport' => $softphone['preferred_transport'],
                'websocket_url' => $softphone['websocket_url'],
            ],
            'hardware' => [
                'recommended' => false,
                'auto_provisioning' => true,
            ],
        ];
    }

    /**
     * Get a default template for a vendor.
     */
    protected function getDefaultTemplate(string $vendor): string
    {
        return match (strtolower($vendor)) {
            'polycom' => $this->polycomTemplate(),
            'yealink' => $this->yealinkTemplate(),
            'grandstream' => $this->grandstreamTemplate(),
            default => $this->genericTemplate(),
        };
    }

    /**
     * @return array{sip_server: string, sip_tls_server: string, preferred_transport: string, websocket_url: string}
     */
    protected function softphoneProvisioningDefaults(?string $domain = null): array
    {
        $host = $domain ?: (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost');
        $sipPort = (string) (config('telephony.freeswitch.sip_port') ?: config('telephony.freeswitch.external_sip_port') ?: 5060);
        $tlsPort = (string) (config('telephony.freeswitch.external_sip_port') ?: config('telephony.freeswitch.sip_port') ?: 5061);
        $wssPort = (string) (config('telephony.freeswitch.wss_port') ?: config('telephony.webrtc.wss_port') ?: 7443);

        return [
            'sip_server' => sprintf('%s:%s', $host, $sipPort),
            'sip_tls_server' => sprintf('%s:%s', $host, $tlsPort),
            'preferred_transport' => $wssPort !== '' ? 'WSS' : 'TLS',
            'websocket_url' => sprintf('wss://%s:%s', $host, $wssPort),
        ];
    }

    protected function polycomTemplate(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<polycomConfig>
  <device provisioning.mode="{{PROVISIONING_MODE}}" endpoint.strategy="{{ENDPOINT_STRATEGY}}" />
  <reg reg.1.displayName="{{DISPLAY_NAME}}"
       reg.1.address="{{EXTENSION}}"
       reg.1.label="{{EXTENSION}}"
       reg.1.auth.userId="{{EXTENSION}}"
       reg.1.auth.password="{{PASSWORD}}"
       reg.1.server.1.address="{{DOMAIN}}"
       reg.1.server.1.port="5060" />
</polycomConfig>
XML;
    }

    protected function yealinkTemplate(): string
    {
        return <<<'INI'
#!version:1.0.0.1
# endpoint_strategy = {{ENDPOINT_STRATEGY}}
# provisioning_mode = {{PROVISIONING_MODE}}
account.1.enable = 1
account.1.label = {{EXTENSION}}
account.1.display_name = {{DISPLAY_NAME}}
account.1.auth_name = {{EXTENSION}}
account.1.user_name = {{EXTENSION}}
account.1.password = {{PASSWORD}}
account.1.sip_server.1.address = {{DOMAIN}}
account.1.sip_server.1.port = 5060
INI;
    }

    protected function grandstreamTemplate(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<gs_provision version="1">
  <config version="1">
    <P9988>{{ENDPOINT_STRATEGY}}</P9988>
    <P9989>{{PROVISIONING_MODE}}</P9989>
    <P271>{{EXTENSION}}</P271>
    <P270>{{DISPLAY_NAME}}</P270>
    <P35>{{EXTENSION}}</P35>
    <P36>{{EXTENSION}}</P36>
    <P34>{{PASSWORD}}</P34>
    <P47>{{DOMAIN}}</P47>
  </config>
</gs_provision>
XML;
    }

    protected function genericTemplate(): string
    {
        return <<<'TEXT'
; NIZAM Auto-Provisioning
; Device: {{DEVICE_NAME}}
; Vendor: {{VENDOR}}
; MAC: {{MAC_ADDRESS}}
; Endpoint Strategy: {{ENDPOINT_STRATEGY}}
; Provisioning Mode: {{PROVISIONING_MODE}}
; Softphone SIP Server: {{SOFTPHONE_SIP_SERVER}}
; Softphone Transport: {{SOFTPHONE_TRANSPORT}}
; Softphone TLS Server: {{SOFTPHONE_TLS_SERVER}}
; Softphone WebSocket URL: {{SOFTPHONE_WEBSOCKET_URL}}

[account]
extension={{EXTENSION}}
password={{PASSWORD}}
display_name={{DISPLAY_NAME}}
domain={{DOMAIN}}
port=5060
TEXT;
    }
}
