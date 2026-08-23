import { AlertTriangle, Lock, Search, X } from 'lucide-react';
import { useState, type ReactNode } from 'react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { CdrVolumePoint, PaginationMeta } from '@/types/models';

/**
 * Shared building blocks for the Reports section.
 *
 * The three report pages each stitch together several independent endpoints, so
 * per-panel state handling and the date-range control are factored out here
 * rather than repeated nine times.
 */

// ─── Date range ──────────────────────────────────────────────

export interface ReportRange {
    date_from: string;
    date_to: string;
}

/**
 * Format a date for a `type="date"` input using the viewer's own calendar day.
 *
 * `toISOString()` converts to UTC first, which shifts the day for anyone not on
 * UTC: at 01:00 in UTC+6 it yielded yesterday, so the default range ended before
 * today and silently omitted the day's calls; west of UTC it yielded tomorrow.
 */
function toDateInput(date: Date): string {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

/**
 * The last 30 days, which is also what every report endpoint falls back to when
 * no range is passed. Prefilling it means the inputs always describe the data on
 * screen instead of sitting empty over a server-chosen default.
 */
export function defaultReportRange(): ReportRange {
    const to = new Date();
    const from = new Date(to);
    from.setDate(from.getDate() - 30);

    return { date_from: toDateInput(from), date_to: toDateInput(to) };
}

/**
 * Draft-vs-applied range picker, matching the filter bars on Call History and
 * Recordings: editing the inputs does not refetch until Apply is pressed.
 */
export function ReportRangeBar({
    draft,
    onDraftChange,
    onApply,
    onReset,
    idPrefix,
    children,
}: {
    draft: ReportRange;
    onDraftChange: (range: ReportRange) => void;
    onApply: () => void;
    onReset: () => void;
    /** Keeps input ids unique when more than one bar is on a page. */
    idPrefix: string;
    /** Extra controls (granularity, window size) rendered alongside the dates. */
    children?: ReactNode;
}) {
    return (
        <Card>
            <CardContent className="pt-6">
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="space-y-1.5">
                        <Label htmlFor={`${idPrefix}-date-from`}>From date</Label>
                        <Input
                            id={`${idPrefix}-date-from`}
                            type="date"
                            value={draft.date_from}
                            max={draft.date_to || undefined}
                            onChange={(e) => onDraftChange({ ...draft, date_from: e.target.value })}
                        />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor={`${idPrefix}-date-to`}>To date</Label>
                        <Input
                            id={`${idPrefix}-date-to`}
                            type="date"
                            value={draft.date_to}
                            min={draft.date_from || undefined}
                            onChange={(e) => onDraftChange({ ...draft, date_to: e.target.value })}
                        />
                    </div>
                    {children}
                </div>

                <div className="mt-4 flex flex-wrap items-center gap-2">
                    <Button onClick={onApply} size="sm">
                        <Search className="size-4" />
                        Apply
                    </Button>
                    <Button onClick={onReset} size="sm" variant="ghost">
                        <X className="size-4" />
                        Last 30 days
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

// ─── Panel state ─────────────────────────────────────────────

/**
 * Detect an authorization failure without importing axios error classes.
 *
 * `supervisor-reports/voicemails-needing-follow-up` authorizes against
 * `Recording` while every other report authorizes against `CallDetailRecord`,
 * so a user with `cdrs.view` but not `recordings.view` gets a 403 on that one
 * panel. It has to degrade in place rather than take down the page.
 */
export function isForbiddenError(error: unknown): boolean {
    if (!error || typeof error !== 'object') return false;

    const response = (error as { response?: { status?: number } }).response;

    return response?.status === 403;
}

/**
 * Renders whichever of loading / forbidden / error / empty applies, or the
 * panel's real content when the data is there.
 *
 * `children` is evaluated eagerly, so callers must read their data defensively
 * (`data?.items ?? []`) — the same way the existing table pages do.
 */
export function ReportPanelState({
    isLoading,
    error,
    isEmpty,
    loadingLabel,
    errorMessage,
    emptyMessage,
    forbiddenMessage = 'You do not have permission to view this report.',
    children,
}: {
    isLoading: boolean;
    error: unknown;
    isEmpty: boolean;
    loadingLabel: string;
    errorMessage: string;
    emptyMessage: string;
    forbiddenMessage?: string;
    children: ReactNode;
}) {
    if (isLoading) {
        return (
            <div className="flex justify-center py-10" role="status">
                <div
                    className="size-6 motion-safe:animate-spin rounded-full border-2 border-primary border-t-transparent"
                    aria-hidden="true"
                />
                <span className="sr-only">{loadingLabel}</span>
            </div>
        );
    }

    if (error) {
        if (isForbiddenError(error)) {
            return (
                <div className="flex items-start gap-3 rounded-lg border border-dashed bg-muted/40 px-4 py-6 text-sm text-muted-foreground">
                    <Lock className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <p>{forbiddenMessage}</p>
                </div>
            );
        }

        return (
            <div className="flex items-start gap-3 rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-6 text-sm text-destructive">
                <AlertTriangle className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                <p>{errorMessage}</p>
            </div>
        );
    }

    if (isEmpty) {
        return (
            <p className="py-10 text-center text-sm text-muted-foreground">{emptyMessage}</p>
        );
    }

    return <>{children}</>;
}

// ─── Number formatting ───────────────────────────────────────

/** Percentages arrive as full-precision floats; never render them raw. */
export function formatPercent(value?: number | null, fractionDigits = 1): string {
    if (value === null || value === undefined || Number.isNaN(value)) return '—';

    return `${value.toFixed(fractionDigits)}%`;
}

export function formatCount(value?: number | null): string {
    if (value === null || value === undefined || Number.isNaN(value)) return '—';

    return value.toLocaleString();
}

/** Decimal measures (MOS, jitter, minutes) with a fixed precision and no raw floats. */
export function formatDecimal(value?: number | null, fractionDigits = 2): string {
    if (value === null || value === undefined || Number.isNaN(value)) return '—';

    return value.toLocaleString(undefined, {
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
    });
}

export function formatDateTime(value?: string | null): string {
    if (!value) return '—';

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}

export function formatDateOnly(value?: string | null): string {
    if (!value) return '—';

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString();
}

// ─── Client-side pagination ──────────────────────────────────

export const REPORT_PAGE_SIZE = 25;

/**
 * Slice a report's `items` into pages and synthesise paginator metadata.
 *
 * The supervisor report endpoints return every row in one response rather than a
 * paginator, so long ranges have to be paged in the browser. Producing real
 * `PaginationMeta` lets these tables reuse `TablePagination` unchanged.
 */
export function paginateReportItems<T>(
    items: T[],
    page: number,
    perPage = REPORT_PAGE_SIZE,
): { rows: T[]; meta: PaginationMeta } {
    const total = items.length;
    const lastPage = Math.max(1, Math.ceil(total / perPage));
    const current = Math.min(Math.max(1, page), lastPage);
    const start = (current - 1) * perPage;
    const rows = items.slice(start, start + perPage);

    return {
        rows,
        meta: {
            current_page: current,
            last_page: lastPage,
            per_page: perPage,
            total,
            from: total === 0 ? null : start + 1,
            to: total === 0 ? null : start + rows.length,
        },
    };
}

// ─── KPI tile ────────────────────────────────────────────────

const KPI_TONES = {
    default: '',
    positive: 'text-emerald-600',
    warning: 'text-amber-600',
    negative: 'text-destructive',
} as const;

export function KpiTile({
    label,
    value,
    hint,
    tone = 'default',
}: {
    label: string;
    value: ReactNode;
    hint?: ReactNode;
    tone?: keyof typeof KPI_TONES;
}) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">{label}</CardTitle>
            </CardHeader>
            <CardContent>
                <div className={cn('text-2xl font-bold', KPI_TONES[tone])}>{value}</div>
                {hint ? <p className="text-xs text-muted-foreground">{hint}</p> : null}
            </CardContent>
        </Card>
    );
}

// ─── Charts ──────────────────────────────────────────────────
//
// Built from CSS box heights rather than a charting library: the data is a
// handful of buckets, and a flex row of divs stays crisp and responsive without
// hand-rolling a viewBox. Both charts sit directly above a table of the same
// numbers, which is what relieves the low contrast of the amber fill.

/** Legend swatch + label. Identity is never carried by colour alone. */
function LegendItem({ className, label }: { className: string; label: string }) {
    return (
        <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
            <span className={cn('size-2.5 rounded-sm', className)} aria-hidden="true" />
            {label}
        </span>
    );
}

/**
 * Call volume per bucket, each column stacked as answered + unanswered so the
 * full column height is the bucket's total call count.
 *
 * Answered/unanswered reuse the emerald/amber pairing the call pages already use
 * for the same distinction.
 */
export function CallVolumeChart({ points }: { points: CdrVolumePoint[] }) {
    const [active, setActive] = useState<CdrVolumePoint | null>(null);

    const peak = points.reduce((max, point) => Math.max(max, point.total_calls), 0);

    if (peak === 0) {
        return (
            <p className="py-10 text-center text-sm text-muted-foreground">
                No calls in this period.
            </p>
        );
    }

    // Selective labels only — first, middle, last.
    const axisLabels =
        points.length <= 3
            ? points.map((point) => point.period)
            : [
                  points[0],
                  points[Math.floor((points.length - 1) / 2)],
                  points[points.length - 1],
              ].map((point) => point.period);

    return (
        <div className="space-y-3">
            {/*
              A shared readout rather than per-bar floating tooltips: with a
              month of daily buckets each column is only a few pixels wide, so an
              absolutely-positioned tooltip on the first or last bar would hang
              outside the card. One fixed-position line cannot overflow, and it
              stays put long enough to actually read.
            */}
            <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                <p className="min-h-5 text-xs text-muted-foreground" aria-live="polite">
                    {active ? (
                        <>
                            <span className="font-medium text-foreground">{active.period}</span>
                            {' · '}
                            {active.total_calls.toLocaleString()} calls ·{' '}
                            {active.answered_calls.toLocaleString()} answered ·{' '}
                            {formatPercent(active.asr)} answer rate
                        </>
                    ) : (
                        'Hover or tab a bar for that day’s detail.'
                    )}
                </p>
                <div className="flex items-center gap-4">
                    <LegendItem className="bg-emerald-600" label="Answered" />
                    <LegendItem className="bg-amber-500" label="Unanswered" />
                </div>
            </div>

            <div className="flex gap-3">
                {/* Recessive y-axis: just the peak and the baseline. */}
                <div className="flex h-48 w-10 shrink-0 flex-col justify-between text-right text-[10px] text-muted-foreground">
                    <span>{peak.toLocaleString()}</span>
                    <span>0</span>
                </div>

                <div className="min-w-0 flex-1">
                    <div className="flex h-48 items-end gap-[2px] border-b">
                        {points.map((point) => {
                            const unanswered = Math.max(0, point.total_calls - point.answered_calls);
                            const answeredPct = (point.answered_calls / peak) * 100;
                            const unansweredPct = (unanswered / peak) * 100;

                            return (
                                <div
                                    key={point.period}
                                    tabIndex={0}
                                    onMouseEnter={() => setActive(point)}
                                    onMouseLeave={() => setActive(null)}
                                    onFocus={() => setActive(point)}
                                    onBlur={() => setActive(null)}
                                    className={cn(
                                        'flex h-full min-w-[3px] flex-1 flex-col justify-end rounded-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                                        active?.period === point.period && 'bg-muted',
                                    )}
                                    aria-label={`${point.period}: ${point.total_calls.toLocaleString()} calls, ${point.answered_calls.toLocaleString()} answered, ${formatPercent(point.asr)} answer rate`}
                                >
                                    {unanswered > 0 ? (
                                        <div
                                            className="mb-[2px] w-full rounded-t-[4px] bg-amber-500"
                                            style={{ height: `${unansweredPct}%` }}
                                            aria-hidden="true"
                                        />
                                    ) : null}
                                    <div
                                        className={cn(
                                            'w-full bg-emerald-600',
                                            unanswered > 0 ? '' : 'rounded-t-[4px]',
                                        )}
                                        style={{ height: `${answeredPct}%` }}
                                        aria-hidden="true"
                                    />
                                </div>
                            );
                        })}
                    </div>

                    {/*
                      Three anchored labels instead of one per bar — a date needs
                      ~60px and a bar in a 30-day range gets ~20px, so per-bar
                      labels would truncate away to nothing.
                    */}
                    <div className="mt-1 flex justify-between text-[10px] text-muted-foreground">
                        {axisLabels.map((label, index) => (
                            <span
                                key={`${label}-${index}`}
                                className={cn(
                                    index === 0 && 'text-left',
                                    index === axisLabels.length - 1 && 'text-right',
                                )}
                            >
                                {label}
                            </span>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}

/**
 * Inline magnitude bar for table rows — one hue, share of the row maximum.
 * The number it encodes is always printed next to it.
 */
export function ProportionBar({ value, max }: { value: number; max: number }) {
    const pct = max > 0 ? Math.max(2, (value / max) * 100) : 0;

    return (
        <div className="h-1.5 w-full min-w-16 overflow-hidden rounded-full bg-muted" aria-hidden="true">
            <div className="h-full rounded-full bg-primary" style={{ width: `${pct}%` }} />
        </div>
    );
}
