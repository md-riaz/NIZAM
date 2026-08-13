<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Did;
use App\Models\Extension;
use App\Models\Organization;
use App\Services\Recording\RecordingPolicy;
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
        // Scope first: a DID belonging to another organization is absent from
        // this URL, and answering 403 would confirm that it exists.
        if ($did->organization_id !== $organization->id) {
            return response()->json(['message' => 'DID not found.'], 404);
        }

        $this->authorize('view', $did);

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
     */
    public function extension(Organization $organization, Extension $extension): JsonResponse
    {
        if ($extension->organization_id !== $organization->id) {
            return response()->json(['message' => 'Extension not found.'], 404);
        }

        $this->authorize('view', $extension);

        $extension->loadMissing('defaultOutboundDid');

        $base = [
            'extension_policy' => $extension->recording_policy,
            'organization_policy' => $organization->recording_policy,
            'answered_target_type' => 'extension',
        ];

        return response()->json([
            'data' => [
                'scope' => 'extension',
                // Inbound and outbound resolve through different DID layers.
                //
                // Outbound calls carry the extension's default outbound DID, so
                // that number's policy sits between extension and organization.
                // Inbound calls arrive on whatever number routed to this
                // extension, which is not a single value — attributing the
                // outbound DID to inbound calls reported a policy that would
                // never apply. Inbound is therefore resolved without a DID
                // layer, and the numbers that would override it are listed
                // separately.
                'inbound' => $this->resolver->resolve([...$base, 'direction' => 'inbound']),
                'outbound' => $this->resolver->resolve([
                    ...$base,
                    'did_policy' => $extension->defaultOutboundDid?->recording_policy,
                    'direction' => 'outbound',
                ]),
                'inbound_did_overrides' => $this->inboundDidOverrides($extension),
            ],
        ]);
    }

    /**
     * Numbers routed straight to this extension whose own policy would win over
     * the extension's for calls arriving on them.
     *
     * @return array<int, array{id: string, number: string, recording_policy: string}>
     */
    protected function inboundDidOverrides(Extension $extension): array
    {
        return Did::query()
            ->where('organization_id', $extension->organization_id)
            ->where('destination_type', 'extension')
            ->where('destination_id', $extension->id)
            ->get(['id', 'number', 'recording_policy'])
            ->reject(fn (Did $did) => RecordingPolicy::normalize($did->recording_policy) === RecordingPolicy::INHERIT)
            ->map(fn (Did $did) => [
                'id' => (string) $did->id,
                'number' => (string) $did->number,
                'recording_policy' => RecordingPolicy::normalize($did->recording_policy),
            ])
            ->values()
            ->all();
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
