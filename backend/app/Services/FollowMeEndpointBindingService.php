<?php

namespace App\Services;

use App\Models\EndpointBinding;
use App\Models\Extension;

class FollowMeEndpointBindingService
{
    public function sync(Extension $extension, array $state = []): void
    {
        $extension->loadMissing('agent');

        $enabled = (bool) ($state['follow_me_enabled'] ?? $extension->follow_me_enabled);
        $destination = $this->normalizeForwardNumber((string) ($state['follow_me_destination'] ?? $extension->follow_me_destination ?? ''));

        $query = EndpointBinding::query()
            ->where('tenant_id', $extension->tenant_id)
            ->where('type', EndpointBinding::TYPE_PSTN_FORWARD)
            ->where(function ($query) use ($extension): void {
                $query->where('extension_id', $extension->id);

                if ($extension->agent?->id) {
                    $query->orWhere('agent_id', $extension->agent->id);
                }
            });

        if (! $enabled || $destination === null) {
            $query->delete();

            return;
        }

        $binding = $query->orderByDesc('agent_id')->orderByDesc('created_at')->first();

        $payload = [
            'tenant_id' => $extension->tenant_id,
            'extension_id' => $extension->id,
            'agent_id' => $extension->agent?->id,
            'type' => EndpointBinding::TYPE_PSTN_FORWARD,
            'device_uuid' => sprintf('follow-me:%s', $extension->id),
            'platform' => EndpointBinding::PLATFORM_UNKNOWN,
            'is_push_capable' => false,
            'is_enabled' => true,
            'rings_immediately_when_online' => false,
            'allow_late_join_after_push' => false,
            'forward_number' => $destination,
            'forward_requires_confirm' => true,
            'push_token' => null,
            'voip_push_token' => null,
            'metadata' => [
                'source' => 'follow_me',
                'managed_by' => self::class,
            ],
        ];

        if ($binding) {
            $binding->forceFill($payload)->save();

            return;
        }

        EndpointBinding::query()->create($payload);
    }

    protected function normalizeForwardNumber(string $destination): ?string
    {
        if (trim($destination) === '') {
            return null;
        }

        $normalized = DidNormalizationService::toE164($destination);

        return DidNormalizationService::isE164($normalized) ? $normalized : null;
    }
}
