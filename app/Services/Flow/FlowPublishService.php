<?php

namespace App\Services\Flow;

use App\Models\FlowVersion;
use App\Services\TenantManifestBuilder;
use Illuminate\Support\Facades\DB;

class FlowPublishService
{
    public function __construct(
        protected FlowArtifactService $artifactService,
        protected TenantManifestBuilder $manifestBuilder,
    ) {}

    /**
     * Create a draft flow version.
     */
    public function createDraft(\App\Models\Flow $flow, array $definition): FlowVersion
    {
        $version = FlowVersion::create([
            'flow_id' => $flow->id,
            'version_number' => $flow->versions()->count() + 1,
            'definition_checksum' => md5(json_encode($definition)),
            'status' => 'draft',
            'is_published' => false,
            'runtime_mode' => 'compiled',
            'definition_json' => $definition,
        ]);

        return $version;
    }

    /**
     * Publish a flow version.
     *
     * This method:
     * 1. Compiles the flow into IR and generates dialplan XML
     * 2. Stores the compiled artifact
     * 3. Rebuilds and activates the tenant's complete dialplan manifest
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

            // Update flow version status so the builder picks it up
            $flowVersion->update(['status' => 'published']);
            
            // Also need to ensure this is the active version for the flow
            $flowVersion->flow->update(['active_version_id' => $flowVersion->id]);

            // Step 2 & 3: Rebuild and activate the tenant's complete manifest
            $this->manifestBuilder->buildAndActivate($flowVersion->flow->tenant);

            return [
                'success' => true,
                'artifact_id' => $artifactResult['artifact_id'] ?? null,
                'checksum' => $artifactResult['checksum'],
            ];
        });
    }

    /**
     * Rollback a published flow version.
     */
    public function rollback(FlowVersion $flowVersion): bool
    {
        return DB::transaction(function () use ($flowVersion) {
            // Revert flow version status
            $flowVersion->update(['status' => 'draft']);
            
            // Clear active version if it was this one
            if ($flowVersion->flow->active_version_id === $flowVersion->id) {
                // Find previous published version or set null
                $prev = $flowVersion->flow->versions()
                    ->where('status', 'published')
                    ->where('id', '!=', $flowVersion->id)
                    ->orderBy('created_at', 'desc')
                    ->first();
                    
                $flowVersion->flow->update(['active_version_id' => $prev?->id]);
            }
            
            // Rebuild manifest without this flow version
            $this->manifestBuilder->buildAndActivate($flowVersion->flow->tenant);
            
            return true;
        });
    }
}
