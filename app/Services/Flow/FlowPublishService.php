<?php

namespace App\Services\Flow;

use App\Models\FlowVersion;
use App\Models\TenantDialplanManifest;
use Illuminate\Support\Facades\DB;

class FlowPublishService
{
    public function __construct(
        protected FlowArtifactService $artifactService,
    ) {}

    /**
     * Publish a flow version.
     *
     * This method:
     * 1. Compiles the flow into IR and generates dialplan XML
     * 2. Stores the compiled artifact
     * 3. Creates/updates the tenant's dialplan manifest
     * 4. Activates the manifest for the tenant
     *
     * If compilation fails, the publish fails and no changes are made.
     */
    public function publish(FlowVersion $flowVersion): array
    {
        // Validate flow version is ready for publishing
        if ($flowVersion->status !== 'draft' && $flowVersion->status !== 'published') {
            return [
                'success' => false,
                'error' => 'Flow version is not in a publishable state',
            ];
        }

        // Validate all node types have compilers registered
        if (!$this->artifactService->getFlowToIrCompiler()->canCompile($flowVersion)) {
            return [
                'success' => false,
                'error' => 'Flow contains node types without registered compilers',
            ];
        }

        // Start transaction to ensure atomicity
        return DB::transaction(function () use ($flowVersion) {
            // Step 1: Compile and store the flow artifact
            $artifactResult = $this->artifactService->compileAndStore($flowVersion);

            if (!$artifactResult['success']) {
                throw new \RuntimeException($artifactResult['error']);
            }

            // Step 2: Create/update the tenant's dialplan manifest
            $manifest = TenantDialplanManifest::updateOrCreate(
                [
                    'tenant_id' => $flowVersion->flow->tenant_id,
                    'manifest_type' => 'inbound_routing',
                ],
                [
                    'content' => $artifactResult['content'] ?? '',
                    'checksum' => $artifactResult['checksum'],
                ]
            );

            // Step 3: Activate the manifest
            $this->artifactService->activateManifest($manifest);

            // Update flow version status
            $flowVersion->update(['status' => 'published']);

            return [
                'success' => true,
                'artifact_id' => $artifactResult['artifact_id'] ?? null,
                'manifest_id' => $manifest->id,
                'checksum' => $artifactResult['checksum'],
            ];
        });
    }

    /**
     * Rollback a published flow version.
     *
     * This deactivates the tenant's manifest, effectively rolling back
     * to the previous state (or interpreted mode if no previous manifest exists).
     */
    public function rollback(FlowVersion $flowVersion): bool
    {
        return DB::transaction(function () use ($flowVersion) {
            // Deactivate the tenant's manifest
            TenantDialplanManifest::where('tenant_id', $flowVersion->flow->tenant_id)
                ->where('manifest_type', 'inbound_routing')
                ->where('is_active', true)
                ->update(['is_active' => false]);

            // Update flow version status
            return $flowVersion->update(['status' => 'draft']);
        });
    }
}
