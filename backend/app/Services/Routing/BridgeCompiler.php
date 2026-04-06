<?php

namespace App\Services\Routing;

use App\Models\Bridge;
use App\Models\Tenant;

class BridgeCompiler
{
    public function compileAction(Tenant $tenant, Bridge $bridge, bool $anti = false): string
    {
        $tag = $anti ? 'anti-action' : 'action';

        if ($bridge->bridge_type === 'gateway' && $bridge->gateway_id) {
            $destination = htmlspecialchars($bridge->destination_template, ENT_QUOTES | ENT_XML1);
            return '            <'.$tag.' application="bridge" data="sofia/gateway/v_'.htmlspecialchars($bridge->gateway_id, ENT_QUOTES | ENT_XML1).'/'.$destination.'"/>'."\n";
        }

        return '            <'.$tag.' application="bridge" data="'.htmlspecialchars($bridge->destination_template, ENT_QUOTES | ENT_XML1).'"/>'."\n";
    }
}
