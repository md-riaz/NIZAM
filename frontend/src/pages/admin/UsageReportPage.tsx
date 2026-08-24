import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';

import {
    KpiTile,
    ReportPanelState,
    ReportRangeBar,
    defaultReportRange,
    formatCount,
    formatDateOnly,
    formatDecimal,
    type ReportRange,
} from '@/components/admin/ReportPrimitives';
import { PageHeader } from '@/components/scaffolds/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
import { formatFileSize } from '@/lib/media';
import type { UsageMetricSummary, UsageReconciliation, UsageSummaryReport } from '@/types/models';

/**
 * How each metered metric should be read.
 *
 * `cumulative` metrics accrue over the period, so their sum is the headline
 * number. `gauge` metrics are daily snapshots of a level (extensions in service,
 * bytes on disk), so summing them across days is meaningless — the peak is what
 * matters, and the total column is deliberately suppressed.
 */
const METRIC_META: Record<
    string,
    { label: string; kind: 'cumulative' | 'gauge'; unit: 'minutes' | 'bytes' | 'count'; description: string }
> = {
    call_minutes: {
        label: 'Call minutes',
        kind: 'cumulative',
        unit: 'minutes',
        description: 'Billable minutes accrued over the period.',
    },
    concurrent_call_peak: {
        label: 'Concurrent call peak',
        kind: 'gauge',
        unit: 'count',
        description: 'Highest number of simultaneous calls recorded on any day.',
    },
    recording_storage_bytes: {
        label: 'Recording storage',
        kind: 'gauge',
        unit: 'bytes',
        description: 'Space used by stored recordings, snapshotted daily.',
    },
    active_devices: {
        label: 'Active devices',
        kind: 'gauge',
        unit: 'count',
        description: 'Provisioned device profiles, snapshotted daily.',
    },
    active_extensions: {
        label: 'Active extensions',
        kind: 'gauge',
        unit: 'count',
        description: 'Extensions in service, snapshotted daily.',
    },
};

function metricMeta(metric: string) {
    return (
        METRIC_META[metric] ?? {
            label: metric.replace(/[_-]+/g, ' ').replace(/^./, (c) => c.toUpperCase()),
            kind: 'cumulative' as const,
            unit: 'count' as const,
            description: 'Recorded by the metering collector.',
        }
    );
}

function formatMetricValue(value: number, unit: 'minutes' | 'bytes' | 'count'): string {
    switch (unit) {
        case 'bytes':
            return formatFileSize(value);
        case 'minutes':
            return `${formatDecimal(value, 1)} min`;
        default:
            return formatDecimal(value, value % 1 === 0 ? 0 : 2);
    }
}

export default function UsageReportPage() {
    const { activeOrganization, organizationApiPrefix } = useOrganization();

    const [draft, setDraft] = useState<ReportRange>(defaultReportRange);
    const [applied, setApplied] = useState<ReportRange>(defaultReportRange);

    const enabled = Boolean(activeOrganization);

    // The usage endpoints read `from`/`to`, not the `date_from`/`date_to` the CDR
    // and supervisor reports use, and default to month-to-date when absent.
    const params = { from: applied.date_from, to: applied.date_to };

    const summaryQuery = useQuery({
        queryKey: ['usage', 'summary', activeOrganization?.id, params],
        queryFn: async () => {
            const response = await api.get<{ data: UsageSummaryReport }>(
                `${organizationApiPrefix}/usage/summary`,
                { params },
            );
            return response.data.data;
        },
        enabled,
    });

    const reconcileQuery = useQuery({
        queryKey: ['usage', 'reconcile', activeOrganization?.id, params],
        queryFn: async () => {
            const response = await api.get<{ data: UsageReconciliation }>(
                `${organizationApiPrefix}/usage/reconcile`,
                { params },
            );
            return response.data.data;
        },
        enabled,
    });

    if (!activeOrganization) {
        return (
            <div className="flex h-64 items-center justify-center text-muted-foreground">
                Select an organization to view usage.
            </div>
        );
    }

    const summary = summaryQuery.data;
    const reconciliation = reconcileQuery.data;

    const metrics: Array<[string, UsageMetricSummary]> = Object.entries(summary?.usage ?? {}).sort(
        ([a], [b]) => metricMeta(a).label.localeCompare(metricMeta(b).label),
    );

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <PageHeader
                title="Usage"
                description="Metered usage for this organization, and how it reconciles against call records."
                breadcrumbs={`${activeOrganization.name} › Reports`}
            />

            <ReportRangeBar
                idPrefix="usage"
                draft={draft}
                onDraftChange={setDraft}
                onApply={() => setApplied(draft)}
                onReset={() => {
                    const reset = defaultReportRange();
                    setDraft(reset);
                    setApplied(reset);
                }}
            />

            {/* ─── Metered usage ────────────────────────────── */}
            <Card>
                <CardHeader>
                    <CardTitle>Metered usage</CardTitle>
                    <CardDescription>
                        Aggregated from the daily usage records written by the metering collector.
                        {summary
                            ? ` Covering ${formatDateOnly(summary.from)} to ${formatDateOnly(summary.to)}.`
                            : ''}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <ReportPanelState
                        isLoading={summaryQuery.isLoading}
                        error={summaryQuery.error}
                        isEmpty={metrics.length === 0}
                        loadingLabel="Loading metered usage…"
                        errorMessage="Could not load metered usage."
                        emptyMessage="No usage was recorded in this period. Usage appears here once the metering collector has run."
                        forbiddenMessage="You do not have permission to view usage for this organization."
                    >
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Metric</TableHead>
                                        <TableHead className="text-right">Total</TableHead>
                                        <TableHead className="text-right">Peak</TableHead>
                                        <TableHead className="text-right">Average</TableHead>
                                        {/*
                                          * Days, not records. `call_minutes` is written once per
                                          * billable hangup, so labelling the record count as days
                                          * reported fifty calls in one day as fifty days.
                                          */}
                                        <TableHead className="text-right">Days recorded</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {metrics.map(([metric, values]) => {
                                        const meta = metricMeta(metric);

                                        return (
                                            <TableRow key={metric}>
                                                <TableCell>
                                                    <div className="font-medium">{meta.label}</div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {meta.description}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {meta.kind === 'cumulative' ? (
                                                        formatMetricValue(values.total, meta.unit)
                                                    ) : (
                                                        <span
                                                            className="text-muted-foreground"
                                                            title="This metric is a daily snapshot of a level, so a sum across days is not meaningful."
                                                        >
                                                            n/a
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {formatMetricValue(values.peak, meta.unit)}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {formatMetricValue(values.average, meta.unit)}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {formatCount(values.days)}
                                                    <div className="text-xs text-muted-foreground">
                                                        {formatCount(values.count)}{' '}
                                                        {values.count === 1 ? 'record' : 'records'}
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        </div>
                    </ReportPanelState>
                </CardContent>
            </Card>

            {/* ─── Reconciliation ──────────────────────────── */}
            <Card>
                <CardHeader>
                    <CardTitle>Call minute reconciliation</CardTitle>
                    <CardDescription>
                        Compares billable minutes summed from call records against the metered
                        call-minute records for the same period.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <ReportPanelState
                        isLoading={reconcileQuery.isLoading}
                        error={reconcileQuery.error}
                        isEmpty={false}
                        loadingLabel="Loading reconciliation…"
                        errorMessage="Could not load call minute reconciliation."
                        emptyMessage="Nothing to reconcile in this period."
                        forbiddenMessage="You do not have permission to view usage for this organization."
                    >
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            <KpiTile
                                label="From call records"
                                value={
                                    reconciliation
                                        ? `${formatDecimal(reconciliation.cdr_total_minutes, 1)} min`
                                        : '—'
                                }
                                hint={
                                    reconciliation
                                        ? `${formatCount(reconciliation.cdr_total_seconds)} billable seconds`
                                        : undefined
                                }
                            />
                            <KpiTile
                                label="Metered"
                                value={
                                    reconciliation
                                        ? `${formatDecimal(reconciliation.metered_minutes, 1)} min`
                                        : '—'
                                }
                            />
                            <KpiTile
                                label="Difference"
                                value={
                                    reconciliation
                                        ? `${formatDecimal(reconciliation.difference_minutes, 1)} min`
                                        : '—'
                                }
                                hint="Call records minus metered"
                                tone={
                                    reconciliation && !reconciliation.matched ? 'warning' : 'default'
                                }
                            />
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-sm font-medium text-muted-foreground">
                                        Status
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {reconciliation ? (
                                        <Badge
                                            variant={reconciliation.matched ? 'success' : 'warning'}
                                        >
                                            {reconciliation.matched ? 'Matched' : 'Discrepancy'}
                                        </Badge>
                                    ) : (
                                        <span className="text-muted-foreground">—</span>
                                    )}
                                </CardContent>
                            </Card>
                        </div>

                        {reconciliation &&
                        !reconciliation.matched &&
                        reconciliation.metered_minutes === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No call-minute usage records exist for this period, so the whole
                                difference is unmetered. This is expected if the metering collector
                                has not run over this range.
                            </p>
                        ) : null}
                    </ReportPanelState>
                </CardContent>
            </Card>
        </div>
    );
}
