<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallDetailRecord;
use App\Models\Gateway;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

/**
 * API controller exposing codec negotiation metrics for a tenant.
 */
class CodecMetricsController extends Controller
{
    /**
     * Return codec distribution and mismatch metrics for a tenant.
     */
    public function __invoke(Tenant $tenant): JsonResponse
    {
        $this->authorize('view', $tenant);

        $cdrs = CallDetailRecord::where('tenant_id', $tenant->id)
            ->whereNotNull('negotiated_codec')
            ->selectRaw('negotiated_codec, count(*) as count')
            ->groupBy('negotiated_codec')
            ->get();

        $total = $cdrs->sum('count');

        $distribution = $cdrs->map(fn ($row) => [
            'codec' => $row->negotiated_codec,
            'count' => (int) $row->count,
            'percentage' => $total > 0 ? round(($row->count / $total) * 100, 2) : 0.0,
        ])->values();

        $mismatches = CallDetailRecord::where('tenant_id', $tenant->id)
            ->whereNotNull('read_codec')
            ->whereNotNull('write_codec')
            ->whereRaw('read_codec != write_codec')
            ->count();

        $totalCdrs = CallDetailRecord::where('tenant_id', $tenant->id)->count();

        $gateways = Gateway::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->get(['id', 'name', 'inbound_codecs', 'outbound_codecs', 'allow_transcoding']);

        return response()->json([
            'data' => [
                'codec_distribution' => $distribution,
                'codec_mismatch_count' => $mismatches,
                'codec_mismatch_rate' => $totalCdrs > 0 ? round(($mismatches / $totalCdrs) * 100, 2) : 0.0,
                'active_gateways' => $gateways->count(),
                'gateways' => $gateways->map(fn ($g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'inbound_codecs' => $g->inbound_codecs ?? [],
                    'outbound_codecs' => $g->outbound_codecs ?? [],
                    'allow_transcoding' => $g->allow_transcoding,
                ])->values(),
            ],
        ]);
    }
}
