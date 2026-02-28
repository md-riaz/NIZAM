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
        $tenant = $profile->tenant;

        $variables = [
            '{{DEVICE_NAME}}' => $profile->name,
            '{{VENDOR}}' => $profile->vendor,
            '{{MAC_ADDRESS}}' => $profile->mac_address ?? '',
            '{{DOMAIN}}' => $tenant->domain ?? '',
            '{{TENANT_NAME}}' => $tenant->name ?? '',
        ];

        // Add extension variables if assigned
        if ($extension) {
            $variables = array_merge($variables, [
                '{{EXTENSION}}' => $extension->extension,
                '{{PASSWORD}}' => $extension->password,
                '{{DISPLAY_NAME}}' => trim(($extension->directory_first_name ?? '').' '.($extension->directory_last_name ?? '')),
                '{{CALLER_ID_NAME}}' => $extension->effective_caller_id_name ?? $extension->directory_first_name ?? '',
                '{{CALLER_ID_NUMBER}}' => $extension->effective_caller_id_number ?? $extension->extension,
                '{{VOICEMAIL_ENABLED}}' => $extension->voicemail_enabled ? 'true' : 'false',
            ]);
        }

        return str_replace(array_keys($variables), array_values($variables), $template);
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

    protected function polycomTemplate(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<polycomConfig>
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

[account]
extension={{EXTENSION}}
password={{PASSWORD}}
display_name={{DISPLAY_NAME}}
domain={{DOMAIN}}
port=5060
TEXT;
    }
}
