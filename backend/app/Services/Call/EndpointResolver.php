<?php

namespace App\Services\Call;

use App\Models\Agent;
use App\Models\EndpointBinding;
use App\Models\Extension;
use Illuminate\Support\Collection;

class EndpointResolver
{
    public function resolve(DeliveryTargetSet $targetSet): EndpointCandidateSet
    {
        $candidates = [];

        foreach (array_values($targetSet->targets) as $index => $target) {
            $candidates = [
                ...$candidates,
                ...$this->resolveTargetCandidates($target, $index),
            ];
        }

        return new EndpointCandidateSet(
            candidates: $candidates,
            metadata: [
                ...$targetSet->metadata,
                'resolved_candidate_count' => count($candidates),
            ],
        );
    }

    /**
     * @return list<EndpointCandidate>
     */
    protected function resolveTargetCandidates(DeliveryTarget $target, int $targetIndex): array
    {
        return match ($target->type) {
            'extension' => $this->resolveExtensionCandidates($target, $targetIndex),
            'agent' => $this->resolveAgentCandidates($target, $targetIndex),
            default => [],
        };
    }

    /**
     * @return list<EndpointCandidate>
     */
    protected function resolveExtensionCandidates(DeliveryTarget $target, int $targetIndex): array
    {
        $extension = Extension::query()
            ->with(['organization', 'agent'])
            ->whereKey($target->id)
            ->where('is_active', true)
            ->first();

        if (! $extension) {
            return [];
        }

        $query = EndpointBinding::query()
            ->with(['organization', 'extension', 'agent'])
            ->where('organization_id', $extension->organization_id)
            ->where(function ($query) use ($extension): void {
                $query->where('extension_id', $extension->id);

                if ($extension->agent?->id) {
                    $query->orWhere('agent_id', $extension->agent->id);
                }
            });

        return $this->buildCandidates(
            bindings: $query->get(),
            ownerType: 'extension',
            ownerId: $extension->id,
            sourcePath: $target->sourcePath,
            priority: $this->resolvePriority($target, $targetIndex),
        );
    }

    /**
     * @return list<EndpointCandidate>
     */
    protected function resolveAgentCandidates(DeliveryTarget $target, int $targetIndex): array
    {
        $agent = Agent::query()
            ->with(['organization', 'extension'])
            ->whereKey($target->id)
            ->where('is_active', true)
            ->first();

        if (! $agent) {
            return [];
        }

        $query = EndpointBinding::query()
            ->with(['organization', 'extension', 'agent.extension'])
            ->where('organization_id', $agent->organization_id)
            ->where(function ($query) use ($agent): void {
                $query->where('agent_id', $agent->id);

                if ($agent->extension_id) {
                    $query->orWhere('extension_id', $agent->extension_id);
                }
            });

        return $this->buildCandidates(
            bindings: $query->get(),
            ownerType: 'agent',
            ownerId: $agent->id,
            sourcePath: $target->sourcePath,
            priority: $this->resolvePriority($target, $targetIndex),
        );
    }

    /**
     * @param  Collection<int, EndpointBinding>  $bindings
     * @param  list<array<string, mixed>>  $sourcePath
     * @return list<EndpointCandidate>
     */
    protected function buildCandidates(Collection $bindings, string $ownerType, string $ownerId, array $sourcePath, int $priority): array
    {
        return $bindings
            ->filter(fn (EndpointBinding $binding) => $binding->isEligibleForOrchestration())
            ->unique('id')
            ->sortBy(fn (EndpointBinding $binding) => $this->bindingPriority($binding))
            ->values()
            ->map(fn (EndpointBinding $binding) => new EndpointCandidate(
                endpointBindingId: $binding->id,
                ownerType: $ownerType,
                ownerId: $ownerId,
                candidateType: $binding->type,
                sipAor: $this->sipAorFor($binding),
                pushCapable: $binding->is_push_capable && $binding->hasPushTokenMaterial(),
                allowLateJoinAfterPush: $binding->allow_late_join_after_push,
                forwardNumber: $binding->type === EndpointBinding::TYPE_PSTN_FORWARD ? $binding->forward_number : null,
                forwardRequiresConfirm: $binding->type === EndpointBinding::TYPE_PSTN_FORWARD
                    ? $binding->forward_requires_confirm
                    : false,
                priority: $priority,
                sourcePath: $sourcePath,
            ))
            ->all();
    }

    protected function sipAorFor(EndpointBinding $binding): ?string
    {
        if ($binding->type === EndpointBinding::TYPE_PSTN_FORWARD) {
            return null;
        }

        $extension = $binding->extension ?? $binding->agent?->extension;

        if (! $extension || blank($extension->extension) || blank($binding->organization?->domain)) {
            return null;
        }

        return sprintf('sip:%s@%s', $extension->extension, $binding->organization->domain);
    }

    protected function bindingPriority(EndpointBinding $binding): int
    {
        return match ($binding->type) {
            EndpointBinding::TYPE_DESK_PHONE => 0,
            EndpointBinding::TYPE_SOFTPHONE => 1,
            EndpointBinding::TYPE_AGENT_ENDPOINT => 2,
            EndpointBinding::TYPE_MOBILE_APP => 3,
            EndpointBinding::TYPE_PSTN_FORWARD => 4,
            default => 5,
        };
    }

    protected function resolvePriority(DeliveryTarget $target, int $targetIndex): int
    {
        $priority = data_get($target->metadata, 'priority');

        if (is_numeric($priority)) {
            return (int) $priority;
        }

        $priority = data_get($target->sourcePath, '*.priority');

        if (is_array($priority)) {
            $priority = collect($priority)->first(static fn ($value) => is_numeric($value));
        }

        if (is_numeric($priority)) {
            return (int) $priority;
        }

        return $targetIndex;
    }
}
