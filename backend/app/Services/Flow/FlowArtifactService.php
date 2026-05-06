<?php

namespace App\Services\Flow;

use App\Models\Agent;
use App\Models\Extension;
use App\Models\FlowCompiledArtifact;
use App\Models\FlowVersion;
use App\Models\Organization;
use App\Models\OrganizationDialplanManifest;
use App\Models\Team;
use App\Services\Flow\Compile\FlowToIrCompiler;
use App\Services\OrganizationManifestBuilder;
use App\Services\Routing\RoutingGraphCompiler;
use App\Services\Team\TeamRoutingService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FlowArtifactService
{
    public function __construct(
        protected FlowToIrCompiler $flowToIrCompiler,
        protected RoutingGraphCompiler $routingGraphCompiler,
        protected TeamRoutingService $teamRoutingService,
        protected OrganizationManifestBuilder $manifestBuilder,
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
                $destinationType = $instruction->params['destination_type'] ?? null;
                $destinationValue = $instruction->params['destination_value'] ?? null;

                if (! empty($destinationType) && ! empty($destinationValue)) {
                    $xml .= '          <action application="set" data="nizam_destination_type='.$destinationType.'"/>'."\n";
                    $xml .= '          <action application="set" data="nizam_destination_id='.$destinationValue.'"/>'."\n";
                    $xml .= '          <action application="transfer" data="call_delivery_entrypoint XML '.$context.'"/>'."\n";
                    break;
                }

                $timeoutTarget = $instruction->transitions['timeout'] ?? null;
                $invalidTarget = $instruction->transitions['invalid'] ?? null;
                $fallbackTarget = $invalidTarget ?? $timeoutTarget;

                if ($fallbackTarget) {
                    $xml .= '          <action application="transfer" data="'.$fallbackTarget.' XML '.$context.'"/>'."\n";
                } else {
                    $xml .= '          <action application="hangup"/>'."\n";
                }
                break;

            case 'PlayMessage':
                $destinationType = $instruction->params['destination_type'] ?? null;
                $destinationValue = $instruction->params['destination_value'] ?? null;

                if (! empty($destinationType) && ! empty($destinationValue)) {
                    $xml .= '          <action application="set" data="nizam_destination_type='.$destinationType.'"/>'."\n";
                    $xml .= '          <action application="set" data="nizam_destination_id='.$destinationValue.'"/>'."\n";
                    $xml .= '          <action application="transfer" data="call_delivery_entrypoint XML '.$context.'"/>'."\n";
                    break;
                }

                $nextTarget = $instruction->transitions['next'] ?? null;
                if ($nextTarget) {
                    $xml .= '          <action application="transfer" data="'.$nextTarget.' XML '.$context.'"/>'."\n";
                } else {
                    $xml .= '          <action application="hangup"/>'."\n";
                }
                break;


            case 'BridgeTeam':
                $teamId = $instruction->params['team_id'] ?? null;
                $timeout = $instruction->params['timeout'] ?? 30;
                $strategy = $instruction->params['config']['strategy'] ?? null;

                $answeredTarget = $instruction->transitions['answered'] ?? 'null';
                $noAnswerTarget = $instruction->transitions['no_answer'] ?? 'null';
                $timeoutTarget = $instruction->transitions['timeout'] ?? $noAnswerTarget;

                $organization = $flowVersion->flow->organization;
                $team = $teamId
                    ? $organization->teams()->whereKey($teamId)->where('is_active', true)->first()
                    : null;

                $dialString = $team instanceof Team
                    ? $this->teamRoutingService->buildDialString($team, $organization->domain, is_string($strategy) ? $strategy : null)
                    : '';

                if ($dialString === '') {
                    $xml .= '          <action application="log" data="WARNING BridgeTeam node_'.$nodeId.' resolved to empty dial string"/>'."\n";
                    $xml .= '          <action application="transfer" data="'.$noAnswerTarget.' XML '.$context.'"/>'."\n";
                } else {
                    $xml .= '          <action application="set" data="team_id='.$teamId.'"/>'."\n";
                    $xml .= '          <action application="lua" data="/usr/local/freeswitch/scripts/custom/_team_ring.lua \''.$dialString.'\' '.$timeout.' '.$answeredTarget.' '.$noAnswerTarget.' '.$timeoutTarget.'"/>'."\n";
                }
                break;

            case 'Voicemail':
                $destinationType = $instruction->params['destination_type'] ?? null;
                $destinationValue = $instruction->params['destination_value'] ?? null;

                if (! empty($destinationType) && ! empty($destinationValue)) {
                    $xml .= '          <action application="set" data="nizam_destination_type='.$destinationType.'"/>'."\n";
                    $xml .= '          <action application="set" data="nizam_destination_id='.$destinationValue.'"/>'."\n";
                    $xml .= '          <action application="transfer" data="call_delivery_entrypoint XML '.$context.'"/>'."\n";
                    break;
                }

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

    public function refreshTeamRoutingArtifactsForOrganization(Organization $organization, callable $matchesNode): void
    {
        $organization->flows()
            ->whereNotNull('active_version_id')
            ->with(['activeVersion.nodes', 'activeVersion.edges'])
            ->get()
            ->filter(function ($flow) use ($matchesNode) {
                return $flow->activeVersion?->nodes->contains(function ($node) use ($matchesNode) {
                    return $node->type === 'ring_team' && $matchesNode($node);
                });
            })
            ->each(function ($flow) {
                if ($flow->activeVersion) {
                    $this->compileAndStore($flow->activeVersion);
                }
            });

        $this->manifestBuilder->buildAndActivate($organization);
    }

    public function refreshTeamRoutingArtifactsForTeam(Team $team): void
    {
        $organization = $team->organization;

        if (! $organization) {
            return;
        }

        $this->refreshTeamRoutingArtifactsForOrganization($organization, function ($node) use ($team) {
            return (string) data_get($node->config_json, 'team_id') === (string) $team->id;
        });
    }

    public function refreshTeamRoutingArtifactsForExtension(Extension $extension): void
    {
        $organization = $extension->organization;

        if (! $organization) {
            return;
        }

        $teamIds = $organization->teams()
            ->whereHas('members', function ($query) use ($extension) {
                $query->where('endpoint_type', 'extension')
                    ->where('endpoint_id', $extension->id)
                    ->where('is_active', true);
            })
            ->pluck('id')
            ->all();

        if ($teamIds === []) {
            return;
        }

        $this->refreshTeamRoutingArtifactsForOrganization($organization, function ($node) use ($teamIds) {
            return in_array((string) data_get($node->config_json, 'team_id'), $teamIds, true);
        });
    }

    public function refreshTeamRoutingArtifactsForAgent(Agent $agent): void
    {
        $organization = $agent->organization;

        if (! $organization) {
            return;
        }

        $teamIds = $organization->teams()
            ->whereHas('members', function ($query) use ($agent) {
                $query->where('endpoint_type', 'agent')
                    ->where('endpoint_id', $agent->id)
                    ->where('is_active', true);
            })
            ->pluck('id')
            ->all();

        if ($teamIds === []) {
            return;
        }

        $this->refreshTeamRoutingArtifactsForOrganization($organization, function ($node) use ($teamIds) {
            return in_array((string) data_get($node->config_json, 'team_id'), $teamIds, true);
        });
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
