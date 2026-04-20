<?php

namespace App\Services;

use App\Models\FlowCompiledArtifact;
use App\Models\Organization;
use App\Models\OrganizationDialplanManifest;
use App\Services\DialplanCompiler;

class OrganizationManifestBuilder
{
    public function __construct(
        protected DialplanCompiler $dialplanCompiler,
    ) {}

    /**
     * Build and activate a complete dialplan manifest for an organization.
     * This includes all DIDs, extensions, ring groups, IVRs, policies,
     * AND compiled flows and schedules.
     */
    public function buildAndActivate(Organization $organization): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>'."\n";
        $xml .= '<document type="freeswitch/xml">'."\n";
        $xml .= '  <section name="dialplan">'."\n";
        $xml .= '    <context name="'.htmlspecialchars($organization->domain, ENT_QUOTES | ENT_XML1).'">'."\n";

        // 1. Add concurrent call limit globally or per-extension (done inside rules)
        
        // 2. Add all DID routing rules
        $dids = $organization->dids()->where('is_active', true)->get();
        foreach ($dids as $did) {
            // We use the existing logic in DialplanCompiler, but refactored to just return the <extension> block
            $xml .= $this->dialplanCompiler->compileDidExtension($organization, $did);
        }

        // 3. Add all local extensions
        $extensions = $organization->extensions()->where('is_active', true)->get();
        foreach ($extensions as $extension) {
            $xml .= $this->dialplanCompiler->compileLocalExtension($organization, $extension);
        }

        // 4. Add compiled convenience and service-code routes
        $xml .= $this->dialplanCompiler->compileConvenienceExtensions($organization);

        // 5. Add compiled Flow artifacts
        $flowArtifacts = FlowCompiledArtifact::where('organization_id', $organization->id)
            ->where('artifact_type', FlowCompiledArtifact::ARTIFACT_TYPE_DIALPLAN_XML)
            ->get();

        foreach ($flowArtifacts as $artifact) {
            // artifact content is already a full document? We should only store the inner <extension>s.
            // But if it's a full document, we need to extract the inside of <context>.
            // Let's assume FlowArtifactService generates JUST the extensions.
            $xml .= $artifact->content."\n";
        }

        // 6. Add Schedules (if we had a ScheduleCompiler that saved artifacts, or we compile them on the fly here)
        $schedules = $organization->schedules()->get();
        $scheduleCompiler = app(\App\Services\Schedule\Compile\ScheduleCompiler::class);
        foreach ($schedules as $schedule) {
            $xml .= $scheduleCompiler->compile($schedule)."\n";
        }
        
        // Add schedule_return global interceptor
        $xml .= '      <extension name="schedule_return">'."\n";
        $xml .= '        <condition field="destination_number" expression="^schedule_\d+_(holiday|exception_.*|break|open|closed)$">'."\n";
        $xml .= '          <action application="transfer" data="${nizam_schedule_return_node} XML '.htmlspecialchars($organization->domain, ENT_QUOTES | ENT_XML1).'"/>'."\n";
        $xml .= '        </condition>'."\n";
        $xml .= '      </extension>'."\n";

        // 6. Fail-safe
        $xml .= '      <extension name="failsafe">'."\n";
        $xml .= '        <condition field="destination_number" expression=".*">'."\n";
        $xml .= '          <action application="log" data="WARNING Fail-safe route triggered for ${destination_number}"/>'."\n";
        $xml .= '          <action application="respond" data="404"/>'."\n";
        $xml .= '        </condition>'."\n";
        $xml .= '      </extension>'."\n";

        $xml .= '    </context>'."\n";
        $xml .= '  </section>'."\n";
        $xml .= '</document>';

        $checksum = md5($xml);

        $manifest = OrganizationDialplanManifest::updateOrCreate(
            [
                'organization_id' => $organization->id,
                'manifest_type' => 'inbound_routing',
            ],
            [
                'content' => $xml,
                'checksum' => $checksum,
            ]
        );

        // Deactivate others
        OrganizationDialplanManifest::where('organization_id', $organization->id)
            ->where('manifest_type', 'inbound_routing')
            ->where('id', '!=', $manifest->id)
            ->update(['is_active' => false]);

        $manifest->update(['is_active' => true]);
    }
}
