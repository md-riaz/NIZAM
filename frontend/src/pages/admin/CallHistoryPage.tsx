import { useQuery } from '@tanstack/react-query';
import {
    ArrowRight,
    Download,
    PhoneCall,
    PhoneIncoming,
    PhoneOutgoing,
    Search,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { Link } from 'react-router-dom';
import { toast } from 'sonner';

import { RecordingPlayer } from '@/components/admin/RecordingPlayer';
import { PageHeader } from '@/components/scaffolds/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/ui/pagination';
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
import { downloadAuthenticatedFile, formatDuration } from '@/lib/media';
import type { Cdr, CdrSummary, PaginationMeta } from '@/types/models';

interface Filters {
    search: string;
    direction: string;
    date_from: string;
    date_to: string;
}

const EMPTY_FILTERS: Filters = {
    search: '',
    direction: 'any',
    date_from: '',
    date_to: '',
};

/** Strip UI-only sentinels before sending filters to the API. */
function toQueryParams(filters: Filters): Record<string, string> {
    const params: Record<string, string> = {};

    if (filters.search) params.search = filters.search;
    if (filters.direction && filters.direction !== 'any') params.direction = filters.direction;
    if (filters.date_from) params.date_from = filters.date_from;
    if (filters.date_to) params.date_to = filters.date_to;

    return params;
}

function formatDateTime(value?: string | null): string {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}

function directionIcon(direction?: string | null) {
    switch (direction?.toLowerCase()) {
        case 'inbound':
            return <PhoneIncoming className="size-4 text-emerald-600" />;
        case 'outbound':
            return <PhoneOutgoing className="size-4 text-blue-600" />;
        default:
            return <PhoneCall className="size-4 text-muted-foreground" />;
    }
}

/**
 * Translate a FreeSWITCH hangup cause into something a phone-system admin can
 * act on. The raw causes are SIP-flavoured and mean little to most operators.
 */
function describeResult(cdr: Cdr): { label: string; variant: 'success' | 'warning' | 'destructive' | 'secondary' } {
    if (cdr.answer_stamp) {
        return { label: 'Answered', variant: 'success' };
    }

    switch ((cdr.hangup_cause ?? '').toUpperCase()) {
        case 'NO_ANSWER':
        case 'ALLOTTED_TIMEOUT':
            return { label: 'No answer', variant: 'warning' };
        case 'USER_BUSY':
            return { label: 'Busy', variant: 'warning' };
        case 'ORIGINATOR_CANCEL':
            return { label: 'Caller hung up', variant: 'secondary' };
        case 'CALL_REJECTED':
            return { label: 'Rejected', variant: 'destructive' };
        case 'UNALLOCATED_NUMBER':
            return { label: 'No such number', variant: 'destructive' };
        case 'NO_ROUTE_DESTINATION':
            return { label: 'No route', variant: 'destructive' };
        case 'NORMAL_CLEARING':
            return { label: 'Not answered', variant: 'secondary' };
        case '':
            return { label: 'Unknown', variant: 'secondary' };
        default:
            return { label: 'Failed', variant: 'destructive' };
    }
}

/** Ring time is the gap between the call starting and being answered. */
function ringSeconds(cdr: Cdr): number | null {
    if (!cdr.start_stamp || !cdr.answer_stamp) return null;

    const start = new Date(cdr.start_stamp).getTime();
    const answer = new Date(cdr.answer_stamp).getTime();

    if (Number.isNaN(start) || Number.isNaN(answer)) return null;

    return Math.max(0, Math.round((answer - start) / 1000));
}

export default function CallHistoryPage() {
    const { activeOrganization, organizationApiPrefix } = useOrganization();

    const [draft, setDraft] = useState<Filters>(EMPTY_FILTERS);
    const [applied, setApplied] = useState<Filters>(EMPTY_FILTERS);
    const [page, setPage] = useState(1);
    const [isExporting, setIsExporting] = useState(false);

    const params = toQueryParams(applied);

    const { data, isLoading, isError } = useQuery({
        queryKey: ['cdrs', activeOrganization?.id, applied, page],
        queryFn: async () => {
            const response = await api.get<{ data: Cdr[]; meta?: PaginationMeta }>(
                `${organizationApiPrefix}/cdrs`,
                { params: { ...params, page } },
            );
            return response.data;
        },
        enabled: Boolean(activeOrganization),
    });

    // Counters come from the server-side aggregate rather than the current page,
    // which is why they can exceed the page size.
    const { data: summary } = useQuery({
        queryKey: ['cdr-summary', activeOrganization?.id, applied],
        queryFn: async () => {
            const response = await api.get<{ data: CdrSummary }>(
                `${organizationApiPrefix}/cdrs/analytics/summary`,
                { params: { date_from: params.date_from, date_to: params.date_to } },
            );
            return response.data.data;
        },
        enabled: Boolean(activeOrganization),
    });

    const applyFilters = () => {
        setApplied(draft);
        setPage(1);
    };

    const clearFilters = () => {
        setDraft(EMPTY_FILTERS);
        setApplied(EMPTY_FILTERS);
        setPage(1);
    };

    const exportCsv = async () => {
        setIsExporting(true);
        try {
            await downloadAuthenticatedFile(
                `${organizationApiPrefix}/cdrs/export`,
                `call-history-${new Date().toISOString().slice(0, 10)}.csv`,
                { ...params, format: 'csv' },
            );
        } catch {
            toast.error('Could not export call history.');
        } finally {
            setIsExporting(false);
        }
    };

    if (!activeOrganization) {
        return (
            <div className="flex h-64 items-center justify-center text-muted-foreground">
                Select an organization to view call history.
            </div>
        );
    }

    const cdrs = data?.data ?? [];
    const hasFilters = Object.keys(params).length > 0;

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <PageHeader
                title="Call History"
                description="Every call in and out of this organization, with recordings where available."
                breadcrumbs={`${activeOrganization.name} › Calls`}
            />

            <div className="grid gap-4 md:grid-cols-4">
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">Total Calls</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">
                            {summary ? summary.total_calls.toLocaleString() : '—'}
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">Answered</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-emerald-600">
                            {summary ? summary.answered_calls.toLocaleString() : '—'}
                        </div>
                        {summary ? (
                            <p className="text-xs text-muted-foreground">{summary.asr}% answer rate</p>
                        ) : null}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">Missed</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-amber-600">
                            {summary ? summary.missed_calls.toLocaleString() : '—'}
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">
                            Avg talk time
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">
                            {summary ? formatDuration(summary.acd_seconds) : '—'}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardContent className="pt-6">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="search">Number or name</Label>
                            <Input
                                id="search"
                                value={draft.search}
                                placeholder="Caller, callee, or UUID"
                                onChange={(e) => setDraft({ ...draft, search: e.target.value })}
                                onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="direction">Direction</Label>
                            <Select
                                value={draft.direction}
                                onValueChange={(value) => setDraft({ ...draft, direction: value })}
                            >
                                <SelectTrigger id="direction">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="any">Any direction</SelectItem>
                                    <SelectItem value="inbound">Inbound</SelectItem>
                                    <SelectItem value="outbound">Outbound</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="date_from">From date</Label>
                            <Input
                                id="date_from"
                                type="date"
                                value={draft.date_from}
                                onChange={(e) => setDraft({ ...draft, date_from: e.target.value })}
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="date_to">To date</Label>
                            <Input
                                id="date_to"
                                type="date"
                                value={draft.date_to}
                                onChange={(e) => setDraft({ ...draft, date_to: e.target.value })}
                            />
                        </div>
                    </div>

                    <div className="mt-4 flex flex-wrap items-center gap-2">
                        <Button onClick={applyFilters} size="sm">
                            <Search className="size-4" />
                            Apply filters
                        </Button>
                        {hasFilters && (
                            <Button onClick={clearFilters} size="sm" variant="ghost">
                                <X className="size-4" />
                                Clear
                            </Button>
                        )}
                        <Button
                            onClick={() => void exportCsv()}
                            size="sm"
                            variant="outline"
                            disabled={isExporting}
                            className="ml-auto"
                        >
                            <Download className="size-4" />
                            {isExporting ? 'Exporting…' : 'Export CSV'}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="pt-6">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-10" />
                                <TableHead>From</TableHead>
                                <TableHead>To</TableHead>
                                <TableHead>Started</TableHead>
                                <TableHead>Ring</TableHead>
                                <TableHead>Talk</TableHead>
                                <TableHead>Result</TableHead>
                                <TableHead>Recording</TableHead>
                                <TableHead className="text-right">Journey</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {isLoading ? (
                                <TableRow>
                                    <TableCell colSpan={9} className="py-10 text-center">
                                        <div className="mx-auto size-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                                    </TableCell>
                                </TableRow>
                            ) : isError ? (
                                <TableRow>
                                    <TableCell colSpan={9} className="py-10 text-center text-sm text-destructive">
                                        Could not load call history.
                                    </TableCell>
                                </TableRow>
                            ) : cdrs.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={9} className="py-10 text-center text-muted-foreground">
                                        {hasFilters
                                            ? 'No calls match these filters.'
                                            : 'No calls recorded yet. Call records appear here once the CDR watcher has ingested them.'}
                                    </TableCell>
                                </TableRow>
                            ) : (
                                cdrs.map((cdr) => {
                                    const result = describeResult(cdr);
                                    const ring = ringSeconds(cdr);
                                    const recording = cdr.recordings?.[0];

                                    return (
                                        <TableRow key={cdr.id}>
                                            <TableCell title={cdr.direction ?? 'Unknown direction'}>
                                                {directionIcon(cdr.direction)}
                                            </TableCell>
                                            <TableCell>
                                                <div className="font-mono text-sm">
                                                    {cdr.caller_id_number || '—'}
                                                </div>
                                                {cdr.caller_id_name ? (
                                                    <div className="text-xs text-muted-foreground">
                                                        {cdr.caller_id_name}
                                                    </div>
                                                ) : null}
                                            </TableCell>
                                            <TableCell className="font-mono text-sm">
                                                {cdr.destination_number || '—'}
                                            </TableCell>
                                            <TableCell className="text-xs text-muted-foreground">
                                                {formatDateTime(cdr.start_stamp)}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {ring === null ? '—' : formatDuration(ring)}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {formatDuration(cdr.billsec)}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={result.variant}
                                                    title={cdr.hangup_cause ?? undefined}
                                                >
                                                    {result.label}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {recording ? (
                                                    <RecordingPlayer
                                                        downloadUrl={`${organizationApiPrefix}/recordings/${recording.id}/download`}
                                                        format={recording.format}
                                                        compact
                                                    />
                                                ) : cdr.has_recording ? (
                                                    <span
                                                        className="text-xs text-muted-foreground"
                                                        title="FreeSWITCH recorded this call but the file has not been ingested yet."
                                                    >
                                                        Pending
                                                    </span>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">—</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {cdr.call_session_id ? (
                                                    <Button variant="ghost" size="sm" asChild className="h-8">
                                                        <Link to={`/admin/interactions/${cdr.call_session_id}`}>
                                                            View
                                                            <ArrowRight className="ml-1 size-3.5" />
                                                        </Link>
                                                    </Button>
                                                ) : (
                                                    <span
                                                        className="text-xs text-muted-foreground"
                                                        title="This call was not traced through the delivery pipeline."
                                                    >
                                                        —
                                                    </span>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>

                    <Pagination meta={data?.meta} onPageChange={setPage} itemLabel="calls" />
                </CardContent>
            </Card>
        </div>
    );
}
