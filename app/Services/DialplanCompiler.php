<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Extension;
use App\Models\Did;
use App\Models\Ivr;
use App\Models\RingGroup;
use App\Models\TimeCondition;

class DialplanCompiler
{
    /**
     * Compile the SIP directory XML for a given domain.
     */
    public function compileDirectory(string $domain): string
    {
        $tenant = Tenant::where('domain', $domain)->where('is_active', true)->first();

        if (!$tenant) {
            return $this->emptyDirectoryResponse();
        }

        $extensions = $tenant->extensions()->where('is_active', true)->get();

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>' . "\n";
        $xml .= '<document type="freeswitch/xml">' . "\n";
        $xml .= '  <section name="directory">' . "\n";
        $xml .= '    <domain name="' . htmlspecialchars($domain, ENT_QUOTES | ENT_XML1) . '">' . "\n";
        $xml .= '      <params>' . "\n";
        $xml .= '        <param name="dial-string" value="{^^:sip_invite_domain=${dialed_domain}:presence_id=${dialed_user}@${dialed_domain}}${sofia_contact(*/${dialed_user}@${dialed_domain})},${verto_contact(${dialed_user}@${dialed_domain})}"/>' . "\n";
        $xml .= '      </params>' . "\n";
        $xml .= '      <groups>' . "\n";
        $xml .= '        <group name="default">' . "\n";
        $xml .= '          <users>' . "\n";

        foreach ($extensions as $ext) {
            $xml .= $this->compileExtensionEntry($ext);
        }

        $xml .= '          </users>' . "\n";
        $xml .= '        </group>' . "\n";
        $xml .= '      </groups>' . "\n";
        $xml .= '    </domain>' . "\n";
        $xml .= '  </section>' . "\n";
        $xml .= '</document>';

        return $xml;
    }

    /**
     * Compile a single extension entry for the directory.
     */
    protected function compileExtensionEntry(Extension $extension): string
    {
        $xml = '            <user id="' . htmlspecialchars($extension->extension, ENT_QUOTES | ENT_XML1) . '">' . "\n";
        $xml .= '              <params>' . "\n";
        $xml .= '                <param name="password" value="' . htmlspecialchars($extension->password, ENT_QUOTES | ENT_XML1) . '"/>' . "\n";

        if ($extension->voicemail_enabled && $extension->voicemail_pin) {
            $xml .= '                <param name="vm-password" value="' . htmlspecialchars($extension->voicemail_pin, ENT_QUOTES | ENT_XML1) . '"/>' . "\n";
            $xml .= '                <param name="vm-enabled" value="true"/>' . "\n";
        }

        $xml .= '              </params>' . "\n";
        $xml .= '              <variables>' . "\n";

        if ($extension->effective_caller_id_name) {
            $xml .= '                <variable name="effective_caller_id_name" value="' . htmlspecialchars($extension->effective_caller_id_name, ENT_QUOTES | ENT_XML1) . '"/>' . "\n";
        }
        if ($extension->effective_caller_id_number) {
            $xml .= '                <variable name="effective_caller_id_number" value="' . htmlspecialchars($extension->effective_caller_id_number, ENT_QUOTES | ENT_XML1) . '"/>' . "\n";
        }
        if ($extension->outbound_caller_id_name) {
            $xml .= '                <variable name="outbound_caller_id_name" value="' . htmlspecialchars($extension->outbound_caller_id_name, ENT_QUOTES | ENT_XML1) . '"/>' . "\n";
        }
        if ($extension->outbound_caller_id_number) {
            $xml .= '                <variable name="outbound_caller_id_number" value="' . htmlspecialchars($extension->outbound_caller_id_number, ENT_QUOTES | ENT_XML1) . '"/>' . "\n";
        }

        $xml .= '              </variables>' . "\n";
        $xml .= '            </user>' . "\n";

        return $xml;
    }

    /**
     * Compile the inbound dialplan XML for a given domain.
     */
    public function compileDialplan(string $domain, string $destinationNumber): string
    {
        $tenant = Tenant::where('domain', $domain)->where('is_active', true)->first();

        if (!$tenant) {
            return $this->emptyDialplanResponse();
        }

        // Check if it's a DID routing
        $did = $tenant->dids()
            ->where('number', $destinationNumber)
            ->where('is_active', true)
            ->first();

        if ($did) {
            return $this->compileDidRouting($tenant, $did);
        }

        // Check if it's an internal extension call
        $extension = $tenant->extensions()
            ->where('extension', $destinationNumber)
            ->where('is_active', true)
            ->first();

        if ($extension) {
            return $this->compileExtensionDialplan($tenant, $extension);
        }

        return $this->emptyDialplanResponse();
    }

    protected function compileDidRouting(Tenant $tenant, Did $did): string
    {
        $xml = $this->dialplanHeader($tenant->domain);
        $xml .= '        <extension name="did-' . htmlspecialchars($did->number, ENT_QUOTES | ENT_XML1) . '">' . "\n";
        $xml .= '          <condition field="destination_number" expression="^' . preg_quote($did->number, '/') . '$">' . "\n";

        switch ($did->destination_type) {
            case 'extension':
                $ext = Extension::find($did->destination_id);
                if ($ext) {
                    $xml .= '            <action application="bridge" data="user/' . htmlspecialchars($ext->extension, ENT_QUOTES | ENT_XML1) . '@' . htmlspecialchars($tenant->domain, ENT_QUOTES | ENT_XML1) . '"/>' . "\n";
                }
                break;
            case 'ivr':
                $ivr = Ivr::find($did->destination_id);
                if ($ivr) {
                    $xml .= '            <action application="ivr" data="' . htmlspecialchars($ivr->name, ENT_QUOTES | ENT_XML1) . '"/>' . "\n";
                }
                break;
            case 'ring_group':
                $rg = RingGroup::find($did->destination_id);
                if ($rg) {
                    $xml .= $this->compileRingGroupActions($tenant, $rg);
                }
                break;
            case 'voicemail':
                $ext = Extension::find($did->destination_id);
                if ($ext) {
                    $xml .= '            <action application="voicemail" data="default ' . htmlspecialchars($tenant->domain, ENT_QUOTES | ENT_XML1) . ' ' . htmlspecialchars($ext->extension, ENT_QUOTES | ENT_XML1) . '"/>' . "\n";
                }
                break;
        }

        $xml .= '          </condition>' . "\n";
        $xml .= '        </extension>' . "\n";
        $xml .= $this->dialplanFooter();

        return $xml;
    }

    protected function compileExtensionDialplan(Tenant $tenant, Extension $extension): string
    {
        $xml = $this->dialplanHeader($tenant->domain);
        $xml .= '        <extension name="local-' . htmlspecialchars($extension->extension, ENT_QUOTES | ENT_XML1) . '">' . "\n";
        $xml .= '          <condition field="destination_number" expression="^' . preg_quote($extension->extension, '/') . '$">' . "\n";
        $xml .= '            <action application="bridge" data="user/' . htmlspecialchars($extension->extension, ENT_QUOTES | ENT_XML1) . '@' . htmlspecialchars($tenant->domain, ENT_QUOTES | ENT_XML1) . '"/>' . "\n";
        $xml .= '          </condition>' . "\n";
        $xml .= '        </extension>' . "\n";
        $xml .= $this->dialplanFooter();

        return $xml;
    }

    protected function compileRingGroupActions(Tenant $tenant, RingGroup $ringGroup): string
    {
        $memberIds = $ringGroup->members ?? [];
        $extensions = Extension::whereIn('id', $memberIds)->where('is_active', true)->get();

        if ($extensions->isEmpty()) {
            return '';
        }

        $dialStrings = $extensions->map(function ($ext) use ($tenant) {
            return 'user/' . $ext->extension . '@' . $tenant->domain;
        });

        if ($ringGroup->strategy === 'simultaneous') {
            return '            <action application="set" data="call_timeout=' . (int) $ringGroup->ring_timeout . '"/>' . "\n"
                 . '            <action application="bridge" data="' . htmlspecialchars($dialStrings->implode(','), ENT_QUOTES | ENT_XML1) . '"/>' . "\n";
        }

        // Sequential
        $xml = '            <action application="set" data="call_timeout=' . (int) $ringGroup->ring_timeout . '"/>' . "\n";
        $xml .= '            <action application="bridge" data="' . htmlspecialchars($dialStrings->implode('|'), ENT_QUOTES | ENT_XML1) . '"/>' . "\n";

        return $xml;
    }

    protected function dialplanHeader(string $domain): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>' . "\n";
        $xml .= '<document type="freeswitch/xml">' . "\n";
        $xml .= '  <section name="dialplan">' . "\n";
        $xml .= '    <context name="' . htmlspecialchars($domain, ENT_QUOTES | ENT_XML1) . '">' . "\n";

        return $xml;
    }

    protected function dialplanFooter(): string
    {
        return '    </context>' . "\n"
             . '  </section>' . "\n"
             . '</document>';
    }

    protected function emptyDirectoryResponse(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="no"?>' . "\n"
             . '<document type="freeswitch/xml">' . "\n"
             . '  <section name="directory"></section>' . "\n"
             . '</document>';
    }

    protected function emptyDialplanResponse(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="no"?>' . "\n"
             . '<document type="freeswitch/xml">' . "\n"
             . '  <section name="dialplan"></section>' . "\n"
             . '</document>';
    }
}
