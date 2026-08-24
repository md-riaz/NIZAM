import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';

import {
    CallVolumeChart,
    KpiTile,
    ProportionBar,
    ReportPanelState,
    ReportRangeBar,
    defaultReportRange,
    formatCount,
    formatDecimal,
    formatPercent,
    type ReportRange,
} from '@/components/admin/ReportPrimitives';
import { PageHeader } from '@/components/scaffolds/PageHeader';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useOrganization } from '@/context/OrganizationContext';
import api from '@/lib/api';
import { formatDuration } from '@/lib/media';
import type {
    CdrAnalyticsSummary,
    CdrQualityPoint,
    CdrTopDestination,
    CdrVolumePoint,
} from '@/types/models';

const DESTINATION_LIMITS = ['10', '20', '50'] as const;

interface AnalyticsFilters extends ReportRange {
    limit: string;
}

function initialFilters(): AnalyticsFilters {
    return { ...defaultReportRange(), limit: '20' };
}

/**
 * Turn a grouping key like `local_extension` into `Local extension`.
 *
 * `direction` and `call_type` are nullable columns grouped with `pluck`, so a
 * blank key is possible and must not render as an empty row label.
 */
function humanizeKey(key: string): string {
    const spaced = key.replace(/[_-]+/g, ' ').trim();

    if (spaced === '') return 'Unknown';

    return spaced.charAt(0).toUpperCase() + spaced.slice(1);
}

/** Render a `Record<string, number>` breakdown as a small definition list. */
function BreakdownList({ counts, emptyLabel }: { counts: Record<string, number>; emptyLabel: string }) {
    const entries = Object.entries(counts).sort(([, a], [, b]) => b - a);

    if (entries.length === 0) {
        return <p className="text-sm text-muted-foreground">{emptyLabel}</p>;
    }

    const total = entries.reduce((sum, [, count]) => sum + count, 0);

    return (
        <dl className="space-y-2">
            {entries.map(([key, count]) => (
                <div key={key} className="flex items-baseline justify-between gap-4">
                    <dt className="text-sm text-muted-foreground">{humanizeKey(key)}</dt>
                    <dd className="text-sm font-medium">
                        {formatCount(count)}
                        {total > 0 ? (
                            <span className="ml-2 text-xs font-normal text-muted-foreground">
                                {formatPercent((count / total) * 100)}
                            </span>
                        ) : null}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

export default function CallAnalyticsPage() {
    const { activeOrganization, organizationApiPrefix } = useOrganization();

    const [draft, setDraft] = useState<AnalyticsFilters>(initialFilters);
    const [applied, setApplied] = useState<AnalyticsFilters>(initialFilters);

    const enabled = Boolean(activeOrganization);
    const rangeParams = { date_from: applied.date_from, date_to: applied.date_to };

    const summaryQuery = useQuery({
        queryKey: ['cdr-analytics', 'summary', activeOrganization?.id, rangeParams],
        queryFn: async () => {
            const response = await api.get<{ data: CdrAnalyticsSummary }>(
                `${organizationApiPrefix}/cdrs/analytics/summary`,
                { params: rangeParams },
            );
            return response.data.data;
        },
        enabled,
    });

    // Granularity is left at the server default of `daily`. The hourly branch of
    // CdrAnalyticsService builds its buckets with MySQL's DATE_FORMAT(), which
    // errors on this stack's Postgres, so no hourly toggle is offered.
    const volumeQuery = useQuery({
        queryKey: ['cdr-analytics', 'volume', activeOrganization?.id, rangeParams],
        queryFn: async () => {
            const response = await api.get<{ data: CdrVolumePoint[] }>(
                `${organizationApiPrefix}/cdrs/analytics/volume`,
                { params: { ...rangeParams, granularity: 'daily' } },
            );
            return response.data.data;
        },
        enabled,
    });

    const qualityQuery = useQuery({
        queryKey: ['cdr-analytics', 'quality', activeOrganization?.id, rangeParams],
        queryFn: async () => {
            const response = await api.get<{ data: CdrQualityPoint[] }>(
                `${organizationApiPrefix}/cdrs/analytics/quality`,
                { params: { ...rangeParams, granularity: 'daily' } },
            );
            return response.data.data;
        },
        enabled,
    });

    const destinationsQuery = useQuery({
        queryKey: ['cdr-analytics', 'destinations', activeOrganization?.id, rangeParams, applied.limit],
        queryFn: async () => {
            const response = await api.get<{ data: CdrTopDestination[] }>(
                `${organizationApiPrefix}/cdrs/analytics/destinations`,
                { params: { ...rangeParams, limit: applied.limit } },
            );
            return response.data.data;
        },
        enabled,
    });

    if (!activeOrganization) {
        return (
            <div className="flex h-64 items-center justify-center text-muted-foreground">
                Select an organization to view call analytics.
            </div>
        );
    }

    const summary = summaryQuery.data;
    const volume = volumeQuery.data ?? [];
    const quality = qualityQuery.data ?? [];
    const destinations = destinationsQuery.data ?? [];
    const destinationPeak = destinations.reduce((max, row) => Math.max(max, row.total_calls), 0);

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <PageHeader
                title="Call Analytics"
                description="Volume, answer rates, and media quality for this organization's calls."
                breadcrumbs={`${activeOrganization.name} › Reports`}
            />

            <ReportRangeBar
                idPrefix="analytics"
                draft={draft}
                onDraftChange={(range) => setDraft({ ...draft, ...range })}
                onApply={() => setApplied(draft)}
                onReset={() => {
                    const reset = initialFilters();
                    setDraft(reset);
                    setApplied(reset);
                }}
            >
                <div className="space-y-1.5">
                    <Label htmlFor="analytics-limit">Top destinations</Label>
                    <Select
                        value={draft.limit}
                        onValueChange={(limit) => setDraft({ ...draft, limit })}
                    >
                        <SelectTrigger id="analytics-limit">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {DESTINATION_LIMITS.map((limit) => (
                                <SelectItem key={limit} value={limit}>
                                    Top {limit}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </ReportRangeBar>

            {/* ─── Summary KPIs ─────────────────────────────── */}
            <ReportPanelState
                isLoading={summaryQuery.isLoading}
                error={summaryQuery.error}
                isEmpty={false}
                loadingLabel="Loading call analytics summary…"
                errorMessage="Could not load the call analytics summary."
                emptyMessage="No calls in this period."
            >
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <KpiTile label="Total calls" value={formatCount(summary?.total_calls)} />
                    <KpiTile
                        label="Answered"
                        value={formatCount(summary?.answered_calls)}
                        hint={summary ? `${formatPercent(summary.asr)} answer rate (ASR)` : undefined}
                        tone="positive"
                    />
                    <KpiTile label="Missed" value={formatCount(summary?.missed_calls)} tone="warning" />
                    <KpiTile
                        label="Failed"
                        value={formatCount(summary?.failed_calls)}
                        hint="Excludes normal clearing, no answer, and caller cancels"
                        tone="negative"
                    />
                    <KpiTile
                        label="Avg call duration (ACD)"
                        value={formatDuration(summary?.acd_seconds)}
                        hint="Billable seconds per answered call"
                    />
                    <KpiTile
                        label="Total talk time"
                        value={formatDuration(summary?.total_billsec_seconds)}
                        hint={
                            summary
                                ? `${formatDuration(summary.total_duration_seconds)} including ring time`
                                : undefined
                        }
                    />
                    <KpiTile
                        label="Avg quality score"
                        value={
                            summary?.quality.average_score === null ||
                            summary?.quality.average_score === undefined
                                ? 'No data'
                                : formatDecimal(summary.quality.average_score, 1)
                        }
                        hint="Averaged over calls that reported a score"
                    />
                    <KpiTile
                        label="Avg MOS"
                        value={
                            summary?.quality.average_mos === null ||
                            summary?.quality.average_mos === undefined
                                ? 'No data'
                                : formatDecimal(summary.quality.average_mos, 2)
                        }
                        hint="Mean opinion score, 1–5"
                    />
                </div>
            </ReportPanelState>

            {/* ─── Volume over time ─────────────────────────── */}
            <Card>
                <CardHeader>
                    <CardTitle>Call volume</CardTitle>
                    <CardDescription>
                        Daily totals, split by whether the call was answered.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <ReportPanelState
                        isLoading={volumeQuery.isLoading}
                        error={volumeQuery.error}
                        isEmpty={volume.length === 0}
                        loadingLabel="Loading call volume…"
                        errorMessage="Could not load call volume."
                        emptyMessage="No calls in this period."
                    >
                        <div className="space-y-6">
                            <CallVolumeChart points={volume} />

                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Day</TableHead>
                                            <TableHead className="text-right">Calls</TableHead>
                                            <TableHead className="text-right">Answered</TableHead>
                                            <TableHead className="text-right">Answer rate</TableHead>
                                            <TableHead className="text-right">Talk time</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {volume.map((point) => (
                                            <TableRow key={point.period}>
                                                <TableCell className="font-mono text-sm">
                                                    {point.period}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {formatCount(point.total_calls)}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {formatCount(point.answered_calls)}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {formatPercent(point.asr)}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {formatDuration(point.total_billsec_seconds)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        </div>
                    </ReportPanelState>
                </CardContent>
            </Card>

            {/* ─── Direction / call type breakdowns ─────────── */}
            <div className="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>By direction</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ReportPanelState
                            isLoading={summaryQuery.isLoading}
                            error={summaryQuery.error}
                            isEmpty={false}
                            loadingLabel="Loading direction breakdown…"
                            errorMessage="Could not load the direction breakdown."
                            emptyMessage="No calls in this period."
                        >
                            <BreakdownList
                                counts={summary?.by_direction ?? {}}
                                emptyLabel="No calls in this period."
                            />
                        </ReportPanelState>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>By call type</CardTitle>
                        <CardDescription>Calls with no recorded type are excluded.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ReportPanelState
                            isLoading={summaryQuery.isLoading}
                            error={summaryQuery.error}
                            isEmpty={false}
                            loadingLabel="Loading call type breakdown…"
                            errorMessage="Could not load the call type breakdown."
                            emptyMessage="No classified calls in this period."
                        >
                            <BreakdownList
                                counts={summary?.by_call_type ?? {}}
                                emptyLabel="No classified calls in this period."
                            />
                        </ReportPanelState>
                    </CardContent>
                </Card>
            </div>

            {/* ─── Quality trends ──────────────────────────── */}
            <Card>
                <CardHeader>
                    <CardTitle>Quality trends</CardTitle>
                    <CardDescription>
                        Daily averages across calls that reported media statistics. Days with no
                        quality data do not appear.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <ReportPanelState
                        isLoading={qualityQuery.isLoading}
                        error={qualityQuery.error}
                        isEmpty={quality.length === 0}
                        loadingLabel="Loading quality trends…"
                        errorMessage="Could not load quality trends."
                        emptyMessage="No calls in this period reported media quality statistics."
                    >
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Day</TableHead>
                                        <TableHead className="text-right">Quality score</TableHead>
                                        <TableHead className="text-right">MOS</TableHead>
                                        <TableHead className="text-right">Packet loss</TableHead>
                                        <TableHead className="text-right">Jitter</TableHead>
                                        <TableHead className="text-right">Latency</TableHead>
                                        <TableHead className="text-right">Calls sampled</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {quality.map((point) => (
                                        <TableRow key={point.period}>
                                            <TableCell className="font-mono text-sm">
                                                {point.period}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {formatDecimal(point.avg_quality_score, 1)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {formatDecimal(point.avg_mos, 2)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {formatPercent(point.avg_packet_loss, 2)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {point.avg_jitter_ms === null
                                                    ? '—'
                                                    : `${formatDecimal(point.avg_jitter_ms, 1)} ms`}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {point.avg_latency_ms === null
                                                    ? '—'
                                                    : `${formatDecimal(point.avg_latency_ms, 1)} ms`}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {formatCount(point.sample_count)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </ReportPanelState>
                </CardContent>
            </Card>

            {/* ─── Top destinations ────────────────────────── */}
            <Card>
                <CardHeader>
                    <CardTitle>Top destinations</CardTitle>
                    <CardDescription>
                        Most-dialled destination numbers in this period, busiest first.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <ReportPanelState
                        isLoading={destinationsQuery.isLoading}
                        error={destinationsQuery.error}
                        isEmpty={destinations.length === 0}
                        loadingLabel="Loading top destinations…"
                        errorMessage="Could not load top destinations."
                        emptyMessage="No calls in this period."
                    >
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Destination</TableHead>
                                        {/*
                                          * The bar is scaled against the busiest row shown, not
                                          * against every call in the period — the endpoint returns
                                          * only the top N, so a share of the whole is not something
                                          * this response can express.
                                          */}
                                        <TableHead>Relative volume</TableHead>
                                        <TableHead className="text-right">Calls</TableHead>
                                        <TableHead className="text-right">Answered</TableHead>
                                        <TableHead className="text-right">Answer rate</TableHead>
                                        <TableHead className="text-right">Talk time</TableHead>
                                        <TableHead className="text-right">Quality</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {destinations.map((row, index) => (
                                        <TableRow key={`${row.destination_number ?? 'unknown'}-${index}`}>
                                            <TableCell className="font-mono text-sm">
                                                {row.destination_number || '—'}
                                            </TableCell>
                                            <TableCell className="w-32">
                                                <ProportionBar
                                                    value={row.total_calls}
                                                    max={destinationPeak}
                                                />
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {formatCount(row.total_calls)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {formatCount(row.answered_calls)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {formatPercent(row.asr)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {formatDuration(row.total_billsec_seconds)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {formatDecimal(row.avg_quality_score, 1)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </ReportPanelState>
                </CardContent>
            </Card>
        </div>
    );
}
