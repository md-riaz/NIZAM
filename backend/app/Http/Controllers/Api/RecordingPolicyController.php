<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Did;
use App\Models\Extension;
use App\Models\Organization;
use App\Services\Recording\RecordingPolicyResolver;
use Illuminate\Http\JsonResponse;

/**
 * Read-only API for surfacing what the recording policy resolver actually
 * decides for a given scope, so admins can see the effective outcome
 * instead of a bare "Inherit" that silently resolves elsewhere.
 */
class RecordingPolicyController extends Controller
{
    public function __construct(
        protected RecordingPolicyResolver $resolver,
    ) {}

    /**
     * Effective recording policy at organization scope.
     */
    public function organization(Organization $organization): JsonResponse
    {
        $this->authorize('view', $organization);

        return response()->json([
            'data' => [
                'scope' => 'organization',
                ...$this->resolveBothDirections([
                    'organization_policy' => $organization->recording_policy,
                    'answered_target_type' => 'organization',
                ]),
            ],
        ]);
    }

    /**
     * Effective recording policy at DID scope.
     */
    public function did(Organization $organization, Did $did): JsonResponse
    {
        $this->authorize('view', $did);

        if ($did->organization_id !== $organization->id) {
            return response()->json(['message' => 'DID not found.'], 404);
        }

        return response()->json([
            'data' => [
                'scope' => 'did',
                ...$this->resolveBothDirections([
                    'did_policy' => $did->recording_policy,
                    'organization_policy' => $organization->recording_policy,
                    'answered_target_type' => 'did',
                ]),
            ],
        ]);
    }

    /**
     * Effective recording policy at extension scope.
     *
     * The extension's DID context comes from its default outbound DID, when set.
     */
    public function extension(Organization $organization, Extension $extension): JsonResponse
    {
        $this->authorize('view', $extension);

        if ($extension->organization_id !== $organization->id) {
            return response()->json(['message' => 'Extension not found.'], 404);
        }

        $extension->loadMissing('defaultOutboundDid');

        return response()->json([
            'data' => [
                'scope' => 'extension',
                ...$this->resolveBothDirections([
                    'extension_policy' => $extension->recording_policy,
                    'did_policy' => $extension->defaultOutboundDid?->recording_policy,
                    'organization_policy' => $organization->recording_policy,
                    'answered_target_type' => 'extension',
                ]),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{inbound: array<string, mixed>, outbound: array<string, mixed>}
     */
    protected function resolveBothDirections(array $context): array
    {
        return [
            'inbound' => $this->resolver->resolve([...$context, 'direction' => 'inbound']),
            'outbound' => $this->resolver->resolve([...$context, 'direction' => 'outbound']),
        ];
    }
}
