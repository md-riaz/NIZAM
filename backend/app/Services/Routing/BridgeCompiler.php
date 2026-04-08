<?php

namespace App\Services\Routing;

use App\Models\Bridge;
use App\Models\Tenant;

class BridgeCompiler
{
    public function __construct(
        protected CodecResolutionService $codecResolution,
    ) {}

    public function compileAction(Tenant $tenant, Bridge $bridge, bool $anti = false): string
    {
        $tag = $anti ? 'anti-action' : 'action';

        $codecVars = $this->buildCodecVariables($bridge);

        if ($bridge->bridge_type === 'gateway' && $bridge->gateway_id) {
            $destination = htmlspecialchars($bridge->destination_template, ENT_QUOTES | ENT_XML1);
            $gatewayId = htmlspecialchars($bridge->gateway_id, ENT_QUOTES | ENT_XML1);
            return $codecVars.'            <'.$tag.' application="bridge" data="sofia/gateway/v_'.$gatewayId.'/'.$destination.'"/>'."\n";
        }

        return $codecVars.'            <'.$tag.' application="bridge" data="'.htmlspecialchars($bridge->destination_template, ENT_QUOTES | ENT_XML1).'"/>'."\n";
    }

    /**
     * Build the FreeSWITCH set/export action lines for codec policy variables.
     * These are injected immediately before the bridge action so FreeSWITCH
     * can honour them during outbound SDP negotiation.
     */
    protected function buildCodecVariables(Bridge $bridge): string
    {
        $policy = $bridge->codec_policy ?? 'default';
        $gateway = $bridge->gateway_id ? $bridge->gateway()->first() : null;

        $result = $this->codecResolution->resolve(
            endpointType: 'sip',
            bridge: $bridge,
            gateway: $gateway,
        );

        $lines = '';

        if ($result['inherit_codec']) {
            $lines .= '            <action application="export" data="inherit_codec=true"/>'."\n";
            return $lines;
        }

        if ($result['fs_variable_name'] && $result['fs_variable_value']) {
            $name = htmlspecialchars($result['fs_variable_name'], ENT_QUOTES | ENT_XML1);
            $value = htmlspecialchars($result['fs_variable_value'], ENT_QUOTES | ENT_XML1);
            $lines .= '            <action application="export" data="'.$name.'='.$value.'"/>'."\n";
        }

        // Only allow transcoding when the policy explicitly permits it
        if ($result['transcoding_allowed'] && $policy !== 'inherit') {
            $lines .= '            <action application="export" data="media_mix_inbound_outbound_codecs=true"/>'."\n";
        }

        return $lines;
    }
}
