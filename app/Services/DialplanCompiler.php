<?php

namespace App\Services;

use App\Models\CallFlow;
use App\Models\CallRoutingPolicy;
use App\Models\Did;
use App\Models\Extension;
use App\Models\Ivr;
use App\Models\RingGroup;
use App\Models\Tenant;
use App\Models\TimeCondition;

class DialplanCompiler
{
    /**
     * Compile the SIP directory XML for a given domain.
     */
    public function compileDirectory(string $domain): string
    {
        $tenant = Tenant::where('domain', $domain)->where('is_active', true)->first();

        if (! $tenant || ! $tenant->isOperational()) {
            return $this->emptyDirectoryResponse();
        }

        $extensions = $tenant->extensions()->where('is_active', true)->get();

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>'."\n";
        $xml .= '<document type="freeswitch/xml">'."\n";
        $xml .= '  <section name="directory">'."\n";
        $xml .= '    <domain name="'.htmlspecialchars($domain, ENT_QUOTES | ENT_XML1).'">'."\n";
        $xml .= '      <params>'."\n";
        $xml .= '        <param name="dial-string" value="{^^:sip_invite_domain=${dialed_domain}:presence_id=${dialed_user}@${dialed_domain}}${sofia_contact(*/${dialed_user}@${dialed_domain})},${verto_contact(${dialed_user}@${dialed_domain})}"/>'."\n";
        $xml .= '      </params>'."\n";
        $xml .= '      <groups>'."\n";
        $xml .= '        <group name="default">'."\n";
        $xml .= '          <users>'."\n";

        foreach ($extensions as $ext) {
            $xml .= $this->compileExtensionEntry($ext);
        }

        $xml .= '          </users>'."\n";
        $xml .= '        </group>'."\n";
        $xml .= '      </groups>'."\n";
        $xml .= '    </domain>'."\n";
        $xml .= '  </section>'."\n";
        $xml .= '</document>';

        return $xml;
    }

    /**
     * Compile a single extension entry for the directory.
     */
    protected function compileExtensionEntry(Extension $extension): string
    {
        $xml = '            <user id="'.htmlspecialchars($extension->extension, ENT_QUOTES | ENT_XML1).'">'."\n";
        $xml .= '              <params>'."\n";
        $xml .= '                <param name="password" value="'.htmlspecialchars($extension->password, ENT_QUOTES | ENT_XML1).'"/>'."\n";

        if ($extension->voicemail_enabled && $extension->voicemail_pin) {
            $xml .= '                <param name="vm-password" value="'.htmlspecialchars($extension->voicemail_pin, ENT_QUOTES | ENT_XML1).'"/>'."\n";
            $xml .= '                <param name="vm-enabled" value="true"/>'."\n";
        }

        $xml .= '              </params>'."\n";
        $xml .= '              <variables>'."\n";

        if ($extension->effective_caller_id_name) {
            $xml .= '                <variable name="effective_caller_id_name" value="'.htmlspecialchars($extension->effective_caller_id_name, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        }
        if ($extension->effective_caller_id_number) {
            $xml .= '                <variable name="effective_caller_id_number" value="'.htmlspecialchars($extension->effective_caller_id_number, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        }
        if ($extension->outbound_caller_id_name) {
            $xml .= '                <variable name="outbound_caller_id_name" value="'.htmlspecialchars($extension->outbound_caller_id_name, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        }
        if ($extension->outbound_caller_id_number) {
            $xml .= '                <variable name="outbound_caller_id_number" value="'.htmlspecialchars($extension->outbound_caller_id_number, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        }

        $xml .= '              </variables>'."\n";
        $xml .= '            </user>'."\n";

        return $xml;
    }

    /**
     * Compile the inbound dialplan XML for a given domain.
     */
    public function compileDialplan(string $domain, string $destinationNumber): string
    {
        $tenant = Tenant::where('domain', $domain)->where('is_active', true)->first();

        if (! $tenant || ! $tenant->isOperational()) {
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

        // Fail-safe: no matching route — play a courtesy message and hangup
        return $this->compileFailsafeDialplan($tenant->domain, $destinationNumber);
    }

    /**
     * Generate concurrent call limit enforcement actions for a tenant.
     *
     * Uses FreeSWITCH's limit application to cap concurrent calls per tenant domain.
     * When max_concurrent_calls is 0, no limit is enforced (unlimited).
     */
    protected function compileConcurrentCallLimit(Tenant $tenant): string
    {
        if ($tenant->max_concurrent_calls <= 0) {
            return '';
        }

        $xml = '            <action application="limit" data="hash '
            .htmlspecialchars($tenant->domain, ENT_QUOTES | ENT_XML1)
            .' tenant_calls '
            .(int) $tenant->max_concurrent_calls
            .' !NORMAL_TEMPORARY_FAILURE"/>'."\n";

        return $xml;
    }

    /**
     * Generate the per-tenant recording storage path.
     */
    protected function tenantRecordingPath(Tenant $tenant): string
    {
        $basePath = config('filesystems.disks.recordings.root', storage_path('app/recordings'));

        return $basePath.'/'.$tenant->id;
    }

    protected function compileDidRouting(Tenant $tenant, Did $did): string
    {
        $xml = $this->dialplanHeader($tenant->domain);
        $xml .= '        <extension name="did-'.htmlspecialchars($did->number, ENT_QUOTES | ENT_XML1).'">'."\n";
        $xml .= '          <condition field="destination_number" expression="^'.preg_quote($did->number, '/').'$">'."\n";
        $xml .= $this->compileConcurrentCallLimit($tenant);

        switch ($did->destination_type) {
            case 'extension':
                $ext = Extension::find($did->destination_id);
                if ($ext) {
                    $xml .= '            <action application="bridge" data="user/'.htmlspecialchars($ext->extension, ENT_QUOTES | ENT_XML1).'@'.htmlspecialchars($tenant->domain, ENT_QUOTES | ENT_XML1).'"/>'."\n";
                }
                break;
            case 'ivr':
                $ivr = Ivr::find($did->destination_id);
                if ($ivr) {
                    $xml .= '            <action application="ivr" data="'.htmlspecialchars($ivr->name, ENT_QUOTES | ENT_XML1).'"/>'."\n";
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
                    $xml .= '            <action application="voicemail" data="default '.htmlspecialchars($tenant->domain, ENT_QUOTES | ENT_XML1).' '.htmlspecialchars($ext->extension, ENT_QUOTES | ENT_XML1).'"/>'."\n";
                }
                break;
            case 'time_condition':
                $tc = TimeCondition::find($did->destination_id);
                if ($tc) {
                    $xml .= $this->compileTimeConditionActions($tenant, $tc);
                }
                break;
            case 'call_routing_policy':
                $policy = CallRoutingPolicy::find($did->destination_id);
                if ($policy) {
                    $xml .= $this->compilePolicyRouting($tenant, $policy);
                }
                break;
            case 'call_flow':
                $flow = CallFlow::find($did->destination_id);
                if ($flow) {
                    $xml .= $this->compileCallFlowActions($tenant, $flow);
                }
                break;
        }

        $xml .= '          </condition>'."\n";
        $xml .= '        </extension>'."\n";
        $xml .= $this->dialplanFooter();

        return $xml;
    }

    protected function compileExtensionDialplan(Tenant $tenant, Extension $extension): string
    {
        $xml = $this->dialplanHeader($tenant->domain);
        $xml .= '        <extension name="local-'.htmlspecialchars($extension->extension, ENT_QUOTES | ENT_XML1).'">'."\n";
        $xml .= '          <condition field="destination_number" expression="^'.preg_quote($extension->extension, '/').'$">'."\n";
        $xml .= $this->compileConcurrentCallLimit($tenant);
        $xml .= '            <action application="bridge" data="user/'.htmlspecialchars($extension->extension, ENT_QUOTES | ENT_XML1).'@'.htmlspecialchars($tenant->domain, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        $xml .= '          </condition>'."\n";
        $xml .= '        </extension>'."\n";
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
            return 'user/'.$ext->extension.'@'.$tenant->domain;
        });

        if ($ringGroup->strategy === 'simultaneous') {
            return '            <action application="set" data="call_timeout='.(int) $ringGroup->ring_timeout.'"/>'."\n"
                 .'            <action application="bridge" data="'.htmlspecialchars($dialStrings->implode(','), ENT_QUOTES | ENT_XML1).'"/>'."\n";
        }

        // Sequential
        $xml = '            <action application="set" data="call_timeout='.(int) $ringGroup->ring_timeout.'"/>'."\n";
        $xml .= '            <action application="bridge" data="'.htmlspecialchars($dialStrings->implode('|'), ENT_QUOTES | ENT_XML1).'"/>'."\n";

        return $xml;
    }

    protected function compileTimeConditionActions(Tenant $tenant, TimeCondition $timeCondition): string
    {
        $conditions = $timeCondition->conditions ?? [];
        $xml = '';

        // Build FreeSWITCH condition attributes from the conditions array
        $attrs = $this->buildTimeConditionAttributes($conditions);

        if ($attrs) {
            $xml .= '          </condition>'."\n";
            $xml .= '          <condition'.$attrs.'>'."\n";

            // Match destination — <action>
            if ($timeCondition->match_destination_type && $timeCondition->match_destination_id) {
                $xml .= $this->compileDestinationAction($tenant, $timeCondition->match_destination_type, $timeCondition->match_destination_id);
            }

            // No-match destination — <anti-action>
            if ($timeCondition->no_match_destination_type && $timeCondition->no_match_destination_id) {
                $xml .= $this->compileAntiAction($tenant, $timeCondition->no_match_destination_type, $timeCondition->no_match_destination_id);
            }
        } else {
            // No time attributes — route to match destination unconditionally
            if ($timeCondition->match_destination_type && $timeCondition->match_destination_id) {
                $xml .= $this->compileDestinationAction($tenant, $timeCondition->match_destination_type, $timeCondition->match_destination_id);
            }
        }

        return $xml;
    }

    /**
     * Build FreeSWITCH condition attributes from time condition rules.
     */
    protected function buildTimeConditionAttributes(array $conditions): string
    {
        $attrs = '';

        foreach ($conditions as $condition) {
            $wday = $condition['wday'] ?? '';
            $timeFrom = $condition['time_from'] ?? '';
            $timeTo = $condition['time_to'] ?? '';
            $mday = $condition['mday'] ?? '';
            $mon = $condition['mon'] ?? '';

            if ($wday) {
                $attrs .= ' wday="'.htmlspecialchars($wday, ENT_QUOTES | ENT_XML1).'"';
            }
            if ($timeFrom && $timeTo) {
                $attrs .= ' time-of-day="'.htmlspecialchars("$timeFrom-$timeTo", ENT_QUOTES | ENT_XML1).'"';
            }
            if ($mday) {
                $attrs .= ' mday="'.htmlspecialchars($mday, ENT_QUOTES | ENT_XML1).'"';
            }
            if ($mon) {
                $attrs .= ' mon="'.htmlspecialchars($mon, ENT_QUOTES | ENT_XML1).'"';
            }
        }

        return $attrs;
    }

    /**
     * Compile a FreeSWITCH anti-action (used for no-match branch of time conditions).
     */
    protected function compileAntiAction(Tenant $tenant, string $type, string $id): string
    {
        switch ($type) {
            case 'extension':
                $ext = Extension::find($id);
                if ($ext) {
                    return '            <anti-action application="bridge" data="user/'.htmlspecialchars($ext->extension, ENT_QUOTES | ENT_XML1).'@'.htmlspecialchars($tenant->domain, ENT_QUOTES | ENT_XML1).'"/>'."\n";
                }
                break;
            case 'voicemail':
                $ext = Extension::find($id);
                if ($ext) {
                    return '            <anti-action application="voicemail" data="default '.htmlspecialchars($tenant->domain, ENT_QUOTES | ENT_XML1).' '.htmlspecialchars($ext->extension, ENT_QUOTES | ENT_XML1).'"/>'."\n";
                }
                break;
            case 'ring_group':
                $rg = RingGroup::find($id);
                if ($rg) {
                    $memberIds = $rg->members ?? [];
                    $extensions = Extension::whereIn('id', $memberIds)->where('is_active', true)->get();
                    if ($extensions->isNotEmpty()) {
                        $dialStrings = $extensions->map(fn ($ext) => 'user/'.$ext->extension.'@'.$tenant->domain);

                        return '            <anti-action application="bridge" data="'.htmlspecialchars($dialStrings->implode($rg->strategy === 'simultaneous' ? ',' : '|'), ENT_QUOTES | ENT_XML1).'"/>'."\n";
                    }
                }
                break;
            case 'ivr':
                $ivr = Ivr::find($id);
                if ($ivr) {
                    return '            <anti-action application="ivr" data="'.htmlspecialchars($ivr->name, ENT_QUOTES | ENT_XML1).'"/>'."\n";
                }
                break;
            case 'call_flow':
                $flow = CallFlow::find($id);
                if ($flow) {
                    return $this->compileCallFlowActions($tenant, $flow);
                }
                break;
        }

        return '';
    }

    protected function compileDestinationAction(Tenant $tenant, string $type, string $id): string
    {
        switch ($type) {
            case 'extension':
                $ext = Extension::find($id);
                if ($ext) {
                    return '            <action application="bridge" data="user/'.htmlspecialchars($ext->extension, ENT_QUOTES | ENT_XML1).'@'.htmlspecialchars($tenant->domain, ENT_QUOTES | ENT_XML1).'"/>'."\n";
                }
                break;
            case 'voicemail':
                $ext = Extension::find($id);
                if ($ext) {
                    return '            <action application="voicemail" data="default '.htmlspecialchars($tenant->domain, ENT_QUOTES | ENT_XML1).' '.htmlspecialchars($ext->extension, ENT_QUOTES | ENT_XML1).'"/>'."\n";
                }
                break;
            case 'ring_group':
                $rg = RingGroup::find($id);
                if ($rg) {
                    return $this->compileRingGroupActions($tenant, $rg);
                }
                break;
            case 'ivr':
                $ivr = Ivr::find($id);
                if ($ivr) {
                    return '            <action application="ivr" data="'.htmlspecialchars($ivr->name, ENT_QUOTES | ENT_XML1).'"/>'."\n";
                }
                break;
            case 'call_flow':
                $flow = CallFlow::find($id);
                if ($flow) {
                    return $this->compileCallFlowActions($tenant, $flow);
                }
                break;
        }

        return '';
    }

    /**
     * Compile policy-based routing using time conditions derived from policy conditions.
     */
    protected function compilePolicyRouting(Tenant $tenant, CallRoutingPolicy $policy): string
    {
        $conditions = $policy->conditions ?? [];
        $xml = '';

        $attrs = $this->buildPolicyConditionAttributes($conditions);

        if ($attrs) {
            $xml .= '          </condition>'."\n";
            $xml .= '          <condition'.$attrs.'>'."\n";

            if ($policy->match_destination_type && $policy->match_destination_id) {
                $xml .= $this->compileDestinationAction($tenant, $policy->match_destination_type, $policy->match_destination_id);
            }

            if ($policy->no_match_destination_type && $policy->no_match_destination_id) {
                $xml .= $this->compileAntiAction($tenant, $policy->no_match_destination_type, $policy->no_match_destination_id);
            }
        } else {
            if ($policy->match_destination_type && $policy->match_destination_id) {
                $xml .= $this->compileDestinationAction($tenant, $policy->match_destination_type, $policy->match_destination_id);
            }
        }

        return $xml;
    }

    /**
     * Build FreeSWITCH condition attributes from policy conditions.
     */
    protected function buildPolicyConditionAttributes(array $conditions): string
    {
        $attrs = '';

        foreach ($conditions as $condition) {
            $type = $condition['type'] ?? '';
            $params = $condition['params'] ?? [];

            switch ($type) {
                case 'time_of_day':
                    $start = $params['start'] ?? '';
                    $end = $params['end'] ?? '';
                    if ($start && $end) {
                        $attrs .= ' time-of-day="'.htmlspecialchars("$start-$end", ENT_QUOTES | ENT_XML1).'"';
                    }
                    break;
                case 'day_of_week':
                    $days = $params['days'] ?? [];
                    if (! empty($days)) {
                        $attrs .= ' wday="'.htmlspecialchars(implode(',', $days), ENT_QUOTES | ENT_XML1).'"';
                    }
                    break;
                case 'caller_id_pattern':
                    $pattern = $params['pattern'] ?? '';
                    if ($pattern) {
                        $attrs .= ' caller-id-number="'.htmlspecialchars($pattern, ENT_QUOTES | ENT_XML1).'"';
                    }
                    break;
            }
        }

        return $attrs;
    }

    /**
     * Compile a call flow into dialplan actions.
     */
    protected function compileCallFlowActions(Tenant $tenant, CallFlow $flow): string
    {
        $nodes = $flow->nodes ?? [];
        $xml = '';

        foreach ($nodes as $node) {
            $type = $node['type'] ?? '';
            $data = $node['data'] ?? [];

            switch ($type) {
                case 'play_prompt':
                    $file = $data['file'] ?? '';
                    if ($file) {
                        $xml .= '            <action application="playback" data="'.htmlspecialchars($file, ENT_QUOTES | ENT_XML1).'"/>'."\n";
                    }
                    break;
                case 'collect_input':
                    $min = (int) ($data['min_digits'] ?? 1);
                    $max = (int) ($data['max_digits'] ?? 1);
                    $timeout = (int) ($data['timeout'] ?? 5);
                    $file = $data['file'] ?? 'silence_stream://250';
                    $xml .= '            <action application="play_and_get_digits" data="'.$min.' '.$max.' 3 '.$timeout.'000 # '.htmlspecialchars($file, ENT_QUOTES | ENT_XML1).' silence_stream://250 digits \d+"/>'."\n";
                    break;
                case 'bridge':
                    $destType = $data['destination_type'] ?? '';
                    $destId = $data['destination_id'] ?? '';
                    if ($destType && $destId) {
                        $xml .= $this->compileDestinationAction($tenant, $destType, $destId);
                    }
                    break;
                case 'record':
                    $path = $data['path'] ?? $this->tenantRecordingPath($tenant).'/${uuid}.wav';
                    $xml .= '            <action application="record" data="'.htmlspecialchars($path, ENT_QUOTES | ENT_XML1).'"/>'."\n";
                    break;
                case 'webhook':
                    $url = $data['url'] ?? '';
                    if ($url) {
                        $xml .= '            <action application="curl" data="'.htmlspecialchars($url, ENT_QUOTES | ENT_XML1).'"/>'."\n";
                    }
                    break;
            }
        }

        return $xml;
    }

    protected function compileFailsafeDialplan(string $domain, string $destinationNumber): string
    {
        $xml = $this->dialplanHeader($domain);
        $xml .= '        <extension name="failsafe">'."\n";
        $xml .= '          <condition field="destination_number" expression="^'.preg_quote($destinationNumber, '/').'$">'."\n";
        $xml .= '            <action application="log" data="WARNING Fail-safe route triggered for '.htmlspecialchars($destinationNumber, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        $xml .= '            <action application="respond" data="404"/>'."\n";
        $xml .= '          </condition>'."\n";
        $xml .= '        </extension>'."\n";
        $xml .= $this->dialplanFooter();

        return $xml;
    }

    protected function dialplanHeader(string $domain): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>'."\n";
        $xml .= '<document type="freeswitch/xml">'."\n";
        $xml .= '  <section name="dialplan">'."\n";
        $xml .= '    <context name="'.htmlspecialchars($domain, ENT_QUOTES | ENT_XML1).'">'."\n";

        return $xml;
    }

    protected function dialplanFooter(): string
    {
        return '    </context>'."\n"
             .'  </section>'."\n"
             .'</document>';
    }

    protected function emptyDirectoryResponse(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="no"?>'."\n"
             .'<document type="freeswitch/xml">'."\n"
             .'  <section name="directory"></section>'."\n"
             .'</document>';
    }

    protected function emptyDialplanResponse(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="no"?>'."\n"
             .'<document type="freeswitch/xml">'."\n"
             .'  <section name="dialplan"></section>'."\n"
             .'</document>';
    }
}
