<?php

namespace App\Services\Flow;

use App\Models\FlowCompiledArtifact;
use App\Models\FlowVersion;
use App\Models\OrganizationDialplanManifest;
use App\Services\Flow\Compile\FlowToIrCompiler;
use App\Services\Routing\RoutingGraphCompiler;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FlowArtifactService
{
    public function __construct(
        protected FlowToIrCompiler $flowToIrCompiler,
        protected RoutingGraphCompiler $routingGraphCompiler,
    ) {}

    public function getRoutingGraphCompiler(): RoutingGraphCompiler
    {
        return $this->routingGraphCompiler;
    }

    public function getFlowToIrCompiler(): FlowToIrCompiler
    {
        return $this->flowToIrCompiler;
    }

    /**
     * Compile and store flow artifacts for a flow version.
     *
     * This generates the compiled dialplan XML and stores it in the database.
     * The compiled artifacts are then used by the xml_curl controller to serve
     * pre-compiled dialplan instead of interpreting at runtime.
     */
    public function compileAndStore(FlowVersion $flowVersion): array
    {
        // Validate that all node types have compilers registered
        if (!$this->flowToIrCompiler->canCompile($flowVersion)) {
            return [
                'success' => false,
                'error' => 'Flow contains node types without registered compilers',
            ];
        }

        try {
            $routingGraphArtifact = $this->routingGraphCompiler->store($flowVersion);
        } catch (InvalidArgumentException $exception) {
            return [
                'success' => false,
                'error' => $exception->getMessage(),
            ];
        }

        // Generate IR instructions
        $irInstructions = $this->flowToIrCompiler->compile($flowVersion);

        // Generate dialplan XML from IR instructions
        $dialplanXml = $this->generateDialplanXml($flowVersion, $irInstructions);

        // Store the compiled artifact
        $artifact = FlowCompiledArtifact::updateOrCreate(
            [
                'flow_version_id' => $flowVersion->id,
                'artifact_type' => FlowCompiledArtifact::ARTIFACT_TYPE_DIALPLAN_XML,
            ],
            [
                'organization_id' => $flowVersion->flow->organization_id,
                'content' => $dialplanXml,
                'checksum' => md5($dialplanXml),
            ]
        );

        return [
            'success' => true,
            'artifact_id' => $artifact->id,
            'checksum' => $artifact->checksum,
            'routing_graph_artifact_id' => $routingGraphArtifact->id,
            'routing_graph_checksum' => $routingGraphArtifact->checksum,
        ];
    }

    /**
     * Generate dialplan XML from IR instructions.
     *
     * This method converts the internal IR representation into FreeSWITCH
     * dialplan XML that can be served via xml_curl.
     */
    protected function generateDialplanXml(FlowVersion $flowVersion, array $irInstructions): string
    {
        $context = $flowVersion->flow->organization->domain;

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>'."\n";
        $xml .= '<document type="freeswitch/xml">'."\n";
        $xml .= '  <section name="dialplan">'."\n";
        $xml .= '    <context name="'.$context.'">'."\n";

        $startNodeId = null;
        foreach ($irInstructions as $instruction) {
            if ($instruction->type === 'AnswerAndTransfer') {
                $startNodeId = $instruction->params['node_id'] ?? null;
                break;
            }
        }

        // Generate entry extension for this flow
        $xml .= '      <extension name="flow_'.$flowVersion->flow->id.'">'."\n";
        $xml .= '        <condition field="destination_number" expression="^flow_'.$flowVersion->flow->id.'$">'."\n";
        if ($startNodeId) {
            $xml .= '          <action application="transfer" data="node_'.$startNodeId.' XML '.$context.'"/>'."\n";
        } else {
            $xml .= '          <action application="hangup"/>'."\n";
        }
        $xml .= '        </condition>'."\n";
        $xml .= '      </extension>'."\n\n";

        // Generate dialplan extensions for each IR instruction
        foreach ($irInstructions as $instruction) {
            $xml .= $this->generateInstructionXml($flowVersion, $instruction);
        }

        $xml .= '    </context>'."\n";
        $xml .= '  </section>'."\n";
        $xml .= '</document>';

        return $xml;
    }

    /**
     * Generate XML for a single IR instruction.
     */
    protected function generateInstructionXml(FlowVersion $flowVersion, object $instruction): string
    {
        $nodeId = $instruction->params['node_id'] ?? 'unknown';
        $context = $flowVersion->flow->organization->domain;
        $xml = '      <extension name="node_'.$nodeId.'">'."\n";
        $xml .= '        <condition field="destination_number" expression="^node_'.$nodeId.'$">'."\n";

        // Generate condition based on instruction type
        switch ($instruction->type) {
            case 'AnswerAndTransfer':
                $nextNodeLabel = $instruction->transitions['next'] ?? null;
                if ($nextNodeLabel) {
                    $xml .= '          <action application="transfer" data="'.$nextNodeLabel.' XML '.$context.'"/>'."\n";
                } else {
                    $xml .= '          <action application="hangup"/>'."\n";
                }
                break;

            case 'CheckSchedule':
                $scheduleId = $instruction->params['schedule_id'] ?? null;
                if ($scheduleId) {
                    // Set return node and state
                    $xml .= '          <action application="set" data="nizam_schedule_state="/>'."\n";
                    $xml .= '          <action application="set" data="nizam_schedule_return_node=node_'.$nodeId.'_resume"/>'."\n";
                    // Transfer to schedule XML
                    $xml .= '          <action application="transfer" data="schedule_'.$scheduleId.' XML '.$context.'"/>'."\n";
                    
                    // Resume extension
                    $xml .= '        </condition>'."\n";
                    $xml .= '      </extension>'."\n\n";
                    
                    $xml .= '      <extension name="node_'.$nodeId.'_resume">'."\n";
                    $xml .= '        <condition field="destination_number" expression="^node_'.$nodeId.'_resume$">'."\n";
                    $xml .= '          <action application="log" data="INFO Schedule state is ${nizam_schedule_state}"/>'."\n";
                    
                    // Route based on state
                    foreach (['holiday', 'exception_open', 'exception_closed', 'break', 'open', 'closed'] as $state) {
                        $target = $instruction->transitions[$state] ?? ($instruction->transitions['closed'] ?? null);
                        if ($target) {
                            $xml .= '          <condition field="${nizam_schedule_state}" expression="^'.$state.'$">'."\n";
                            $xml .= '            <action application="transfer" data="'.$target.' XML '.$context.'"/>'."\n";
                            $xml .= '          </condition>'."\n";
                        }
                    }
                    
                    // Fallback
                    $fallback = $instruction->transitions['closed'] ?? null;
                    if ($fallback) {
                        $xml .= '          <action application="transfer" data="'.$fallback.' XML '.$context.'"/>'."\n";
                    } else {
                        $xml .= '          <action application="hangup"/>'."\n";
                    }
                } else {
                    $fallback = $instruction->transitions['open'] ?? null;
                    if ($fallback) {
                        $xml .= '          <action application="transfer" data="'.$fallback.' XML '.$context.'"/>'."\n";
                    } else {
                        $xml .= '          <action application="hangup"/>'."\n";
                    }
                }
                break;

            case 'CollectDigits':
                // Menu digit collection
                $config = $instruction->params['config'] ?? [];
                $minDigits = $config['min_digits'] ?? 1;
                $maxDigits = $config['max_digits'] ?? 1;
                $maxTries = $config['max_tries'] ?? 3;
                $timeout = $config['timeout'] ?? 5000;
                $terminators = $config['terminators'] ?? '#';
                $prompt = $config['prompt'] ?? 'silence_stream://1000';
                
                $xml .= '          <action application="play_and_get_digits" data="'.$minDigits.' '.$maxDigits.' '.$maxTries.' '.$timeout.' '.$terminators.' '.$prompt.' silence_stream://250 nizam_menu_digits \d+"/>'."\n";
                
                foreach ($instruction->transitions as $result => $targetLabel) {
                    if (str_starts_with($result, 'digit_')) {
                        $digit = str_replace('digit_', '', $result);
                        $xml .= '          <condition field="${nizam_menu_digits}" expression="^'.$digit.'$">'."\n";
                        $xml .= '            <action application="transfer" data="'.$targetLabel.' XML '.$context.'"/>'."\n";
                        $xml .= '          </condition>'."\n";
                    }
                }
                
                $timeoutTarget = $instruction->transitions['timeout'] ?? null;
                if ($timeoutTarget) {
                    $xml .= '          <condition field="${nizam_menu_digits}" expression="^$">'."\n";
                    $xml .= '            <action application="transfer" data="'.$timeoutTarget.' XML '.$context.'"/>'."\n";
                    $xml .= '          </condition>'."\n";
                }
                
                $invalidTarget = $instruction->transitions['invalid'] ?? null;
                if ($invalidTarget) {
                    $xml .= '          <action application="transfer" data="'.$invalidTarget.' XML '.$context.'"/>'."\n";
                } elseif ($timeoutTarget) {
                    $xml .= '          <action application="transfer" data="'.$timeoutTarget.' XML '.$context.'"/>'."\n";
                } else {
                    $xml .= '          <action application="hangup"/>'."\n";
                }
                break;

            case 'PlayMessage':
                $config = $instruction->params['config'] ?? [];
                $prompt = $config['prompt'] ?? $config['message'] ?? 'silence_stream://250';
                $xml .= '          <action application="playback" data="'.$prompt.'"/>'."\n";

                $nextTarget = $instruction->transitions['next'] ?? null;
                if ($nextTarget) {
                    $xml .= '          <action application="transfer" data="'.$nextTarget.' XML '.$context.'"/>'."\n";
                } else {
                    $xml .= '          <action application="hangup"/>'."\n";
                }
                break;

            case 'BridgeTeam':
                // Team routing with Lua helper
                $teamId = $instruction->params['team_id'] ?? null;
                $timeout = $instruction->params['timeout'] ?? 30;
                
                $answeredTarget = $instruction->transitions['answered'] ?? 'null';
                $noAnswerTarget = $instruction->transitions['no_answer'] ?? 'null';
                $timeoutTarget = $instruction->transitions['timeout'] ?? $noAnswerTarget;
                
                $organization = $flowVersion->flow->organization;
                $ringGroup = $organization->ringGroups()->find($teamId);
                
                $dialString = '';
                if ($ringGroup) {
                    $memberIds = $ringGroup->members ?? [];
                    $extensions = $organization->extensions()->whereIn('id', $memberIds)->where('is_active', true)->get();
                    if ($extensions->isNotEmpty()) {
                        $strings = $extensions->map(fn ($ext) => 'user/'.$ext->extension.'@'.$organization->domain);
                        $dialString = $strings->implode($ringGroup->strategy === 'simultaneous' ? ',' : '|');
                    }
                }
                
                if (empty($dialString)) {
                    $xml .= '          <action application="log" data="WARNING BridgeTeam node_'.$nodeId.' resolved to empty dial string"/>'."\n";
                    $xml .= '          <action application="transfer" data="'.$noAnswerTarget.' XML '.$context.'"/>'."\n";
                } else {
                    $xml .= '          <action application="set" data="team_id='.$teamId.'"/>'."\n";
                    $xml .= '          <action application="lua" data="/usr/local/freeswitch/scripts/custom/_team_ring.lua \''.$dialString.'\' '.$timeout.' '.$answeredTarget.' '.$noAnswerTarget.' '.$timeoutTarget.'"/>'."\n";
                }
                break;

            case 'Voicemail':
                // Voicemail handling
                $config = $instruction->params['config'] ?? [];
                $extension = $config['extension'] ?? '${destination_number}';
                $xml .= '          <action application="voicemail" data="default ${domain_name} '.$extension.'"/>'."\n";
                
                $completedTarget = $instruction->transitions['completed'] ?? null;
                if ($completedTarget) {
                    $xml .= '          <action application="transfer" data="'.$completedTarget.' XML '.$context.'"/>'."\n";
                } else {
                    $xml .= '          <action application="hangup"/>'."\n";
                }
                break;

            case 'Hangup':
                $xml .= '          <action application="hangup"/>'."\n";
                break;
        }

        $xml .= '        </condition>'."\n";
        $xml .= '      </extension>'."\n\n";

        return $xml;
    }

    /**
     * Activate a manifest for an organization.
     *
     * This sets the is_active flag on the organization's dialplan manifest,
     * making it the active dialplan served by xml_curl.
     */
    public function activateManifest(OrganizationDialplanManifest $manifest): bool
    {
        // Deactivate any existing active manifest for this organization and type
        OrganizationDialplanManifest::where('organization_id', $manifest->organization_id)
            ->where('manifest_type', $manifest->manifest_type)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        // Activate the new manifest
        return $manifest->update(['is_active' => true]);
    }
}
