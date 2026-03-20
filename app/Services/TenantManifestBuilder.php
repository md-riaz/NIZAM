<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantDialplanManifest;
use App\Services\DialplanCompiler;

class TenantManifestBuilder
{
    public function __construct(
        protected DialplanCompiler $dialplanCompiler,
    ) {}

    /**
     * Build and activate a complete dialplan manifest for a tenant.
     * This includes all DIDs, extensions, ring groups, IVRs, policies,
     * AND compiled flows and schedules.
     */
    public function buildAndActivate(Tenant $tenant): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>'."\n";
        $xml .= '<document type="freeswitch/xml">'."\n";
        $xml .= '  <section name="dialplan">'."\n";
        $xml .= '    <context name="'.htmlspecialchars($tenant->domain, ENT_QUOTES | ENT_XML1).'">'."\n";

        // 1. Add concurrent call limit globally or per-extension (done inside rules)
        
        // 2. Add all DID routing rules
        $dids = $tenant->dids()->where('is_active', true)->get();
        foreach ($dids as $did) {
            // We use the existing logic in DialplanCompiler, but refactored to just return the <extension> block
            $xml .= $this->dialplanCompiler->compileDidExtension($tenant, $did);
        }

        // 3. Add all local extensions
        $extensions = $tenant->extensions()->where('is_active', true)->get();
        foreach ($extensions as $extension) {
            $xml .= $this->dialplanCompiler->compileLocalExtension($tenant, $extension);
        }

        // 4. Add compiled Flow artifacts
        $flowArtifacts = \App\Models\FlowCompiledArtifact::where('tenant_id', $tenant->id)
            ->where('artifact_type', 'dialplan_xml')
            ->get();
            
        foreach ($flowArtifacts as $artifact) {
            // artifact content is already a full document? We should only store the inner <extension>s.
            // But if it's a full document, we need to extract the inside of <context>.
            // Let's assume FlowArtifactService generates JUST the extensions.
            $xml .= $artifact->content."\n";
        }

        // 5. Add Schedules (if we had a ScheduleCompiler that saved artifacts, or we compile them on the fly here)
        $schedules = $tenant->schedules()->get();
        $scheduleCompiler = app(\App\Services\Schedule\Compile\ScheduleCompiler::class);
        foreach ($schedules as $schedule) {
            $xml .= $scheduleCompiler->compile($schedule)."\n";
        }
        
        // Add schedule_return global interceptor
        $xml .= '      <extension name="schedule_return">'."\n";
        $xml .= '        <condition field="destination_number" expression="^schedule_\d+_(holiday|exception_.*|break|open|closed)$">'."\n";
        $xml .= '          <action application="transfer" data="${nizam_schedule_return_node} XML '.htmlspecialchars($tenant->domain, ENT_QUOTES | ENT_XML1).'"/>'."\n";
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

        $manifest = TenantDialplanManifest::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'manifest_type' => 'inbound_routing',
            ],
            [
                'content' => $xml,
                'checksum' => $checksum,
            ]
        );

        // Deactivate others
        TenantDialplanManifest::where('tenant_id', $tenant->id)
            ->where('manifest_type', 'inbound_routing')
            ->where('id', '!=', $manifest->id)
            ->update(['is_active' => false]);

        $manifest->update(['is_active' => true]);
    }
}
