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
    }

    /**
     * Compile SIP profile XML for a specific profile model.
     */
    protected function compileProfileXml(SipProfile $profile): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>'."\n";
        $xml .= '<document type="freeswitch/xml">'."\n";
        $xml .= '  <section name="configuration">'."\n";
        $xml .= '    <configuration name="sofia.conf" description="Sofia SIP">'."\n";
        $xml .= '      <profiles>'."\n";
        $xml .= '        <profile name="'.htmlspecialchars($profile->name, ENT_QUOTES | ENT_XML1).'">'."\n";
        $xml .= '          <settings>'."\n";

        foreach ($profile->settings as $setting) {
            $xml .= '            <param name="'.htmlspecialchars($setting->name, ENT_QUOTES | ENT_XML1).'" value="'.htmlspecialchars((string) $setting->value, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        }

        $xml .= '          </settings>'."\n";
        $xml .= '        </profile>'."\n";
        $xml .= '      </profiles>'."\n";
        $xml .= '    </configuration>'."\n";
        $xml .= '  </section>'."\n";
        $xml .= '</document>';

        return $xml;
    }
}
