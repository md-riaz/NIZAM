<?php

namespace Modules\PbxMediaPolicy;

use App\Models\Gateway;
use App\Models\Organization;
use App\Modules\BaseModule;

class PbxMediaPolicyModule extends BaseModule
{
    public function name(): string
    {
        return 'pbx-media-policy';
    }

    public function description(): string
    {
        return 'Media policy: gateway codec config, organization codec enforcement, dialplan codec injection, transcoding awareness';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function subscribedEvents(): array
    {
        return [
            'call.created',
            'call.hangup',
        ];
    }

    public function permissions(): array
    {
        return [
            'gateways.view',
            'gateways.manage',
            'codec-metrics.view',
        ];
    }

    public function routesFile(): ?string
    {
        return __DIR__.'/../routes/api.php';
    }

    /**
     * Inject absolute_codec_string into dialplan based on organization codec policy.
     *
     * @return array<int, string>
     */
    public function dialplanContributions(string $organizationDomain, string $destination): array
    {
        $organization = Organization::where('domain', $organizationDomain)->first();

        if (! $organization) {
            return [];
        }

        $codecPolicy = $organization->codec_policy ?? [];
        $codecs = $codecPolicy['codecs'] ?? [];

        if (empty($codecs)) {
            return [];
        }

        $codecString = implode(',', $codecs);

        return [
            10 => "<action application=\"export\" data=\"absolute_codec_string={$codecString}\"/>",
        ];
    }

    /**
     * Provide a pre-bridge policy hook that enforces codec policy.
     *
     * @return array<string, callable>
     */
    public function policyHooks(): array
    {
        return [
            'before.bridge' => function (array $context): array {
                $organizationId = $context['organization_id'] ?? null;

                if (! $organizationId) {
                    return ['allow_transcoding' => true, 'codec_string' => null];
                }

                $organization = Organization::find($organizationId);
                $codecPolicy = $organization?->codec_policy ?? [];

                $gateway = isset($context['gateway_id'])
                    ? Gateway::find($context['gateway_id'])
                    : null;

                $allowTranscoding = $gateway?->allow_transcoding
                    ?? $codecPolicy['allow_transcoding']
                    ?? true;

                $outboundCodecs = $gateway?->outbound_codecs
                    ?? $codecPolicy['codecs']
                    ?? [];

                return [
                    'allow_transcoding' => $allowTranscoding,
                    'codec_string' => ! empty($outboundCodecs) ? implode(',', $outboundCodecs) : null,
                ];
            },
        ];
    }
}
