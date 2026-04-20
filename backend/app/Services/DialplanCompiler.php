<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\BlockedDestination;
use App\Models\Bridge;
use App\Models\CallRoutingPolicy;
use App\Models\Did;
use App\Models\Extension;
use App\Models\Flow;
use App\Models\FlowCompiledArtifact;
use App\Models\Ivr;
use App\Models\Queue;
use App\Models\RingGroup;
use App\Models\Schedule;
use App\Models\Organization;
use App\Models\TimeCondition;
use App\Services\DidNormalizationService;
use App\Services\Routing\BridgeCompiler;
use App\Services\Routing\GatewayResolutionService;
use App\Services\Routing\NumberRoutingService;
use App\Models\EndpointBinding;
use App\Services\Routing\RoutingGraphCompiler;

class DialplanCompiler
{
    protected string $currentEndpointType = 'sip';

    public function __construct(
        protected NumberRoutingService $numberRoutingService,
        protected GatewayResolutionService $gatewayResolutionService,
        protected BridgeCompiler $bridgeCompiler,
        protected RoutingGraphCompiler $routingGraphCompiler,
    ) {}
    /**
     * Compile the SIP directory XML for a given domain.
     */
    public function compileDirectory(string $domain, ?string $user = null): string
    {
        $organization = Organization::where('domain', $domain)->where('is_active', true)->first();

        if (! $organization || ! $organization->isOperational()) {
            return $this->emptyDirectoryResponse();
        }

        $query = $organization->extensions()->where('is_active', true);

        if ($user) {
            $query->where('extension', $user);
        }

        $extensions = $query->get();

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>'."\n";
        $xml .= '<document type="freeswitch/xml">'."\n";
        $xml .= '  <section name="directory">'."\n";
        $xml .= '    <domain name="'.htmlspecialchars($domain, ENT_QUOTES | ENT_XML1).'">'."\n";
        $xml .= '      <params>'."\n";
        $xml .= '        <param name="dial-string" value="{^^:sip_invite_domain=${dialed_domain}:presence_id=${dialed_user}@${dialed_domain}}${sofia_contact(internal/${dialed_user}@${dialed_domain})},${verto_contact(${dialed_user}@${dialed_domain})}"/>'."\n";
        $xml .= '      </params>'."\n";
        $xml .= '      <users>'."\n";

        foreach ($extensions as $ext) {
            $xml .= $this->compileExtensionEntry($ext);
        }

        $xml .= '      </users>'."\n";
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

        // FusionPBX parity: dial-string at the user level for more reliable lookup
        $xml .= '                <param name="dial-string" value="{^^:sip_invite_domain=${dialed_domain}:presence_id=${dialed_user}@${dialed_domain}}${sofia_contact(internal/${dialed_user}@${dialed_domain})},${verto_contact(${dialed_user}@${dialed_domain})}"/>'."\n";

        if ($extension->voicemail_enabled && $extension->voicemail_pin) {
            $xml .= '                <param name="vm-password" value="'.htmlspecialchars($extension->voicemail_pin, ENT_QUOTES | ENT_XML1).'"/>'."\n";
            $xml .= '                <param name="vm-enabled" value="true"/>'."\n";
        }

        $xml .= '              </params>'."\n";
        $xml .= '              <variables>'."\n";

        // FusionPBX parity: essential variables for routing and accounting
        $xml .= '                <variable name="user_context" value="'.htmlspecialchars($extension->organization?->domain ?? 'default', ENT_QUOTES | ENT_XML1).'"/>'."\n";
        $xml .= '                <variable name="accountcode" value="'.htmlspecialchars($extension->organization?->domain ?? 'default', ENT_QUOTES | ENT_XML1).'"/>'."\n";
        $xml .= '                <variable name="effective_caller_id_name" value="'.htmlspecialchars($extension->effective_caller_id_name ?? '', ENT_QUOTES | ENT_XML1).'"/>'."\n";
        $xml .= '                <variable name="effective_caller_id_number" value="'.htmlspecialchars($extension->extension, ENT_QUOTES | ENT_XML1).'"/>'."\n";

        $defaultCountryCode = (string) data_get($extension->organization?->settings, 'default_country_code', '1');

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
            
            // P-Asserted-Identity injection from Organization settings
            $sendPai = data_get($extension->organization?->settings, 'outbound_caller_id_pai');
            if ($sendPai === null) {
                $sendPai = config('telephony.media.outbound_caller_id_pai', false);
            }
            
            if ($sendPai) {
                $xml .= '                <variable name="sip_h_P-Asserted-Identity" value="&lt;sip:'.htmlspecialchars($normalizedOutboundE164, ENT_QUOTES | ENT_XML1).'@${domain}&gt;"/>'."\n";
            }

            // Privacy header manipulation from Organization settings
            $privacyMode = data_get($extension->organization?->settings, 'outbound_caller_id_privacy', 'none');

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

        $organization = Organization::where('domain', $domain)->where('is_active', true)->first();

        if (! $organization || ! $organization->isOperational()) {
            return $this->emptyDialplanResponse();
        }

        // Evaluate active policies BEFORE routing resolution
        $policyDecision = $this->evaluatePreRoutingPolicies($organization, $destinationNumber, $callerIdNumber);

        if ($policyDecision !== null) {
            if ($policyDecision['decision'] === PolicyEvaluator::DECISION_REJECT) {
                return $this->compileRejectDialplan($organization->domain, $destinationNumber, $policyDecision['reason'] ?? 'Policy rejected');
            }

            if ($policyDecision['decision'] === PolicyEvaluator::DECISION_REDIRECT && isset($policyDecision['redirect_to'])) {
                return $this->compilePolicyRedirect($organization, $destinationNumber, $policyDecision['redirect_to']);
            }
        }

        // Check if it's a DID routing
        $gatewayContext = $this->gatewayResolutionService->resolveFromXmlCurl($organization, $requestPayload);
        $did = $this->numberRoutingService->resolveInboundDid(
            $organization,
            $destinationNumber,
            $gatewayContext['gateway'] ?? null,
        );

        if ($destinationNumber === 'call_delivery_entrypoint') {
            return $this->compileDeliveryEntrypointDialplan($organization);
        }

        if (str_starts_with($destinationNumber, 'flow_')) {
            $flow = $organization->flows()
                ->whereKey(substr($destinationNumber, 5))
                ->first();

            if ($flow) {
                $entrypoint = $this->resolveFlowEntrypoint($organization, $flow);

                if ($entrypoint) {
                    return $this->compileDirectFlowEntrypointDialplan($organization, $entrypoint);
                }
            }
        }

        if ($this->matchesConvenienceServiceCode($organization, $destinationNumber)) {
            return $this->compileConvenienceDialplan($organization);
        }

        if ($did) {
            $entrypoint = $this->resolveInboundEntrypoint($organization, $did);

            if ($entrypoint) {
                if (($entrypoint['route_type'] ?? null) === 'flow') {
                    return $this->compileDirectFlowEntrypointDialplan($organization, $entrypoint);
                }

                return $this->compileDidRoutingWithResolvedEntrypoint($organization, $did, $entrypoint);
            }

            return $this->compileDidRouting($organization, $did);
        }

        if (str_starts_with($destinationNumber, 'did_preset_')) {
            $did = $organization->dids()->whereKey(substr($destinationNumber, 11))->where('is_active', true)->first();

            if ($did) {
                $entrypoint = $this->resolveInboundEntrypoint($organization, $did);

                if ($entrypoint && ($entrypoint['route_type'] ?? null) === 'preset') {
                    return $this->compileResolvedEntrypointDialplan($organization, $did, $entrypoint);
                }
            }
        }

        // Check if it's an internal extension call
        $extension = $organization->extensions()
            ->where('extension', $destinationNumber)
            ->where('is_active', true)
            ->first();

        if ($extension) {
            // Check for self-call (caller calling their own extension)
            if ($callerIdNumber === $destinationNumber) {
                return $this->compileSelfCallDialplan($organization, $extension);
            }

            return $this->compileExtensionDialplan($organization, $extension);
        }

        // Fail-safe: no matching route — play a courtesy message and hangup
        return $this->compileFailsafeDialplan($organization->domain, $destinationNumber);
    }

    /**
     * Evaluate active pre-routing policies for an organization before routing resolution.
     *
     * Returns null if no active policies apply (proceed normally),
     * or a structured decision array from PolicyEvaluator.
     *
     * Note: Policies that are explicitly assigned as DID destinations are excluded
     * from pre-routing evaluation — they are handled via the DID routing path.
     */
    protected function evaluatePreRoutingPolicies(Organization $organization, string $destinationNumber, ?string $callerIdNumber = null): ?array
    {
        // Get IDs of policies that are assigned as DID destinations (handled separately)
        $didLinkedPolicyIds = $organization->dids()
            ->where('destination_type', 'call_routing_policy')
            ->where('is_active', true)
            ->pluck('destination_id');

        $policies = $organization->callRoutingPolicies()
            ->where('is_active', true)
            ->whereNotIn('id', $didLinkedPolicyIds)
            ->orderBy('priority', 'asc')
            ->get();

        if ($policies->isEmpty()) {
            return null;
        }

        $evaluator = app(PolicyEvaluator::class);
        $context = [
            'organization_id' => $organization->id,
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
    protected function compilePolicyRedirect(Organization $organization, string $destinationNumber, array $redirectTo): string
    {
        $xml = $this->dialplanHeader($organization->domain);
        $xml .= '        <extension name="policy-redirect">'."\n";
        $xml .= '          <condition field="destination_number" expression="^'.preg_quote($destinationNumber, '/').'$">'."\n";
        $xml .= $this->compileConcurrentCallLimit($organization);
        $xml .= $this->compileDestinationAction($organization, $redirectTo['type'], $redirectTo['id']);
        $xml .= '          </condition>'."\n";
        $xml .= '        </extension>'."\n";
        $xml .= $this->dialplanFooter();

        return $xml;
    }

    /**
     * Generate concurrent call limit enforcement actions for an organization.
     *
     * Uses FreeSWITCH's limit application to cap concurrent calls per organization domain.
     * When max_concurrent_calls is 0, no limit is enforced (unlimited).
     */
    protected function compileConcurrentCallLimit(Organization $organization): string
    {
        $xml = '';
        
        // Concurrent calls limit (concurrency)
        if ($organization->max_concurrent_calls > 0) {
            $xml .= '            <action application="limit" data="hash '
                .htmlspecialchars($organization->domain, ENT_QUOTES | ENT_XML1)
                .' concurrent_calls '
                .(int) $organization->max_concurrent_calls
                .' !NORMAL_TEMPORARY_FAILURE"/>'."\n";
        }

        // Rate limit (calls per minute)
        if ($organization->max_calls_per_minute > 0) {
            // Using hash with a 60s interval effectively implements calls per minute
            $xml .= '            <action application="limit" data="hash '
                .htmlspecialchars($organization->domain, ENT_QUOTES | ENT_XML1)
                .' rate_limit '
                .(int) $organization->max_calls_per_minute
                .'/60 !SWITCH_CONGESTION"/>'."\n";
        }

        return $xml;
    }

    /**
     * Check for blocked destinations and apply security restrictions.
     */
    protected function compileSecurityChecks(Organization $organization, string $destinationNumber): string
    {
        // Check for blocked destinations (Global or Organization-specific)
        $isBlocked = BlockedDestination::where(function($query) use ($organization) {
                $query->where('organization_id', $organization->id)
                      ->orWhereNull('organization_id');
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
     * Generate the per-organization recording storage path.
     */
    protected function organizationRecordingPath(Organization $organization): string
    {
        $basePath = config('filesystems.disks.recordings.root', storage_path('app/recordings'));

        return $basePath.'/'.$organization->id;
    }

    public function compileDidExtension(Organization $organization, Did $did): string
    {
        $xml = '        <extension name="did-'.htmlspecialchars($did->number, ENT_QUOTES | ENT_XML1).'">'."\n";
        $xml .= '          <condition field="destination_number" expression="^'.preg_quote($did->number, '/').'$">'."\n";
        $xml .= $this->compileSecurityChecks($organization, $did->number);
        $xml .= $this->compileConcurrentCallLimit($organization);

        switch ($did->destination_type) {
            case 'extension':
                $ext = $organization->extensions()->find($did->destination_id);
                if ($ext) {
                    $xml .= $this->compileExtensionDestinationAction($organization, $ext);
                }
                break;
            case 'agent':
                $agent = $organization->agents()->find($did->destination_id);
                if ($agent) {
                    $xml .= $this->compileAgentActions($organization, $agent);
                }
                break;
            case 'ivr':
                $ivr = $organization->ivrs()->find($did->destination_id);
                if ($ivr) {
                    $xml .= '            <action application="ivr" data="'.htmlspecialchars($ivr->name, ENT_QUOTES | ENT_XML1).'"/>'."\n";
                }
                break;
            case 'ring_group':
                $rg = $organization->ringGroups()->find($did->destination_id);
                if ($rg) {
                    $xml .= $this->compileRingGroupActions($organization, $rg);
                }
                break;
            case 'queue':
                $queue = $organization->queues()->find($did->destination_id);
                if ($queue) {
                    $xml .= $this->compileQueueActions($organization, $queue);
                }
                break;
            case 'voicemail':
                $ext = $organization->extensions()->find($did->destination_id);
                if ($ext) {
                    $xml .= '            <action application="voicemail" data="default '.htmlspecialchars($organization->domain, ENT_QUOTES | ENT_XML1).' '.htmlspecialchars($ext->extension, ENT_QUOTES | ENT_XML1).'"/>'."\n";
                }
                break;
            case 'time_condition':
                $tc = $organization->timeConditions()->find($did->destination_id);
                if ($tc) {
                    $xml .= $this->compileTimeConditionActions($organization, $tc);
                }
                break;
            case 'call_routing_policy':
                $policy = $organization->callRoutingPolicies()->find($did->destination_id);
                if ($policy) {
                    $xml .= $this->compilePolicyRouting($organization, $policy);
                }
                break;
            case 'flow':
                $flow = $organization->flows()->find($did->destination_id);
                if ($flow) {
                    // STEP 8: Route to compiled flow entry extension instead of answer + park
                    $xml .= '            <action application="transfer" data="flow_'.$flow->id.' XML '.htmlspecialchars($organization->domain, ENT_QUOTES | ENT_XML1).'"/>'."\n";
                }
                break;
            case 'bridge':
                $bridge = $organization->bridges()->where('is_active', true)->find($did->destination_id);
                if ($bridge) {
                    $xml .= $this->bridgeCompiler->compileAction($organization, $bridge, false, $this->currentEndpointType);
                }
                break;
        }

        $xml .= '          </condition>'."\n";
        $xml .= '        </extension>'."\n";

        return $xml;
    }

    protected function compileDidRouting(Organization $organization, Did $did): string
    {
        $xml = $this->dialplanHeader($organization->domain);
        $xml .= $this->compileDidExtension($organization, $did);
        $xml .= $this->dialplanFooter();

        return $xml;
    }

    /**
     * @return array{entrypoint:string, route_type:string, route_id:string, route_name:string|null, metadata:array<string, mixed>}|null
     */
    protected function resolveInboundEntrypoint(Organization $organization, Did $did): ?array
    {
        if ($did->destination_type === 'flow') {
            $flow = $organization->flows()->with(['activeVersion.routingGraphArtifact'])->find($did->destination_id);

            if (! $flow || ! $flow->activeVersion) {
                return null;
            }

            $artifact = $flow->activeVersion->routingGraphArtifact;

            if (! $artifact) {
                $artifact = $this->routingGraphCompiler->store($flow->activeVersion);
            }

            $graph = $artifact->decodedContent() ?? [];
            $entryExtension = (string) data_get($graph, 'entrypoint.extension', '');

            if ($entryExtension === '') {
                return null;
            }

            return [
                'entrypoint' => $entryExtension,
                'route_type' => 'flow',
                'route_id' => (string) $flow->id,
                'route_name' => $flow->name,
                'metadata' => [
                    'artifact_id' => $artifact->id,
                    'artifact_checksum' => $artifact->checksum,
                    'flow_version_id' => $flow->activeVersion->id,
                    'entry_node_id' => data_get($graph, 'entrypoint.node_id'),
                ],
            ];
        }

        $presetMap = [
            'extension' => fn () => $organization->extensions()->find($did->destination_id),
            'agent' => fn () => $organization->agents()->find($did->destination_id),
            'ivr' => fn () => $organization->ivrs()->find($did->destination_id),
            'ring_group' => fn () => $organization->ringGroups()->find($did->destination_id),
            'queue' => fn () => $organization->queues()->find($did->destination_id),
            'voicemail' => fn () => $organization->extensions()->find($did->destination_id),
            'time_condition' => fn () => $organization->timeConditions()->find($did->destination_id),
            'call_routing_policy' => fn () => $organization->callRoutingPolicies()->find($did->destination_id),
            'bridge' => fn () => $organization->bridges()->where('is_active', true)->find($did->destination_id),
        ];

        $resolver = $presetMap[$did->destination_type] ?? null;

        if (! $resolver) {
            return null;
        }

        $target = $resolver();

        if (! $target) {
            return null;
        }

        return [
            'entrypoint' => $this->didPresetEntrypointName($did),
            'route_type' => 'preset',
            'route_id' => (string) $did->id,
            'route_name' => method_exists($target, 'getAttribute') ? ($target->getAttribute('name') ?? $target->getAttribute('extension')) : null,
            'metadata' => [
                'destination_type' => $did->destination_type,
                'destination_id' => (string) $did->destination_id,
            ],
        ];
    }

    protected function didPresetEntrypointName(Did $did): string
    {
        return 'did_preset_'.$did->id;
    }

    protected function compileResolvedEntrypointExtension(Organization $organization, Did $did, array $entrypoint): string
    {
        $entrypointName = (string) $entrypoint['entrypoint'];
        $routeType = (string) $entrypoint['route_type'];
        $routeId = (string) $entrypoint['route_id'];
        $routeName = $entrypoint['route_name'] ?? null;

        $xml = '        <extension name="did-entrypoint-'.htmlspecialchars($did->id, ENT_QUOTES | ENT_XML1).'">'."\n";
        $xml .= '          <condition field="destination_number" expression="^'.preg_quote($entrypointName, '/').'$">'."\n";
        $xml .= '            <action application="set" data="nizam_entrypoint_route_type='.htmlspecialchars($routeType, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        $xml .= '            <action application="set" data="nizam_entrypoint_route_id='.htmlspecialchars($routeId, ENT_QUOTES | ENT_XML1).'"/>'."\n";

        if (filled($routeName)) {
            $xml .= '            <action application="set" data="nizam_entrypoint_route_name='.htmlspecialchars((string) $routeName, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        }

        foreach (($entrypoint['metadata'] ?? []) as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $xml .= '            <action application="set" data="nizam_entrypoint_'.htmlspecialchars((string) $key, ENT_QUOTES | ENT_XML1).'='.htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        }

        if ($routeType === 'flow') {
            $xml .= '            <action application="transfer" data="'.htmlspecialchars($entrypointName, ENT_QUOTES | ENT_XML1).' XML '.htmlspecialchars($organization->domain, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        } else {
            $xml .= $this->compileDestinationAction($organization, (string) data_get($entrypoint, 'metadata.destination_type', ''), (string) data_get($entrypoint, 'metadata.destination_id', ''));
        }

        $xml .= '          </condition>'."\n";
        $xml .= '        </extension>'."\n";

        return $xml;
    }

    protected function compileDidRoutingWithResolvedEntrypoint(Organization $organization, Did $did, array $entrypoint): string
    {
        $xml = $this->dialplanHeader($organization->domain);
        $xml .= $this->compileDidExtension($organization, $did);

        if (($entrypoint['route_type'] ?? null) === 'preset') {
            $xml .= $this->compileResolvedEntrypointExtension($organization, $did, $entrypoint);
        }

        $xml .= $this->dialplanFooter();

        return $xml;
    }

    protected function compileResolvedEntrypointDialplan(Organization $organization, Did $did, array $entrypoint): string
    {
        $xml = $this->dialplanHeader($organization->domain);
        $xml .= $this->compileResolvedEntrypointExtension($organization, $did, $entrypoint);
        $xml .= $this->dialplanFooter();

        return $xml;
    }

    protected function compileDirectFlowEntrypointDialplan(Organization $organization, array $entrypoint): string
    {
        $xml = $this->dialplanHeader($organization->domain);
        $xml .= '        <extension name="flow-entrypoint-'.htmlspecialchars((string) $entrypoint['route_id'], ENT_QUOTES | ENT_XML1).'">'."\n";
        $xml .= '          <condition field="destination_number" expression="^'.preg_quote((string) $entrypoint['entrypoint'], '/').'">'."\n";
        $xml .= '            <action application="set" data="nizam_entrypoint_route_type=flow"/>'."\n";
        $xml .= '            <action application="set" data="nizam_entrypoint_route_id='.htmlspecialchars((string) $entrypoint['route_id'], ENT_QUOTES | ENT_XML1).'"/>'."\n";
        $xml .= '            <action application="transfer" data="'.htmlspecialchars((string) $entrypoint['entrypoint'], ENT_QUOTES | ENT_XML1).' XML '.htmlspecialchars($organization->domain, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        $xml .= '          </condition>'."\n";
        $xml .= '        </extension>'."\n";
        $xml .= $this->dialplanFooter();

        return $xml;
    }

    protected function compileFlowEntrypointTransfer(Organization $organization, Flow $flow): ?string
    {
        $entrypoint = $this->resolveFlowEntrypoint($organization, $flow);

        if (! $entrypoint) {
            return null;
        }

        return '            <action application="set" data="nizam_entrypoint_route_type=flow"/>'."\n"
            .'            <action application="set" data="nizam_entrypoint_route_id='.htmlspecialchars((string) $flow->id, ENT_QUOTES | ENT_XML1).'"/>'."\n"
            .'            <action application="transfer" data="'.htmlspecialchars($entrypoint['entrypoint'], ENT_QUOTES | ENT_XML1).' XML '.htmlspecialchars($organization->domain, ENT_QUOTES | ENT_XML1).'"/>'."\n";
    }

    /**
     * @return array{entrypoint:string, route_type:string, route_id:string, route_name:string|null, metadata:array<string,mixed>}|null
     */
    protected function resolveFlowEntrypoint(Organization $organization, Flow $flow): ?array
    {
        $flow->loadMissing(['activeVersion.routingGraphArtifact']);

        if (! $flow->activeVersion) {
            return null;
        }

        $artifact = $flow->activeVersion->routingGraphArtifact;

        if (! $artifact) {
            $artifact = $this->routingGraphCompiler->store($flow->activeVersion);
        }

        $graph = $artifact->decodedContent() ?? [];
        $entryExtension = (string) data_get($graph, 'entrypoint.extension', '');

        if ($entryExtension === '') {
            return null;
        }

        return [
            'entrypoint' => $entryExtension,
            'route_type' => 'flow',
            'route_id' => (string) $flow->id,
            'route_name' => $flow->name,
            'metadata' => [
                'artifact_id' => $artifact->id,
                'artifact_checksum' => $artifact->checksum,
                'flow_version_id' => $flow->activeVersion->id,
                'entry_node_id' => data_get($graph, 'entrypoint.node_id'),
            ],
        ];
    }

    protected function compileDeliveryEntrypointDialplan(Organization $organization): string
    {
        $xml = $this->dialplanHeader($organization->domain);
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

    public function compileConvenienceExtensions(Organization $organization): string
    {
        $xml = '';

        foreach ($this->convenienceServiceCodeMap($organization) as $route) {
            $xml .= $this->compileConvenienceRouteExtension($organization, $route);
        }

        return $xml;
    }

    protected function compileConvenienceDialplan(Organization $organization): string
    {
        $xml = $this->dialplanHeader($organization->domain);
        $xml .= $this->compileConvenienceExtensions($organization);
        $xml .= $this->dialplanFooter();

        return $xml;
    }

    protected function matchesConvenienceServiceCode(Organization $organization, string $destinationNumber): bool
    {
        foreach ($this->convenienceServiceCodeMap($organization) as $route) {
            $code = (string) ($route['code'] ?? '');
            $action = (string) ($route['action'] ?? '');

            if ($code === '') {
                continue;
            }

            if ($destinationNumber === $code) {
                return true;
            }

            if (in_array($action, ['pickup_direct', 'intercom_prefix', 'paging_prefix'], true)
                && str_starts_with($destinationNumber, $code)
                && strlen($destinationNumber) > strlen($code)) {
                return true;
            }

            if ($action === 'park_auto') {
                $start = (int) config('telephony.bootstrap.parking.orbit_start', 5901);
                $end = (int) config('telephony.bootstrap.parking.orbit_end', 5999);
                if (preg_match('/^'.$this->parkingOrbitRegex($start, $end).'$/', $destinationNumber) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<int, array{name:string, code:string, action:string, target_extension:?string}>
     */
    protected function convenienceServiceCodeMap(Organization $organization): array
    {
        $codes = config('telephony.bootstrap.service_codes', []);
        $operatorTarget = $this->resolveOperatorTargetExtension($organization);
        $voicemailTarget = $this->resolveVoicemailMainExtension($organization);
        $sendToVoicemailTarget = $this->resolveSendToVoicemailExtension($organization);

        $routes = [];

        foreach ([
            'voicemail_main' => [
                'name' => 'voicemail-main',
                'action' => 'voicemail_main',
                'target_extension' => $voicemailTarget,
            ],
            'send_to_voicemail_prefix' => [
                'name' => 'send-to-voicemail',
                'action' => 'send_to_voicemail',
                'target_extension' => $sendToVoicemailTarget,
            ],
            'dnd_on' => [
                'name' => 'dnd-on',
                'action' => 'dnd_on',
                'target_extension' => null,
            ],
            'dnd_off' => [
                'name' => 'dnd-off',
                'action' => 'dnd_off',
                'target_extension' => null,
            ],
            'call_return' => [
                'name' => 'call-return',
                'action' => 'call_return',
                'target_extension' => null,
            ],
            'pickup_direct_prefix' => [
                'name' => 'pickup-direct',
                'action' => 'pickup_direct',
                'target_extension' => null,
            ],
            'pickup_group' => [
                'name' => 'pickup-group',
                'action' => 'pickup_group',
                'target_extension' => null,
            ],
            'intercom_prefix' => [
                'name' => 'intercom-prefix',
                'action' => 'intercom_prefix',
                'target_extension' => null,
            ],
            'paging_prefix' => [
                'name' => 'paging-prefix',
                'action' => 'paging_prefix',
                'target_extension' => null,
            ],
            'park_auto' => [
                'name' => 'park-auto',
                'action' => 'park_auto',
                'target_extension' => null,
            ],
            'operator' => [
                'name' => 'operator-shortcut',
                'action' => 'operator',
                'target_extension' => $operatorTarget,
            ],
        ] as $configKey => $route) {
            $code = (string) ($codes[$configKey] ?? '');

            if ($code === '') {
                continue;
            }

            $routes[] = [
                'name' => $route['name'],
                'code' => $code,
                'action' => $route['action'],
                'target_extension' => $route['target_extension'],
            ];
        }

        return $routes;
    }

    /**
     * @param array{name:string, code:string, action:string, target_extension:?string} $route
     */
    protected function compileConvenienceRouteExtension(Organization $organization, array $route): string
    {
        $name = (string) $route['name'];
        $code = (string) $route['code'];
        $action = (string) $route['action'];
        $targetExtension = $route['target_extension'] ?? null;

        if ($action === 'pickup_direct') {
            return $this->compileDirectedPickupExtension($organization, $name, $code);
        }

        if ($action === 'intercom_prefix') {
            return $this->compileIntercomExtension($organization, $name, $code);
        }

        if ($action === 'paging_prefix') {
            return $this->compilePagingExtension($organization, $name, $code);
        }

        if ($action === 'park_auto') {
            return $this->compileValetParkingExtension($organization, $name, $code);
        }

        $xml = '        <extension name="'.htmlspecialchars($name, ENT_QUOTES | ENT_XML1).'">'."\n";
        $xml .= '          <condition field="destination_number" expression="^'.preg_quote($code, '/').'$">'."\n";
        $xml .= '            <action application="answer"/>'."\n";
        $xml .= '            <action application="set" data="nizam_convenience_route='.htmlspecialchars($action, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        $xml .= '            <action application="set" data="nizam_convenience_code='.htmlspecialchars($code, ENT_QUOTES | ENT_XML1).'"/>'."\n";

        switch ($action) {
            case 'voicemail_main':
                $mailbox = $targetExtension ?: '${caller_id_number}';
                $xml .= '            <action application="voicemail" data="check default '.htmlspecialchars($organization->domain, ENT_QUOTES | ENT_XML1).' '.htmlspecialchars($mailbox, ENT_QUOTES | ENT_XML1).'"/>'."\n";
                break;
            case 'send_to_voicemail':
                $mailbox = $targetExtension ?: '${caller_id_number}';
                $xml .= '            <action application="voicemail" data="default '.htmlspecialchars($organization->domain, ENT_QUOTES | ENT_XML1).' '.htmlspecialchars($mailbox, ENT_QUOTES | ENT_XML1).'"/>'."\n";
                break;
            case 'dnd_on':
                $xml .= '            <action application="set" data="nizam_dnd_enabled=true"/>'."\n";
                $xml .= '            <action application="log" data="INFO DND starter route requested by ${caller_id_number}; persistent DND is not configured yet"/>'."\n";
                $xml .= '            <action application="playback" data="ivr/ivr-that_was_an_invalid_entry.wav"/>'."\n";
                $xml .= '            <action application="respond" data="404"/>'."\n";
                break;
            case 'dnd_off':
                $xml .= '            <action application="set" data="nizam_dnd_enabled=false"/>'."\n";
                $xml .= '            <action application="log" data="INFO DND clear starter route requested by ${caller_id_number}; persistent DND is not configured yet"/>'."\n";
                $xml .= '            <action application="playback" data="ivr/ivr-that_was_an_invalid_entry.wav"/>'."\n";
                $xml .= '            <action application="respond" data="404"/>'."\n";
                break;
            case 'call_return':
                $xml .= '            <action application="log" data="INFO Call return starter route requested by ${caller_id_number}; call return is not configured yet"/>'."\n";
                $xml .= '            <action application="respond" data="404"/>'."\n";
                break;
            case 'pickup_group':
                $xml .= '            <action application="set" data="call_direction=inbound"/>'."\n";
                $xml .= '            <action application="answer"/>'."\n";
                $xml .= '            <action application="lua" data="/usr/local/freeswitch/scripts/custom/_group_pickup.lua inbound"/>'."\n";
                break;
            case 'intercom_prefix':
                $xml .= '            <action application="respond" data="404"/>'."\n";
                break;
            case 'paging_prefix':
                $xml .= '            <action application="respond" data="404"/>'."\n";
                break;
            case 'operator':
                if ($targetExtension !== null && $targetExtension !== '') {
                    $xml .= '            <action application="transfer" data="'.htmlspecialchars($targetExtension, ENT_QUOTES | ENT_XML1).' XML '.htmlspecialchars($organization->domain, ENT_QUOTES | ENT_XML1).'"/>'."\n";
                } else {
                    $xml .= '            <action application="respond" data="404"/>'."\n";
                }
                break;
            default:
                $xml .= '            <action application="respond" data="404"/>'."\n";
                break;
        }

        $xml .= '          </condition>'."\n";
        $xml .= '        </extension>'."\n";

        return $xml;
    }

    protected function compileDirectedPickupExtension(Organization $organization, string $name, string $code): string
    {
        $xml = '        <extension name="'.htmlspecialchars($name, ENT_QUOTES | ENT_XML1).'">'."\n";
        $xml .= '          <condition field="destination_number" expression="^'.preg_quote($code, '/').'(.+)$">'."\n";
        $xml .= '            <action application="set" data="nizam_convenience_route=pickup_direct"/>'."\n";
        $xml .= '            <action application="set" data="nizam_convenience_code='.htmlspecialchars($code, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        $xml .= '            <action application="set" data="call_direction=inbound"/>'."\n";
        $xml .= '            <action application="answer"/>'."\n";
        $xml .= '            <action application="lua" data="/usr/local/freeswitch/scripts/custom/_directed_pickup.lua $1"/>'."\n";
        $xml .= '          </condition>'."\n";
        $xml .= '        </extension>'."\n";

        return $xml;
    }

    protected function compileIntercomExtension(Organization $organization, string $name, string $code): string
    {
        $xml = '        <extension name="'.htmlspecialchars($name, ENT_QUOTES | ENT_XML1).'">'."\n";
        $xml .= '          <condition field="destination_number" expression="^'.preg_quote($code, '/').'(\d{2,7})$">'."\n";
        $xml .= '            <action application="set" data="nizam_convenience_route=intercom_prefix"/>'."\n";
        $xml .= '            <action application="set" data="nizam_convenience_code='.htmlspecialchars($code, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        $xml .= '            <action application="set" data="nizam_intercom_target_extension=$1"/>'."\n";
        $xml .= '            <action application="set" data="nizam_auto_answer_enabled=true"/>'."\n";
        $xml .= '            <action application="set" data="nizam_auto_answer_call_info=answer-after=0"/>'."\n";
        $xml .= '            <action application="set" data="nizam_auto_answer_alert_info=intercom"/>'."\n";
        $xml .= '            <action application="export" data="sip_auto_answer=true"/>'."\n";
        $xml .= '            <action application="export" data="sip_h_Call-Info=answer-after=0"/>'."\n";
        $xml .= '            <action application="export" data="sip_h_Alert-Info=intercom"/>'."\n";
        $xml .= '            <action application="transfer" data="call_delivery_entrypoint XML '.htmlspecialchars($organization->domain, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        $xml .= '          </condition>'."\n";
        $xml .= '        </extension>'."\n";

        return $xml;
    }

    protected function compilePagingExtension(Organization $organization, string $name, string $code): string
    {
        $xml = '        <extension name="'.htmlspecialchars($name, ENT_QUOTES | ENT_XML1).'">'."\n";
        $xml .= '          <condition field="destination_number" expression="^'.preg_quote($code, '/').'(\d{2,7})$">'."\n";
        $xml .= '            <action application="set" data="nizam_convenience_route=paging_prefix"/>'."\n";
        $xml .= '            <action application="set" data="nizam_convenience_code='.htmlspecialchars($code, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        $xml .= '            <action application="set" data="nizam_paging_target_extension=$1"/>'."\n";
        $xml .= '            <action application="set" data="nizam_auto_answer_enabled=true"/>'."\n";
        $xml .= '            <action application="set" data="nizam_auto_answer_call_info=answer-after=0"/>'."\n";
        $xml .= '            <action application="set" data="nizam_auto_answer_alert_info=intercom"/>'."\n";
        $xml .= '            <action application="export" data="sip_auto_answer=true"/>'."\n";
        $xml .= '            <action application="export" data="sip_h_Call-Info=answer-after=0"/>'."\n";
        $xml .= '            <action application="export" data="sip_h_Alert-Info=intercom"/>'."\n";
        $xml .= '            <action application="transfer" data="call_delivery_entrypoint XML '.htmlspecialchars($organization->domain, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        $xml .= '          </condition>'."\n";
        $xml .= '        </extension>'."\n";

        return $xml;
    }

    protected function compileValetParkingExtension(Organization $organization, string $name, string $code): string
    {
        $xml = '        <extension name="'.htmlspecialchars($name, ENT_QUOTES | ENT_XML1).'">'."\n";
        $xml .= '          <condition field="destination_number" expression="^(park\\+)?'.preg_quote($code, '/').'$">'."\n";
        $xml .= '            <action application="set" data="nizam_convenience_route=park_auto"/>'."\n";
        $xml .= '            <action application="set" data="nizam_convenience_code='.htmlspecialchars($code, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        $xml .= $this->compileValetParkingActions($organization, null, true);
        $xml .= '          </condition>'."\n";
        $xml .= '        </extension>'."\n";

        $start = (int) config('telephony.bootstrap.parking.orbit_start', 5901);
        $end = (int) config('telephony.bootstrap.parking.orbit_end', 5999);

        $xml .= '        <extension name="'.htmlspecialchars($name.'-orbit', ENT_QUOTES | ENT_XML1).'">'."\n";
        $xml .= '          <condition field="destination_number" expression="^(?:park\\+)?('.$this->parkingOrbitRegex($start, $end).')$">'."\n";
        $xml .= '            <action application="answer"/>'."\n";
        $xml .= '            <action application="valet_park" data="'.htmlspecialchars($code, ENT_QUOTES | ENT_XML1).'@${context} $1"/>'."\n";
        $xml .= '          </condition>'."\n";
        $xml .= '        </extension>'."\n";

        return $xml;
    }

    protected function compileValetParkingActions(Organization $organization, ?string $orbitExtension = null, bool $includeMetadata = false): string
    {
        $parkCode = (string) config('telephony.bootstrap.service_codes.park_auto', '*5900');
        $timeout = (int) config('telephony.bootstrap.parking.timeout', 900);
        $orbitStart = (int) config('telephony.bootstrap.parking.orbit_start', 5901);
        $orbitEnd = (int) config('telephony.bootstrap.parking.orbit_end', 5999);
        $lot = (string) config('telephony.bootstrap.parking.lot', 'park');
        $resolvedOrbit = $orbitExtension ?? $parkCode;

        $xml = '';
        if (! $includeMetadata) {
            $xml .= '            <action application="answer"/>'."\n";
        }
        $xml .= '            <action application="set" data="valet_hold_music=${hold_music}"/>'."\n";
        $xml .= '            <action application="set" data="valet_parking_orbit_exten=${referred_by_user}"/>'."\n";
        $xml .= '            <action application="set" data="valet_parking_timeout='.$timeout.'"/>'."\n";
        $xml .= '            <action application="set" data="valet_parking_direction=in"/>'."\n";
        $xml .= '            <action application="set" data="valet_parking_display=enable"/>'."\n";
        $xml .= '            <action application="set" data="nizam_parking_lot='.htmlspecialchars($lot, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        $xml .= '            <action application="lua" data="/usr/local/freeswitch/scripts/custom/_valet_park.lua '.htmlspecialchars($lot, ENT_QUOTES | ENT_XML1).' '.htmlspecialchars($resolvedOrbit, ENT_QUOTES | ENT_XML1).' '.$orbitStart.' '.$orbitEnd.'"/>'."\n";

        return $xml;
    }

    protected function parkingOrbitRegex(int $start, int $end): string
    {
        if ($start === 5901 && $end === 5999) {
            return '59(0[1-9]|[1-9][0-9])';
        }

        return '(?:'.implode('|', range($start, $end)).')';
    }

    protected function resolveVoicemailMainExtension(Organization $organization): ?string
    {
        $configuredExtension = data_get($organization->settings, 'business_phone.voicemail.main_extension');

        if (is_string($configuredExtension) && $configuredExtension !== '') {
            return $this->resolveActiveExtensionNumber($organization, $configuredExtension);
        }

        return null;
    }

    protected function resolveOperatorTargetExtension(Organization $organization): ?string
    {
        $configuredExtension = data_get($organization->settings, 'business_phone.operator.extension')
            ?? data_get($organization->settings, 'business_phone.default_entrypoint.operator_extension');

        if (is_string($configuredExtension) && $configuredExtension !== '') {
            $resolved = $this->resolveActiveExtensionNumber($organization, $configuredExtension);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        $primaryExtension = $organization->extensions()
            ->where('is_active', true)
            ->orderByDesc('is_primary')
            ->orderBy('extension')
            ->first();

        return $primaryExtension?->extension;
    }

    protected function resolveActiveExtensionNumber(Organization $organization, string $extensionNumber): ?string
    {
        $extension = $organization->extensions()
            ->where('extension', $extensionNumber)
            ->where('is_active', true)
            ->first();

        return $extension?->extension;
    }

    protected function resolveSendToVoicemailExtension(Organization $organization): ?string
    {
        return $this->resolveVoicemailMainExtension($organization)
            ?? $this->resolveOperatorTargetExtension($organization);
    }

    public function compileLocalExtension(Organization $organization, Extension $extension): string
    {
        $xml = '        <extension name="local-'.htmlspecialchars($extension->extension, ENT_QUOTES | ENT_XML1).'">'."\n";
        $xml .= '          <condition field="destination_number" expression="^'.preg_quote($extension->extension, '/').'$">'."\n";
        $xml .= $this->compileSecurityChecks($organization, $extension->extension);
        $xml .= $this->compileConcurrentCallLimit($organization);
        $xml .= $this->compileExtensionRoutingActions($organization, $extension);
        $xml .= '          </condition>'."\n";
        $xml .= '        </extension>'."\n";

        return $xml;
    }

    protected function compileExtensionRoutingActions(Organization $organization, Extension $extension): string
    {
        return $this->compileExtensionDestinationAction($organization, $extension);
    }

    protected function compileExtensionDestinationAction(Organization $organization, Extension $extension, bool $antiAction = false): string
    {
        $action = $antiAction ? 'anti-action' : 'action';

        if ((bool) $extension->dnd_enabled) {
            return '            <'.$action.' application="respond" data="486"/>'."\n";
        }

        $xml = '';
        $followMeDestination = $this->resolveExtensionFollowMeDestination($extension);

        if ($followMeDestination !== null) {
            $xml .= '            <'.$action.' application="set" data="call_timeout='.(int) $this->extensionRoutingRingTimeout($extension).'"/>'."\n";
            $xml .= '            <'.$action.' application="set" data="delivery_pstn_delay_seconds='.(int) $this->extensionRoutingPstnDelaySeconds($extension).'"/>'."\n";
        }

        $xml .= $this->compileHumanTargetHandoffAction($organization, 'extension', (string) $extension->id, $antiAction);

        return $xml;
    }

    protected function resolveExtensionFollowMeDestination(Extension $extension): ?string
    {
        if (! (bool) $extension->follow_me_enabled) {
            return null;
        }

        $destination = trim((string) $extension->follow_me_destination);

        if ($destination === '') {
            return null;
        }

        $normalized = DidNormalizationService::toE164($destination);

        if (! DidNormalizationService::isE164($normalized)) {
            return null;
        }

        $bindingExists = EndpointBinding::query()
            ->where('organization_id', $extension->organization_id)
            ->where('extension_id', $extension->id)
            ->where('type', EndpointBinding::TYPE_PSTN_FORWARD)
            ->where('is_enabled', true)
            ->where('forward_number', $normalized)
            ->exists();

        return $bindingExists ? $normalized : null;
    }

    protected function extensionRoutingPstnDelaySeconds(Extension $extension): int
    {
        return max(0, (int) ($extension->call_timeout ?? 25));
    }

    protected function extensionRoutingRingTimeout(Extension $extension): int
    {
        return max(1, (int) ($extension->call_timeout ?? 25));
    }

    protected function compileSelfCallDialplan(Organization $organization, Extension $extension): string
    {
        $xml = $this->dialplanHeader($organization->domain);
        $xml .= '        <extension name="self-call-voicemail-'.htmlspecialchars($extension->extension, ENT_QUOTES | ENT_XML1).'">'."\n";
        $xml .= '          <condition field="destination_number" expression="^'.preg_quote($extension->extension, '/').'$">'."\n";
        $xml .= '            <action application="answer"/>'."\n";
        $xml .= '            <action application="sleep" data="1000"/>'."\n";
        $xml .= '            <action application="voicemail" data="check default '.htmlspecialchars($organization->domain, ENT_QUOTES | ENT_XML1).' '.htmlspecialchars($extension->extension, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        $xml .= '          </condition>'."\n";
        $xml .= '        </extension>'."\n";
        $xml .= $this->dialplanFooter();

        return $xml;
    }

    protected function compileExtensionDialplan(Organization $organization, Extension $extension): string
    {
        $xml = $this->dialplanHeader($organization->domain);
        $xml .= $this->compileLocalExtension($organization, $extension);
        $xml .= $this->dialplanFooter();

        return $xml;
    }

    protected function compileRingGroupActions(Organization $organization, RingGroup $ringGroup): string
    {
        $memberIds = $ringGroup->members ?? [];
        $extensions = $organization->extensions()->whereIn('id', $memberIds)->where('is_active', true)->get();
        $fallback = null;

        if ($ringGroup->fallback_destination_type && $ringGroup->fallback_destination_id) {
            $fallback = $this->compileDestinationAction($organization, $ringGroup->fallback_destination_type, $ringGroup->fallback_destination_id);
        }

        if ($extensions->isEmpty()) {
            return $fallback ?? '';
        }

        $xml = '            <action application="set" data="call_timeout='.(int) $ringGroup->ring_timeout.'"/>'."\n";
        $xml .= $this->compileHumanTargetHandoffAction($organization, 'ring_group', (string) $ringGroup->id);

        if ($fallback) {
            $xml .= '            <condition field="${originate_disposition}" expression="^(USER_BUSY|NO_ANSWER|NO_USER_RESPONSE|ALLOTTED_TIMEOUT|NO_ROUTE_DESTINATION|UNALLOCATED_NUMBER|SUBSCRIBER_ABSENT)$">'."\n";
            $xml .= $fallback;
            $xml .= '            </condition>'."\n";
        }

        return $xml;
    }

    protected function compileQueueActions(Organization $organization, Queue $queue): string
    {
        $hasEligibleMembers = $queue->members()
            ->where('agents.is_active', true)
            ->exists();

        if (! $hasEligibleMembers) {
            return '';
        }

        return $this->compileHumanTargetHandoffAction($organization, 'queue', (string) $queue->id);
    }

    protected function compileAgentActions(Organization $organization, Agent $agent): string
    {
        if (! $agent->is_active) {
            return '';
        }

        return $this->compileHumanTargetHandoffAction($organization, 'agent', (string) $agent->id);
    }

    protected function compileTimeConditionActions(Organization $organization, TimeCondition $timeCondition): string
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
                $xml .= $this->compileDestinationAction($organization, $timeCondition->match_destination_type, $timeCondition->match_destination_id);
            }

            // No-match destination — <anti-action>
            if ($timeCondition->no_match_destination_type && $timeCondition->no_match_destination_id) {
                $xml .= $this->compileAntiAction($organization, $timeCondition->no_match_destination_type, $timeCondition->no_match_destination_id);
            }
        } else {
            // No time attributes — route to match destination unconditionally
            if ($timeCondition->match_destination_type && $timeCondition->match_destination_id) {
                $xml .= $this->compileDestinationAction($organization, $timeCondition->match_destination_type, $timeCondition->match_destination_id);
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
    protected function compileHumanTargetHandoffAction(Organization $organization, string $targetType, string $targetId, bool $antiAction = false): string
    {
        $action = $antiAction ? 'anti-action' : 'action';
        $entrypoint = 'call_delivery_entrypoint';

        $xml = '            <'.$action.' application="set" data="nizam_delivery_target_type='.htmlspecialchars($targetType, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        $xml .= '            <'.$action.' application="set" data="nizam_delivery_target_id='.htmlspecialchars($targetId, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        $xml .= '            <'.$action.' application="set" data="nizam_call_uuid=${uuid}"/>'."\n";
        $xml .= '            <'.$action.' application="transfer" data="'.$entrypoint.' XML '.htmlspecialchars($organization->domain, ENT_QUOTES | ENT_XML1).'"/>'."\n";

        return $xml;
    }

    protected function compileAntiAction(Organization $organization, string $type, string $id): string
    {
        switch ($type) {
            case 'extension':
                $ext = $organization->extensions()->find($id);
                if ($ext) {
                    return $this->compileExtensionDestinationAction($organization, $ext, true);
                }
                break;
            case 'agent':
                $agent = $organization->agents()->find($id);
                if ($agent) {
                    return $this->compileHumanTargetHandoffAction($organization, 'agent', (string) $agent->id, true);
                }
                break;
            case 'voicemail':
                $ext = $organization->extensions()->find($id);
                if ($ext) {
                    return '            <anti-action application="voicemail" data="default '.htmlspecialchars($organization->domain, ENT_QUOTES | ENT_XML1).' '.htmlspecialchars($ext->extension, ENT_QUOTES | ENT_XML1).'"/>'."\n";
                }
                break;
            case 'ring_group':
                $rg = $organization->ringGroups()->find($id);
                if ($rg) {
                    return $this->compileHumanTargetHandoffAction($organization, 'ring_group', (string) $rg->id, true);
                }
                break;
            case 'queue':
                $queue = $organization->queues()->find($id);
                if ($queue) {
                    return $this->compileHumanTargetHandoffAction($organization, 'queue', (string) $queue->id, true);
                }
                break;
            case 'ivr':
                $ivr = $organization->ivrs()->find($id);
                if ($ivr) {
                    return '            <anti-action application="ivr" data="'.htmlspecialchars($ivr->name, ENT_QUOTES | ENT_XML1).'"/>'."\n";
                }
                break;
            case 'flow':
                $flow = $organization->flows()->find($id);
                if ($flow) {
                    return '            <anti-action application="transfer" data="flow_'.htmlspecialchars($flow->id, ENT_QUOTES | ENT_XML1).' XML '.htmlspecialchars($organization->domain, ENT_QUOTES | ENT_XML1).'"/>'."\n";
                }
                break;
            case 'bridge':
                $bridge = $organization->bridges()->where('is_active', true)->find($id);
                if ($bridge) {
                    return $this->bridgeCompiler->compileAction($organization, $bridge, true, $this->currentEndpointType);
                }
                break;
        }

        return '';
    }

    protected function compileDestinationAction(Organization $organization, string $type, string $id): string
    {
        switch ($type) {
            case 'extension':
                $ext = $organization->extensions()->find($id);
                if ($ext) {
                    return $this->compileExtensionDestinationAction($organization, $ext);
                }
                break;
            case 'agent':
                $agent = $organization->agents()->find($id);
                if ($agent) {
                    return $this->compileHumanTargetHandoffAction($organization, 'agent', (string) $agent->id);
                }
                break;
            case 'voicemail':
                $ext = $organization->extensions()->find($id);
                if ($ext) {
                    return '            <action application="voicemail" data="default '.htmlspecialchars($organization->domain, ENT_QUOTES | ENT_XML1).' '.htmlspecialchars($ext->extension, ENT_QUOTES | ENT_XML1).'"/>'."\n";
                }
                break;
            case 'ring_group':
                $rg = $organization->ringGroups()->find($id);
                if ($rg) {
                    return $this->compileHumanTargetHandoffAction($organization, 'ring_group', (string) $rg->id);
                }
                break;
            case 'queue':
                $queue = $organization->queues()->find($id);
                if ($queue) {
                    return $this->compileHumanTargetHandoffAction($organization, 'queue', (string) $queue->id);
                }
                break;
            case 'ivr':
                $ivr = $organization->ivrs()->find($id);
                if ($ivr) {
                    return '            <action application="ivr" data="'.htmlspecialchars($ivr->name, ENT_QUOTES | ENT_XML1).'"/>'."\n";
                }
                break;
            case 'flow':
                $flow = $organization->flows()->find($id);
                if ($flow) {
                    return '            <action application="transfer" data="flow_'.htmlspecialchars($flow->id, ENT_QUOTES | ENT_XML1).' XML '.htmlspecialchars($organization->domain, ENT_QUOTES | ENT_XML1).'"/>'."\n";
                }
                break;
            case 'bridge':
                $bridge = $organization->bridges()->where('is_active', true)->find($id);
                if ($bridge) {
                    return $this->bridgeCompiler->compileAction($organization, $bridge, false, $this->currentEndpointType);
                }
                break;
        }

        return '';
    }

    /**
     * Compile policy-based routing using time conditions derived from policy conditions.
     */
    protected function compilePolicyRouting(Organization $organization, CallRoutingPolicy $policy): string
    {
        $conditions = $policy->conditions ?? [];
        $xml = '';

        $attrs = $this->buildPolicyConditionAttributes($conditions);

        if ($attrs) {
            $xml .= '          </condition>'."\n";
            $xml .= '          <condition'.$attrs.'>'."\n";

            if ($policy->match_destination_type && $policy->match_destination_id) {
                $xml .= $this->compileDestinationAction($organization, $policy->match_destination_type, $policy->match_destination_id);
            }

            if ($policy->no_match_destination_type && $policy->no_match_destination_id) {
                $xml .= $this->compileAntiAction($organization, $policy->no_match_destination_type, $policy->no_match_destination_id);
            }
        } else {
            if ($policy->match_destination_type && $policy->match_destination_id) {
                $xml .= $this->compileDestinationAction($organization, $policy->match_destination_type, $policy->match_destination_id);
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
        // FusionPBX parity: The context name must match the domain requested
        // to prevent falling through to the FreeSWITCH stock 'public' or 'default' contexts.
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
