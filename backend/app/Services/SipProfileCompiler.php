<?php

namespace App\Services;

use App\Models\SipProfile;
use Illuminate\Support\Facades\File;

class SipProfileCompiler
{
    /**
     * Compile all active SIP profiles to disk.
     */
    public function compileAllToDisk(): void
    {
        /** @var \Illuminate\Database\Eloquent\Collection|\App\Models\SipProfile[] $profiles */
        $profiles = SipProfile::with(['settings' => function ($query) {
            $query->where('is_enabled', true);
        }])->where('is_active', true)->get();

        $storagePath = storage_path('app/freeswitch/sip_profiles');
        
        if (!File::exists($storagePath)) {
            File::makeDirectory($storagePath, 0755, true);
        }

        // Clean out existing .xml files
        $existingFiles = File::glob($storagePath . '/*.xml');
        foreach ($existingFiles as $file) {
            File::delete($file);
        }

        foreach ($profiles as $profile) {
            $xml = $this->compileProfileXml($profile);
            File::put($storagePath . '/' . $profile->name . '.xml', $xml);
        }

        $externalGatewayPath = $storagePath . '/external';
        if (!File::exists($externalGatewayPath)) {
            File::makeDirectory($externalGatewayPath, 0755, true);
        }
    }

    /**
     * Compile SIP profile XML for a specific profile model.
     */
    protected function compileProfileXml(SipProfile $profile): string
    {
        $safeName = htmlspecialchars($profile->name, ENT_QUOTES | ENT_XML1);
        $xml = '<profile name="'.$safeName.'">'."\n";
        
        $xml .= '  <aliases>'."\n";
        $xml .= '  </aliases>'."\n";

        if ($profile->name === 'external') {
            $xml .= '  <gateways>'."\n";
            $xml .= '    <X-PRE-PROCESS cmd="include" data="'.$safeName.'/*.xml"/>'."\n";
            $xml .= '  </gateways>'."\n";
        }

        $xml .= '  <domains>'."\n";

        // Map all active tenant domains as aliases to this profile
        // This ensures user_data 1001@domain correctly routes to the directory lookup
        $domains = \App\Models\Tenant::where('is_active', true)->pluck('domain');
        foreach ($domains as $domain) {
            $xml .= '    <domain name="'.htmlspecialchars($domain, ENT_QUOTES | ENT_XML1).'" alias="true" parse="true"/>'."\n";
        }

        $xml .= '    <domain name="all" alias="true" parse="true"/>'."\n";
        $xml .= '  </domains>'."\n";

        $xml .= '  <settings>'."\n";

        foreach ($profile->settings as $setting) {
            $xml .= '    <param name="'.htmlspecialchars($setting->name, ENT_QUOTES | ENT_XML1).'" value="'.htmlspecialchars((string) $setting->value, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        }

        $xml .= '  </settings>'."\n";
        $xml .= '</profile>';

        return $xml;
    }
}
