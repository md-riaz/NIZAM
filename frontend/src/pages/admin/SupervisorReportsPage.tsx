import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';

import {
    KpiTile,
    ReportPanelState,
    ReportRangeBar,
    defaultReportRange,
    formatCount,
    formatDateTime,
    formatPercent,
    isForbiddenError,
    paginateReportItems,
    type ReportRange,
} from '@/components/admin/ReportPrimitives';
import { PageHeader } from '@/components/scaffolds/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { TablePagination } from '@/components/ui/table-pagination';
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
    MissedReturnedCallsReport,
    ReportReturnedCall,
    SupervisorCallSummaryReport,
    VoicemailsNeedingFollowUpReport,
} from '@/types/models';

interface SupervisorFilters extends ReportRange {
    /** Blank means "use the server's configured default window". */
    window_days: string;
}

function initialFilters(): SupervisorFilters {
    return { ...defaultReportRange(), window_days: '' };
}

/** `direction` is nullable, so a blank grouping key must not render as an empty label. */
function humanizeKey(key: string): string {
    const spaced = key.replace(/[_-]+/g, ' ').trim();

    if (spaced === '') return 'Unknown';

    return spaced.charAt(0).toUpperCase() + spaced.slice(1);
}

/** The outbound call that closed the loop, or a badge saying nothing did. */
function ReturnedCallCell({ returnedCall }: { returnedCall: ReportReturnedCall | null }) {
    if (!returnedCall) {
        return <Badge variant="warning">Not returned</Badge>;
    }

    return (
        <div>
            <Badge variant="success">Returned</Badge>
            <div className="mt-1 text-xs text-muted-foreground">
                {formatDateTime(returnedCall.started_at)}
                {returnedCall.destination_number ? (
                    <> · to {returnedCall.destination_number}</>
                ) : null}
            </div>
        </div>
    );
}

export default function SupervisorReportsPage() {
    const { activeOrganization, organizationApiPrefix } = useOrganization();

    const [draft, setDraft] = useState<SupervisorFilters>(initialFilters);
    const [applied, setApplied] = useState<SupervisorFilters>(initialFilters);
    const [missedPage, setMissedPage] = useState(1);
    const [voicemailPage, setVoicemailPage] = useState(1);

    const enabled = Boolean(activeOrganization);
    const rangeParams = { date_from: applied.date_from, date_to: applied.date_to };

    // `window_days` is only read by the missed/returned and voicemail endpoints;
    // the call summary would silently ignore it, so it is not sent there.
    const windowParams = applied.window_days
        ? { ...rangeParams, window_days: applied.window_days }
        : rangeParams;

    const callSummaryQuery = useQuery({
        queryKey: ['supervisor-reports', 'call-summary', activeOrganization?.id, rangeParams],
        queryFn: async () => {
            const response = await api.get<{ data: SupervisorCallSummaryReport }>(
                `${organizationApiPrefix}/supervisor-reports/call-summary`,
                { params: rangeParams },
            );
            return response.data.data;
        },
        enabled,
    });

    const missedQuery = useQuery({
        queryKey: ['supervisor-reports', 'missed-returned', activeOrganization?.id, windowParams],
        queryFn: async () => {
            const response = await api.get<{ data: MissedReturnedCallsReport }>(
                `${organizationApiPrefix}/supervisor-reports/missed-returned-calls`,
                { params: windowParams },
            );
            return response.data.data;
        },
        enabled,
    });

    // This endpoint authorizes against `Recording` (recordings.view) while the two
    // above authorize against `CallDetailRecord` (cdrs.view), so a 403 here is an
    // expected outcome for a supervisor who can see calls but not recordings.
    // Retrying it would just repeat a denial, so retry is off.
    const voicemailQuery = useQuery({
        queryKey: ['supervisor-reports', 'voicemails', activeOrganization?.id, windowParams],
        queryFn: async () => {
            const response = await api.get<{ data: VoicemailsNeedingFollowUpReport }>(
                `${organizationApiPrefix}/supervisor-reports/voicemails-needing-follow-up`,
                { params: windowParams },
            );
            return response.data.data;
        },
        enabled,
        retry: false,
    });

    if (!activeOrganization) {
        return (
            <div className="flex h-64 items-center justify-center text-muted-foreground">
                Select an organization to view supervisor reports.
            </div>
        );
    }

    const callSummary = callSummaryQuery.data;
    const missed = missedQuery.data;
    const voicemails = voicemailQuery.data;

    const missedPageData = paginateReportItems(missed?.items ?? [], missedPage);
    const voicemailPageData = paginateReportItems(voicemails?.items ?? [], voicemailPage);

    const directionEntries = Object.entries(callSummary?.by_direction ?? {}).sort(
        ([, a], [, b]) => b - a,
    );

    const voicemailForbidden = isForbiddenError(voicemailQuery.error);

    const applyFilters = () => {
        setApplied(draft);
        setMissedPage(1);
        setVoicemailPage(1);
    };

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <PageHeader
                title="Supervisor Reports"
                description="Answer performance, missed calls awaiting a callback, and voicemails still needing follow-up."
                breadcrumbs={`${activeOrganization.name} › Reports`}
            />

            <ReportRangeBar
                idPrefix="supervisor"
                draft={draft}
                onDraftChange={(range) => setDraft({ ...draft, ...range })}
                onApply={applyFilters}
                onReset={() => {
                    const reset = initialFilters();
                    setDraft(reset);
                    setApplied(reset);
                    setMissedPage(1);
                    setVoicemailPage(1);
                }}
            >
                <div className="space-y-1.5">
                    <Label htmlFor="supervisor-window-days">Callback window (days)</Label>
                    <Input
                        id="supervisor-window-days"
                        type="number"
                        min={1}
                        placeholder={
                            missed ? String(missed.returned_call_window_days) : 'Server default'
                        }
                        value={draft.window_days}
                        onChange={(e) => setDraft({ ...draft, window_days: e.target.value })}
                    />
                    <p className="text-xs text-muted-foreground">
                        How long after a missed call an outbound call still counts as a callback.
                    </p>
                </div>
            </ReportRangeBar>

            {/* ─── Call summary ─────────────────────────────── */}
            <ReportPanelState
                isLoading={callSummaryQuery.isLoading}
                error={callSummaryQuery.error}
                isEmpty={false}
                loadingLabel="Loading call summary…"
                errorMessage="Could not load the call summary."
                emptyMessage="No calls in this period."
                forbiddenMessage="You do not have permission to view call records."
            >
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <KpiTile label="Total calls" value={formatCount(callSummary?.totals.calls)} />
                    <KpiTile
                        label="Answered"
                        value={formatCount(callSummary?.totals.answered_calls)}
                        hint={
                            callSummary
                                ? `${formatPercent(callSummary.totals.answer_rate)} answer rate`
                                : undefined
                        }
                        tone="positive"
                    />
                    <KpiTile
                        label="Missed inbound"
                        value={formatCount(callSummary?.totals.missed_calls)}
                        hint="Inbound calls that were never answered"
                        tone="warning"
                    />
                    <KpiTile
                        label="Voicemail calls"
                        value={formatCount(callSummary?.totals.voicemail_calls)}
                    />
                    <KpiTile
                        label="Talk time"
                        value={formatDuration(callSummary?.totals.total_billsec_seconds)}
                        hint={
                            callSummary
                                ? `${formatDuration(callSummary.totals.total_duration_seconds)} including ring time`
                                : undefined
                        }
                    />
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                By direction
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {directionEntries.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No calls in this period.
                                </p>
                            ) : (
                                <dl className="space-y-1">
                                    {directionEntries.map(([direction, count]) => (
                                        <div key={direction} className="flex justify-between gap-4">
                                            <dt className="text-sm text-muted-foreground">
                                                {humanizeKey(direction)}
                                            </dt>
                                            <dd className="text-sm font-medium">
                                                {formatCount(count)}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </ReportPanelState>

            {/* ─── Missed and returned calls ────────────────── */}
            <Card>
                <CardHeader>
                    <CardTitle>Missed and returned calls</CardTitle>
                    <CardDescription>
                        Every unanswered inbound call in the period, and whether someone called the
                        number back
                        {missed ? ` within ${missed.returned_call_window_days} days` : ''}.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    <ReportPanelState
                        isLoading={missedQuery.isLoading}
                        error={missedQuery.error}
                        isEmpty={(missed?.items.length ?? 0) === 0}
                        loadingLabel="Loading missed and returned calls…"
                        errorMessage="Could not load missed and returned calls."
                        emptyMessage="No inbound calls were missed in this period."
                        forbiddenMessage="You do not have permission to view call records."
                    >
                        <div className="grid gap-4 md:grid-cols-3">
                            <KpiTile
                                label="Missed calls"
                                value={formatCount(missed?.summary.missed_calls)}
                            />
                            <KpiTile
                                label="Called back"
                                value={formatCount(missed?.summary.returned_calls)}
                                tone="positive"
                            />
                            <KpiTile
                                label="Still open"
                                value={formatCount(missed?.summary.open_missed_calls)}
                                hint="No outbound call to this number inside the window"
                                tone="warning"
                            />
                        </div>

                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Caller</TableHead>
                                        <TableHead>Dialled</TableHead>
                                        <TableHead>Missed at</TableHead>
                                        <TableHead>Callback</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {missedPageData.rows.map((item) => (
                                        <TableRow key={item.cdr_id}>
                                            <TableCell className="font-mono text-sm">
                                                {item.caller_id_number || '—'}
                                            </TableCell>
                                            <TableCell className="font-mono text-sm">
                                                {item.destination_number || '—'}
                                            </TableCell>
                                            <TableCell className="text-xs text-muted-foreground">
                                                {formatDateTime(item.missed_at)}
                                            </TableCell>
                                            <TableCell>
                                                <ReturnedCallCell returnedCall={item.returned_call} />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <TablePagination
                            meta={missedPageData.meta}
                            onPageChange={setMissedPage}
                            itemLabel="missed calls"
                        />
                    </ReportPanelState>
                </CardContent>
            </Card>

            {/* ─── Voicemails needing follow-up ─────────────── */}
            <Card>
                <CardHeader>
                    <CardTitle>Voicemails needing follow-up</CardTitle>
                    {!voicemailForbidden && (
                        <CardDescription>
                            Voicemails left in the period, flagged when nobody called the number back
                            or the recording was marked for review.
                        </CardDescription>
                    )}
                </CardHeader>
                <CardContent className="space-y-6">
                    <ReportPanelState
                        isLoading={voicemailQuery.isLoading}
                        error={voicemailQuery.error}
                        isEmpty={(voicemails?.items.length ?? 0) === 0}
                        loadingLabel="Loading voicemails needing follow-up…"
                        errorMessage="Could not load voicemails needing follow-up."
                        emptyMessage="No voicemails were left in this period."
                        forbiddenMessage="You don't have permission to view voicemail follow-ups. This report reads recordings, which needs the recordings.view permission — the rest of this page only needs access to call records."
                    >
                        <div className="grid gap-4 md:grid-cols-4">
                            <KpiTile
                                label="Voicemails"
                                value={formatCount(voicemails?.summary.voicemails)}
                            />
                            <KpiTile
                                label="Pending follow-up"
                                value={formatCount(voicemails?.summary.pending_follow_up)}
                                tone="warning"
                            />
                            <KpiTile
                                label="Flagged for review"
                                value={formatCount(voicemails?.summary.needs_review)}
                            />
                            <KpiTile
                                label="Needs attention"
                                value={formatCount(voicemails?.summary.needs_attention)}
                                hint="Pending callback or flagged recording"
                                tone="negative"
                            />
                        </div>

                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Caller</TableHead>
                                        <TableHead>Mailbox</TableHead>
                                        <TableHead>Received</TableHead>
                                        <TableHead>Follow-up</TableHead>
                                        <TableHead>Recording</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {voicemailPageData.rows.map((item) => (
                                        <TableRow key={item.event_id}>
                                            <TableCell className="font-mono text-sm">
                                                {item.caller_id_number || '—'}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {item.mailbox || '—'}
                                            </TableCell>
                                            <TableCell className="text-xs text-muted-foreground">
                                                {formatDateTime(item.received_at)}
                                            </TableCell>
                                            <TableCell>
                                                <ReturnedCallCell returnedCall={item.returned_call} />
                                            </TableCell>
                                            <TableCell>
                                                {item.recording ? (
                                                    item.recording.needs_review ? (
                                                        <div>
                                                            <Badge variant="warning">
                                                                Needs review
                                                            </Badge>
                                                            {item.recording.review_reasons?.length ? (
                                                                <div className="mt-1 text-xs text-muted-foreground">
                                                                    {item.recording.review_reasons
                                                                        .map(humanizeKey)
                                                                        .join(', ')}
                                                                </div>
                                                            ) : null}
                                                        </div>
                                                    ) : (
                                                        <Badge variant="secondary">Reviewed</Badge>
                                                    )
                                                ) : (
                                                    <span
                                                        className="text-xs text-muted-foreground"
                                                        title="No recording was matched to this voicemail event."
                                                    >
                                                        —
                                                    </span>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <TablePagination
                            meta={voicemailPageData.meta}
                            onPageChange={setVoicemailPage}
                            itemLabel="voicemails"
                        />
                    </ReportPanelState>
                </CardContent>
            </Card>
        </div>
    );
}
