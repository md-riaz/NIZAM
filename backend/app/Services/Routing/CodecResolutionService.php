<?php

namespace App\Services\Routing;

use App\Models\Bridge;
use App\Models\Gateway;

/**
 * Deterministically resolve the effective codec set for a call leg.
 *
 * Resolution algorithm (Section 6 of the Codec spec):
 *   1. Load the bridge's codec_policy and codec_list.
 *   2. Load the gateway's preferred_codecs, inbound/outbound codec caps.
 *   3. Apply the codec policy to compute the final ordered codec list.
 *   4. Determine whether transcoding is needed and allowed.
 *   5. Return a structured result with codec list, transcoding flag, and
 *      the FreeSWITCH variable name/value pair to inject.
 *
 * Supported codec_policy values:
 *   default   — use gateway preferred_codecs (or outbound_codecs as fallback)
 *   restricted — use bridge codec_list intersected with gateway outbound_codecs
 *   preferred  — use bridge codec_list order, restricted to gateway outbound_codecs
 *   exact      — use bridge codec_list exactly (single or ordered, no gateway filter)
 *   inherit    — enable late negotiation / inherit B-leg codec (no explicit list)
 */
class CodecResolutionService
{
    /**
     * All codec keys recognised by the platform.
     */
    public const SUPPORTED_CODECS = [
        'OPUS', 'PCMU', 'PCMA', 'G722', 'G729',
        'G726', 'G726-32', 'iLBC', 'SPEEX', 'VP8', 'H264',
    ];

    /**
     * Default codec order for WebRTC (browser) endpoints.
     */
    public const WEBRTC_DEFAULT_CODECS = ['OPUS', 'G722', 'PCMU', 'PCMA'];

    /**
     * Default codec order for SIP phone (UDP/TLS) endpoints.
     */
    public const SIP_DEFAULT_CODECS = ['G722', 'PCMU', 'PCMA'];

    /**
     * Resolve effective codec list and transcoding requirement.
     *
     * @param  string  $endpointType  'webrtc' | 'sip'
     * @param  Bridge|null  $bridge   The outbound bridge (destination binding)
     * @param  Gateway|null  $gateway The assigned carrier gateway
     * @param  list<string>  $offeredCodecs  Codecs offered by the A-leg endpoint
     * @return array{
     *   effective_codecs: list<string>,
     *   transcoding_required: bool,
     *   transcoding_allowed: bool,
     *   fs_variable_name: string|null,
     *   fs_variable_value: string|null,
     *   inherit_codec: bool,
     *   warnings: list<string>
     * }
     */
    public function resolve(
        string $endpointType,
        ?Bridge $bridge,
        ?Gateway $gateway,
        array $offeredCodecs = [],
    ): array {
        $warnings = [];
        $gatewayOutbound = $gateway?->outbound_codecs ?? [];
        $gatewayPreferred = ! empty($gateway?->preferred_codecs)
            ? $gateway->preferred_codecs
            : $gatewayOutbound;
        $transcodeAllowed = $gateway?->allow_transcoding ?? false;

        $codecPolicy = $bridge?->codec_policy ?? 'default';
        $bridgeCodecList = $bridge?->codec_list ?? [];
        $bridgeTranscodePolicy = $bridge?->transcode_policy ?? 'none';

        // Determine transcode permission (most restrictive wins)
        if ($bridgeTranscodePolicy === 'none') {
            $transcodeAllowed = false;
        } elseif ($bridgeTranscodePolicy === 'web_only') {
            $transcodeAllowed = ($endpointType === 'webrtc');
        } elseif ($bridgeTranscodePolicy === 'allow') {
            // Bridge explicitly permits transcoding (overrides gateway default-deny)
            $transcodeAllowed = true;
        }

        $inheritCodec = false;
        $effectiveCodecs = [];

        switch ($codecPolicy) {
            case 'inherit':
                // FreeSWITCH late negotiation: inherit B-leg codec from A-leg
                $inheritCodec = true;
                $effectiveCodecs = ! empty($offeredCodecs) ? $offeredCodecs : $this->defaultsForEndpoint($endpointType);
                break;

            case 'exact':
                // Use bridge codec_list exactly — no gateway filtering
                $effectiveCodecs = $this->validated($bridgeCodecList);
                if (empty($effectiveCodecs)) {
                    $warnings[] = 'exact policy set but codec_list is empty; falling back to gateway defaults';
                    $effectiveCodecs = $gatewayPreferred ?: $this->defaultsForEndpoint($endpointType);
                }
                break;

            case 'restricted':
                // Intersection of bridge codec_list and gateway outbound codecs
                $effectiveCodecs = array_values(array_intersect(
                    $this->validated($bridgeCodecList),
                    $gatewayOutbound,
                ));
                if (empty($effectiveCodecs)) {
                    $warnings[] = 'restricted policy produced empty codec set; no shared codec between bridge list and gateway';
                }
                break;

            case 'preferred':
                // Bridge-defined order, filtered to gateway's allowed outbound set
                $effectiveCodecs = array_values(array_intersect(
                    $this->validated($bridgeCodecList),
                    ! empty($gatewayOutbound) ? $gatewayOutbound : self::SUPPORTED_CODECS,
                ));
                if (empty($effectiveCodecs) && ! empty($gatewayPreferred)) {
                    $effectiveCodecs = $gatewayPreferred;
                    $warnings[] = 'preferred policy produced empty codec set after gateway filter; using gateway preferred_codecs';
                }
                break;

            default: // 'default'
                $effectiveCodecs = ! empty($gatewayPreferred) ? $gatewayPreferred : $this->defaultsForEndpoint($endpointType);
                break;
        }

        // Determine if transcoding will be required
        $transcodingRequired = false;
        if (! $inheritCodec && ! empty($offeredCodecs) && ! empty($effectiveCodecs)) {
            $sharedCodecs = array_intersect($offeredCodecs, $effectiveCodecs);
            $transcodingRequired = empty($sharedCodecs);
        }

        if ($transcodingRequired && ! $transcodeAllowed) {
            $warnings[] = 'transcoding required but not allowed by policy; call will fail at bridge time';
        }

        // Determine the FreeSWITCH variable to set on the outbound leg
        [$fsVarName, $fsVarValue] = $this->fsVariable($codecPolicy, $effectiveCodecs, $inheritCodec);

        return [
            'effective_codecs' => $effectiveCodecs,
            'transcoding_required' => $transcodingRequired,
            'transcoding_allowed' => $transcodeAllowed,
            'fs_variable_name' => $fsVarName,
            'fs_variable_value' => $fsVarValue,
            'inherit_codec' => $inheritCodec,
            'warnings' => $warnings,
        ];
    }

    /**
     * Return the FreeSWITCH variable name/value pair for the given policy.
     *
     * @param  list<string>  $effectiveCodecs
     * @return array{string|null, string|null}
     */
    protected function fsVariable(string $policy, array $effectiveCodecs, bool $inheritCodec): array
    {
        if ($inheritCodec) {
            return [null, null];
        }

        if (empty($effectiveCodecs)) {
            return [null, null];
        }

        $codecString = implode(',', $effectiveCodecs);

        return match ($policy) {
            // Exact / restricted = hard override — no negotiation
            'exact', 'restricted' => ['absolute_codec_string', $codecString],
            // Default / preferred = ordered preference — FreeSWITCH can still negotiate
            default => ['codec_string', $codecString],
        };
    }

    /**
     * Return codec defaults for a given endpoint type.
     *
     * @return list<string>
     */
    protected function defaultsForEndpoint(string $endpointType): array
    {
        return match ($endpointType) {
            'webrtc' => self::WEBRTC_DEFAULT_CODECS,
            default => self::SIP_DEFAULT_CODECS,
        };
    }

    /**
     * Filter a codec list to only known supported values.
     *
     * @param  list<string>  $codecs
     * @return list<string>
     */
    protected function validated(array $codecs): array
    {
        return array_values(array_filter(
            $codecs,
            fn (string $c) => in_array($c, self::SUPPORTED_CODECS, true),
        ));
    }
}
