<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\BlockedDestination;
use App\Models\Bridge;
use App\Models\CallRoutingPolicy;
use App\Models\Did;
use App\Models\Extension;
use App\Models\Flow;
use App\Models\Ivr;
use App\Models\Queue;
use App\Models\RingGroup;
use App\Models\Tenant;
use App\Models\TimeCondition;
use App\Services\DidNormalizationService;
use App\Services\Routing\BridgeCompiler;
use App\Services\Routing\GatewayResolutionService;
use App\Services\Routing\NumberRoutingService;

class DialplanCompiler
{
    protected string $currentEndpointType = 'sip';

    public function __construct(
        protected NumberRoutingService $numberRoutingService,
        protected GatewayResolutionService $gatewayResolutionService,
        protected BridgeCompiler $bridgeCompiler,
    ) {}
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

        $defaultCountryCode = (string) data_get($extension->tenant?->settings, 'default_country_code', '1');
        
        if ($extension->effective_caller_id_name) {
            $xml .= '                <variable name="effective_caller_id_name" value="'.htmlspecialchars($extension->effective_caller_id_name, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        }
        if ($extension->effective_caller_id_number) {
            $normalizedEffective = ltrim(DidNormalizationService::toE164($extension->effective_caller_id_number, $defaultCountryCode), '+');
            $xml .= '                <variable name="effective_caller_id_number" value="'.htmlspecialchars($normalizedEffective, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        }
        if ($extension->outbound_caller_id_name) {
            $xml .= '                <variable name="outbound_caller_id_name" value="'.htmlspecialchars($extension->outbound_caller_id_name, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        }
        if ($extension->outbound_caller_id_number) {
            $normalizedOutboundE164 = DidNormalizationService::toE164($extension->outbound_caller_id_number, $defaultCountryCode);
            $normalizedOutbound = ltrim($normalizedOutboundE164, '+');
            $xml .= '                <variable name="outbound_caller_id_number" value="'.htmlspecialchars($normalizedOutbound, ENT_QUOTES | ENT_XML1).'"/>'."\n";
            
            // P-Asserted-Identity injection from Tenant settings
            $sendPai = data_get($extension->tenant?->settings, 'outbound_caller_id_pai');
            if ($sendPai === null) {
                $sendPai = config('telephony.media.outbound_caller_id_pai', false);
            }
            
            if ($sendPai) {
                $xml .= '                <variable name="sip_h_P-Asserted-Identity" value="&lt;sip:'.htmlspecialchars($normalizedOutboundE164, ENT_QUOTES | ENT_XML1).'@${domain}&gt;"/>'."\n";
            }

            // Privacy header manipulation from Tenant settings
            $privacyMode = data_get($extension->tenant?->settings, 'outbound_caller_id_privacy', 'none');

            if ($privacyMode !== 'none') {
                $privacy = htmlspecialchars($privacyMode, ENT_QUOTES | ENT_XML1);
                $xml .= '                <variable name="sip_h_Privacy" value="'.$privacy.'"/>'."\n";
                $xml .= '                <variable name="origination_privacy" value="'.$privacy.'"/>'."\n";
                
                if ($privacyMode === 'hide' || $privacyMode === 'full') {
                    $xml .= '                <variable name="effective_caller_id_number" value="anonymous"/>'."\n";
                    $xml .= '                <variable name="effective_caller_id_name" value="Anonymous"/>'."\n";
                }
            }
        }

        $xml .= '              </variables>'."\n";
        $xml .= '            </user>'."\n";

        return $xml;
    }

    /**
     * Infer the A-leg endpoint type ('sip' or 'webrtc') from the FreeSWITCH XML-CURL payload.
     * If the call came over WSS (WebSocket Secure), it's a WebRTC call.
     */
    public static function inferEndpointType(array $payload): string
    {
        $viaProtocol = strtolower((string) ($payload['variable_sip_via_protocol'] ?? ''));
        $transport = strtolower((string) ($payload['variable_sip_transport'] ?? ''));

        if ($viaProtocol === 'wss' || $transport === 'wss') {
            return 'webrtc';
        }

        return 'sip';
    }

    /**
     * Compile the inbound dialplan XML for a given domain.
     */
    public function compileDialplan(string $domain, string $destinationNumber, ?string $callerIdNumber = null, array $requestPayload = []): string
    {
        $this->currentEndpointType = self::inferEndpointType($requestPayload);

        $tenant = Tenant::where('domain', $domain)->where('is_active', true)->first();

        if (! $tenant || ! $tenant->isOperational()) {
            return $this->emptyDialplanResponse();
        }

        // Evaluate active policies BEFORE routing resolution
        $policyDecision = $this->evaluatePreRoutingPolicies($tenant, $destinationNumber, $callerIdNumber);

        if ($policyDecision !== null) {
            if ($policyDecision['decision'] === PolicyEvaluator::DECISION_REJECT) {
                return $this->compileRejectDialplan($tenant->domain, $destinationNumber, $policyDecision['reason'] ?? 'Policy rejected');
            }

            if ($policyDecision['decision'] === PolicyEvaluator::DECISION_REDIRECT && isset($policyDecision['redirect_to'])) {
                return $this->compilePolicyRedirect($tenant, $destinationNumber, $policyDecision['redirect_to']);
            }
        }

        // Check if it's a DID routing
        $gatewayContext = $this->gatewayResolutionService->resolveFromXmlCurl($tenant, $requestPayload);
        $did = $this->numberRoutingService->resolveInboundDid(
            $tenant,
            $destinationNumber,
            $gatewayContext['gateway'] ?? null,
        );

        if ($destinationNumber === 'call_delivery_entrypoint') {
            return $this->compileDeliveryEntrypointDialplan($tenant);
        }

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
     * Evaluate active pre-routing policies for a tenant before routing resolution.
     *
     * Returns null if no active policies apply (proceed normally),
     * or a structured decision array from PolicyEvaluator.
     *
     * Note: Policies that are explicitly assigned as DID destinations are excluded
     * from pre-routing evaluation — they are handled via the DID routing path.
     */
    protected function evaluatePreRoutingPolicies(Tenant $tenant, string $destinationNumber, ?string $callerIdNumber = null): ?array
    {
        // Get IDs of policies that are assigned as DID destinations (handled separately)
        $didLinkedPolicyIds = $tenant->dids()
            ->where('destination_type', 'call_routing_policy')
            ->where('is_active', true)
            ->pluck('destination_id');

        $policies = $tenant->callRoutingPolicies()
            ->where('is_active', true)
            ->whereNotIn('id', $didLinkedPolicyIds)
            ->orderBy('priority', 'asc')
            ->get();

        if ($policies->isEmpty()) {
            return null;
        }

        $evaluator = app(PolicyEvaluator::class);
        $context = [
            'tenant_id' => $tenant->id,
            'did' => $destinationNumber,
            'caller_id' => $callerIdNumber ?? '',
            'now' => now(),
        ];

        foreach ($policies as $policy) {
            $decision = $evaluator->evaluatePolicy($policy, $context);

            if ($decision['decision'] !== PolicyEvaluator::DECISION_ALLOW) {
                return $decision;
            }
        }

        return null;
    }

    /**
     * Compile a reject dialplan response (policy rejected the call).
     */
    protected function compileRejectDialplan(string $domain, string $destinationNumber, string $reason): string
    {
        $xml = $this->dialplanHeader($domain);
        $xml .= '        <extension name="policy-reject">'."\n";
        $xml .= '          <condition field="destination_number" expression="^'.preg_quote($destinationNumber, '/').'$">'."\n";
        $xml .= '            <action application="log" data="WARNING Policy rejected call: '.htmlspecialchars($reason, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        $xml .= '            <action application="respond" data="403"/>'."\n";
        $xml .= '          </condition>'."\n";
        $xml .= '        </extension>'."\n";
        $xml .= $this->dialplanFooter();

        return $xml;
    }

    /**
     * Compile a redirect dialplan based on policy decision.
     */
    protected function compilePolicyRedirect(Tenant $tenant, string $destinationNumber, array $redirectTo): string
    {
        $xml = $this->dialplanHeader($tenant->domain);
        $xml .= '        <extension name="policy-redirect">'."\n";
        $xml .= '          <condition field="destination_number" expression="^'.preg_quote($destinationNumber, '/').'$">'."\n";
        $xml .= $this->compileConcurrentCallLimit($tenant);
        $xml .= $this->compileDestinationAction($tenant, $redirectTo['type'], $redirectTo['id']);
        $xml .= '          </condition>'."\n";
        $xml .= '        </extension>'."\n";
        $xml .= $this->dialplanFooter();

        return $xml;
    }

    /**
     * Generate concurrent call limit enforcement actions for a tenant.
     *
     * Uses FreeSWITCH's limit application to cap concurrent calls per tenant domain.
     * When max_concurrent_calls is 0, no limit is enforced (unlimited).
     */
    protected function compileConcurrentCallLimit(Tenant $tenant): string
    {
        $xml = '';
        
        // Concurrent calls limit (concurrency)
        if ($tenant->max_concurrent_calls > 0) {
            $xml .= '            <action application="limit" data="hash '
                .htmlspecialchars($tenant->domain, ENT_QUOTES | ENT_XML1)
                .' concurrent_calls '
                .(int) $tenant->max_concurrent_calls
                .' !NORMAL_TEMPORARY_FAILURE"/>'."\n";
        }

        // Rate limit (calls per minute)
        if ($tenant->max_calls_per_minute > 0) {
            // Using hash with a 60s interval effectively implements calls per minute
            $xml .= '            <action application="limit" data="hash '
                .htmlspecialchars($tenant->domain, ENT_QUOTES | ENT_XML1)
                .' rate_limit '
                .(int) $tenant->max_calls_per_minute
                .'/60 !SWITCH_CONGESTION"/>'."\n";
        }

        return $xml;
    }

    /**
     * Check for blocked destinations and apply security restrictions.
     */
    protected function compileSecurityChecks(Tenant $tenant, string $destinationNumber): string
    {
        // Check for blocked destinations (Global or Tenant-specific)
        $isBlocked = BlockedDestination::where(function($query) use ($tenant) {
                $query->where('tenant_id', $tenant->id)
                      ->orWhereNull('tenant_id');
            })
            ->get()
            ->contains(function($block) use ($destinationNumber) {
                return preg_match('/'.$block->pattern.'/', $destinationNumber);
            });

        if ($isBlocked) {
            return '            <action application="log" data="WARNING Call to blocked destination: '.htmlspecialchars($destinationNumber, ENT_QUOTES | ENT_XML1).'"/>'."\n"
                 .'            <action application="respond" data="403"/>'."\n"
                 .'            <action application="hangup" data="CALL_REJECTED"/>'."\n";
        }

        return '';
    }

    /**
     * Generate the per-tenant recording storage path.
     */
    protected function tenantRecordingPath(Tenant $tenant): string
    {
        $basePath = config('filesystems.disks.recordings.root', storage_path('app/recordings'));

        return $basePath.'/'.$tenant->id;
    }

    public function compileDidExtension(Tenant $tenant, Did $did): string
    {
        $xml = '        <extension name="did-'.htmlspecialchars($did->number, ENT_QUOTES | ENT_XML1).'">'."\n";
        $xml .= '          <condition field="destination_number" expression="^'.preg_quote($did->number, '/').'$">'."\n";
        $xml .= $this->compileSecurityChecks($tenant, $did->number);
        $xml .= $this->compileConcurrentCallLimit($tenant);

        switch ($did->destination_type) {
            case 'extension':
                $ext = $tenant->extensions()->find($did->destination_id);
                if ($ext) {
                    $xml .= $this->compileHumanTargetHandoffAction($tenant, 'extension', (string) $ext->id);
                }
                break;
            case 'agent':
                $agent = $tenant->agents()->find($did->destination_id);
                if ($agent) {
                    $xml .= $this->compileAgentActions($tenant, $agent);
                }
                break;
            case 'ivr':
                $ivr = $tenant->ivrs()->find($did->destination_id);
                if ($ivr) {
                    $xml .= '            <action application="ivr" data="'.htmlspecialchars($ivr->name, ENT_QUOTES | ENT_XML1).'"/>'."\n";
                }
                break;
            case 'ring_group':
                $rg = $tenant->ringGroups()->find($did->destination_id);
                if ($rg) {
                    $xml .= $this->compileRingGroupActions($tenant, $rg);
                }
                break;
            case 'queue':
                $queue = $tenant->queues()->find($did->destination_id);
                if ($queue) {
                    $xml .= $this->compileQueueActions($tenant, $queue);
                }
                break;
            case 'voicemail':
                $ext = $tenant->extensions()->find($did->destination_id);
                if ($ext) {
                    $xml .= '            <action application="voicemail" data="default '.htmlspecialchars($tenant->domain, ENT_QUOTES | ENT_XML1).' '.htmlspecialchars($ext->extension, ENT_QUOTES | ENT_XML1).'"/>'."\n";
                }
                break;
            case 'time_condition':
                $tc = $tenant->timeConditions()->find($did->destination_id);
                if ($tc) {
                    $xml .= $this->compileTimeConditionActions($tenant, $tc);
                }
                break;
            case 'call_routing_policy':
                $policy = $tenant->callRoutingPolicies()->find($did->destination_id);
                if ($policy) {
                    $xml .= $this->compilePolicyRouting($tenant, $policy);
                }
                break;
            case 'flow':
                $flow = $tenant->flows()->find($did->destination_id);
                if ($flow) {
                    // STEP 8: Route to compiled flow entry extension instead of answer + park
                    $xml .= '            <action application="transfer" data="flow_'.$flow->id.' XML '.htmlspecialchars($tenant->domain, ENT_QUOTES | ENT_XML1).'"/>'."\n";
                }
                break;
            case 'bridge':
                $bridge = $tenant->bridges()->where('is_active', true)->find($did->destination_id);
                if ($bridge) {
                    $xml .= $this->bridgeCompiler->compileAction($tenant, $bridge, false, $this->currentEndpointType);
                }
                break;
        }

        $xml .= '          </condition>'."\n";
        $xml .= '        </extension>'."\n";

        return $xml;
    }

    protected function compileDidRouting(Tenant $tenant, Did $did): string
    {
        $xml = $this->dialplanHeader($tenant->domain);
        $xml .= $this->compileDidExtension($tenant, $did);
        $xml .= $this->dialplanFooter();

        return $xml;
    }

    protected function compileDeliveryEntrypointDialplan(Tenant $tenant): string
    {
        $xml = $this->dialplanHeader($tenant->domain);
        $xml .= '        <extension name="call-delivery-entrypoint">'."\n";
        $xml .= '          <condition field="destination_number" expression="^call_delivery_entrypoint$">'."\n";
        $xml .= '            <action application="answer"/>'."\n";
        $xml .= '            <action application="set" data="nizam_call_uuid=${uuid}"/>'."\n";
        $xml .= '            <action application="park"/>'."\n";
        $xml .= '          </condition>'."\n";
        $xml .= '        </extension>'."\n";
        $xml .= $this->dialplanFooter();

        return $xml;
    }

    public function compileLocalExtension(Tenant $tenant, Extension $extension): string
    {
        $xml = '        <extension name="local-'.htmlspecialchars($extension->extension, ENT_QUOTES | ENT_XML1).'">'."\n";
        $xml .= '          <condition field="destination_number" expression="^'.preg_quote($extension->extension, '/').'$">'."\n";
        $xml .= $this->compileSecurityChecks($tenant, $extension->extension);
        $xml .= $this->compileConcurrentCallLimit($tenant);
        $xml .= $this->compileHumanTargetHandoffAction($tenant, 'extension', (string) $extension->id);
        $xml .= '          </condition>'."\n";
        $xml .= '        </extension>'."\n";

        return $xml;
    }

    protected function compileExtensionDialplan(Tenant $tenant, Extension $extension): string
    {
        $xml = $this->dialplanHeader($tenant->domain);
        $xml .= $this->compileLocalExtension($tenant, $extension);
        $xml .= $this->dialplanFooter();

        return $xml;
    }

    protected function compileRingGroupActions(Tenant $tenant, RingGroup $ringGroup): string
    {
        $memberIds = $ringGroup->members ?? [];
        $extensions = $tenant->extensions()->whereIn('id', $memberIds)->where('is_active', true)->get();
        $fallback = null;

        if ($ringGroup->fallback_destination_type && $ringGroup->fallback_destination_id) {
            $fallback = $this->compileDestinationAction($tenant, $ringGroup->fallback_destination_type, $ringGroup->fallback_destination_id);
        }

        if ($extensions->isEmpty()) {
            return $fallback ?? '';
        }

        $xml = '            <action application="set" data="call_timeout='.(int) $ringGroup->ring_timeout.'"/>'."\n";
        $xml .= $this->compileHumanTargetHandoffAction($tenant, 'ring_group', (string) $ringGroup->id);

        if ($fallback) {
            $xml .= '            <condition field="${originate_disposition}" expression="^(USER_BUSY|NO_ANSWER|NO_USER_RESPONSE|ALLOTTED_TIMEOUT|NO_ROUTE_DESTINATION|UNALLOCATED_NUMBER|SUBSCRIBER_ABSENT)$">'."\n";
            $xml .= $fallback;
            $xml .= '            </condition>'."\n";
        }

        return $xml;
    }

    protected function compileQueueActions(Tenant $tenant, Queue $queue): string
    {
        $hasEligibleMembers = $queue->members()
            ->where('agents.is_active', true)
            ->exists();

        if (! $hasEligibleMembers) {
            return '';
        }

        return $this->compileHumanTargetHandoffAction($tenant, 'queue', (string) $queue->id);
    }

    protected function compileAgentActions(Tenant $tenant, Agent $agent): string
    {
        if (! $agent->is_active) {
            return '';
        }

        return $this->compileHumanTargetHandoffAction($tenant, 'agent', (string) $agent->id);
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
    protected function compileHumanTargetHandoffAction(Tenant $tenant, string $targetType, string $targetId, bool $antiAction = false): string
    {
        $action = $antiAction ? 'anti-action' : 'action';
        $entrypoint = 'call_delivery_entrypoint';

        $xml = '            <'.$action.' application="set" data="nizam_delivery_target_type='.htmlspecialchars($targetType, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        $xml .= '            <'.$action.' application="set" data="nizam_delivery_target_id='.htmlspecialchars($targetId, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        $xml .= '            <'.$action.' application="set" data="nizam_call_uuid=${uuid}"/>'."\n";
        $xml .= '            <'.$action.' application="transfer" data="'.$entrypoint.' XML '.htmlspecialchars($tenant->domain, ENT_QUOTES | ENT_XML1).'"/>'."\n";

        return $xml;
    }

    protected function compileAntiAction(Tenant $tenant, string $type, string $id): string
    {
        switch ($type) {
            case 'extension':
                $ext = $tenant->extensions()->find($id);
                if ($ext) {
                    return $this->compileHumanTargetHandoffAction($tenant, 'extension', (string) $ext->id, true);
                }
                break;
            case 'agent':
                $agent = $tenant->agents()->find($id);
                if ($agent) {
                    return $this->compileHumanTargetHandoffAction($tenant, 'agent', (string) $agent->id, true);
                }
                break;
            case 'voicemail':
                $ext = $tenant->extensions()->find($id);
                if ($ext) {
                    return '            <anti-action application="voicemail" data="default '.htmlspecialchars($tenant->domain, ENT_QUOTES | ENT_XML1).' '.htmlspecialchars($ext->extension, ENT_QUOTES | ENT_XML1).'"/>'."\n";
                }
                break;
            case 'ring_group':
                $rg = $tenant->ringGroups()->find($id);
                if ($rg) {
                    return $this->compileHumanTargetHandoffAction($tenant, 'ring_group', (string) $rg->id, true);
                }
                break;
            case 'queue':
                $queue = $tenant->queues()->find($id);
                if ($queue) {
                    return $this->compileHumanTargetHandoffAction($tenant, 'queue', (string) $queue->id, true);
                }
                break;
            case 'ivr':
                $ivr = $tenant->ivrs()->find($id);
                if ($ivr) {
                    return '            <anti-action application="ivr" data="'.htmlspecialchars($ivr->name, ENT_QUOTES | ENT_XML1).'"/>'."\n";
                }
                break;
            case 'flow':
                $flow = $tenant->flows()->find($id);
                if ($flow) {
                    return '            <anti-action application="transfer" data="flow_'.htmlspecialchars($flow->id, ENT_QUOTES | ENT_XML1).' XML '.htmlspecialchars($tenant->domain, ENT_QUOTES | ENT_XML1).'"/>'."\n";
                }
                break;
            case 'bridge':
                $bridge = $tenant->bridges()->where('is_active', true)->find($id);
                if ($bridge) {
                    return $this->bridgeCompiler->compileAction($tenant, $bridge, true, $this->currentEndpointType);
                }
                break;
        }

        return '';
    }

    protected function compileDestinationAction(Tenant $tenant, string $type, string $id): string
    {
        switch ($type) {
            case 'extension':
                $ext = $tenant->extensions()->find($id);
                if ($ext) {
                    return $this->compileHumanTargetHandoffAction($tenant, 'extension', (string) $ext->id);
                }
                break;
            case 'agent':
                $agent = $tenant->agents()->find($id);
                if ($agent) {
                    return $this->compileHumanTargetHandoffAction($tenant, 'agent', (string) $agent->id);
                }
                break;
            case 'voicemail':
                $ext = $tenant->extensions()->find($id);
                if ($ext) {
                    return '            <action application="voicemail" data="default '.htmlspecialchars($tenant->domain, ENT_QUOTES | ENT_XML1).' '.htmlspecialchars($ext->extension, ENT_QUOTES | ENT_XML1).'"/>'."\n";
                }
                break;
            case 'ring_group':
                $rg = $tenant->ringGroups()->find($id);
                if ($rg) {
                    return $this->compileHumanTargetHandoffAction($tenant, 'ring_group', (string) $rg->id);
                }
                break;
            case 'queue':
                $queue = $tenant->queues()->find($id);
                if ($queue) {
                    return $this->compileHumanTargetHandoffAction($tenant, 'queue', (string) $queue->id);
                }
                break;
            case 'ivr':
                $ivr = $tenant->ivrs()->find($id);
                if ($ivr) {
                    return '            <action application="ivr" data="'.htmlspecialchars($ivr->name, ENT_QUOTES | ENT_XML1).'"/>'."\n";
                }
                break;
            case 'flow':
                $flow = $tenant->flows()->find($id);
                if ($flow) {
                    return '            <action application="transfer" data="flow_'.htmlspecialchars($flow->id, ENT_QUOTES | ENT_XML1).' XML '.htmlspecialchars($tenant->domain, ENT_QUOTES | ENT_XML1).'"/>'."\n";
                }
                break;
            case 'bridge':
                $bridge = $tenant->bridges()->where('is_active', true)->find($id);
                if ($bridge) {
                    return $this->bridgeCompiler->compileAction($tenant, $bridge, false, $this->currentEndpointType);
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
