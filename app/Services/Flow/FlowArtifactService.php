<?php

namespace App\Services\Flow;

use App\Models\FlowCompiledArtifact;
use App\Models\FlowVersion;
use App\Models\TenantDialplanManifest;
use App\Services\Flow\Compile\FlowToIrCompiler;
use Illuminate\Support\Facades\DB;

class FlowArtifactService
{
    public function __construct(
        protected FlowToIrCompiler $flowToIrCompiler,
    ) {}

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

        // Generate IR instructions
        $irInstructions = $this->flowToIrCompiler->compile($flowVersion);

        // Generate dialplan XML from IR instructions
        $dialplanXml = $this->generateDialplanXml($flowVersion, $irInstructions);

        // Store the compiled artifact
        $artifact = FlowCompiledArtifact::updateOrCreate(
            [
                'flow_version_id' => $flowVersion->id,
                'artifact_type' => 'dialplan_xml',
            ],
            [
                'tenant_id' => $flowVersion->flow->tenant_id,
                'content' => $dialplanXml,
                'checksum' => md5($dialplanXml),
            ]
        );

        return [
            'success' => true,
            'artifact_id' => $artifact->id,
            'checksum' => $artifact->checksum,
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
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>'."\n";
        $xml .= '<document type="freeswitch/xml">'."\n";
        $xml .= '  <section name="dialplan">'."\n";
        $xml .= '    <context name="'.$flowVersion->flow->tenant->domain.'">'."\n";

        // Generate extension for this flow
        $xml .= '      <extension name="flow_'.$flowVersion->flow->id.'">'."\n";
        $xml .= '        <condition field="destination_number" expression="^flow_'.$flowVersion->flow->id.'$">'."\n";

        // Generate dialplan actions from IR instructions
        foreach ($irInstructions as $instruction) {
            $xml .= $this->generateInstructionXml($instruction);
        }

        $xml .= '        </condition>'."\n";
        $xml .= '      </extension>'."\n";

        $xml .= '    </context>'."\n";
        $xml .= '  </section>'."\n";
        $xml .= '</document>';

        return $xml;
    }

    /**
     * Generate XML for a single IR instruction.
     */
    protected function generateInstructionXml(object $instruction): string
    {
        $xml = '';

        // Generate condition based on instruction type
        switch ($instruction->type) {
            case 'AnswerAndTransfer':
                $xml .= '          <action application="answer"/>'."\n";
                if ($instruction->next_node_id) {
                    $xml .= '          <action application="transfer" data="node_'.$instruction->next_node_id.' XML default"/>'."\n";
                }
                break;

            case 'CheckSchedule':
                // Schedule check is handled by ScheduleCompiler
                $scheduleId = $instruction->config['schedule_id'] ?? null;
                if ($scheduleId) {
                    $xml .= '          <action application="set" data="nizam_schedule_state=open"/>'."\n";
                    $xml .= '          <action application="transfer" data="schedule_'.$scheduleId.' XML default"/>'."\n";
                }
                break;

            case 'CollectDigits':
                // Menu digit collection
                $xml .= '          <action application="read" data="1 1 1 # digit 5000"/>'."\n";
                break;

            case 'BridgeTeam':
                // Team routing with Lua helper
                $teamId = $instruction->config['team_id'] ?? null;
                $timeout = $instruction->config['timeout'] ?? 30;
                if ($teamId) {
                    $xml .= '          <action application="lua" data="/usr/local/freeswitch/scripts/nizam_ring_team.lua"/>'."\n";
                }
                break;

            case 'Voicemail':
                // Voicemail handling
                $xml .= '          <action application="voicemail" data="default ${domain} ${destination_number}"/>'."\n";
                break;

            case 'Hangup':
                $xml .= '          <action application="hangup"/>'."\n";
                break;
        }

        return $xml;
    }

    /**
     * Activate a manifest for a tenant.
     *
     * This sets the is_active flag on the tenant's dialplan manifest,
     * making it the active dialplan served by xml_curl.
     */
    public function activateManifest(TenantDialplanManifest $manifest): bool
    {
        // Deactivate any existing active manifest for this tenant and type
        TenantDialplanManifest::where('tenant_id', $manifest->tenant_id)
            ->where('manifest_type', $manifest->manifest_type)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        // Activate the new manifest
        return $manifest->update(['is_active' => true]);
    }
}
