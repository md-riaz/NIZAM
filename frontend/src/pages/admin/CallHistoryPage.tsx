import { useQuery } from '@tanstack/react-query';
import {
    ArrowRight,
    CheckCircle2,
    Clock3,
    History,
    PhoneCall,
    PhoneIncoming,
    PhoneOutgoing,
    Route,
} from 'lucide-react';
import { Link } from 'react-router-dom';

import { PageHeader } from '@/components/scaffolds/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTenant } from '@/context/TenantContext';
import api from '@/lib/api';
import type { CallSessionSummary } from '@/types/models';

function formatDateTime(value: string | null | undefined): string {
    if (!value) return '—';

    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}

function formatStatus(value: string | null | undefined): string {
    if (!value) return 'Unknown';

    return value
        .replace(/[._]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

function statusVariant(value: string | null | undefined): 'success' | 'warning' | 'destructive' | 'secondary' {
    const normalized = value?.toLowerCase();

    if (!normalized) return 'secondary';
    if (['bridged', 'completed', 'won', 'answered', 'success'].includes(normalized)) return 'success';
    if (['failed', 'error', 'missed', 'abandoned'].includes(normalized)) return 'destructive';
    if (['pending', 'ringing', 'queued', 'processing'].includes(normalized)) return 'warning';

    return 'secondary';
}

function directionIcon(direction: string | null | undefined) {
    switch (direction?.toLowerCase()) {
        case 'inbound':
            return <PhoneIncoming className="size-4 text-emerald-600" />;
        case 'outbound':
            return <PhoneOutgoing className="size-4 text-blue-600" />;
        default:
            return <PhoneCall className="size-4 text-muted-foreground" />;
    }
}

function sessionOutcome(session: CallSessionSummary): string {
    const winningAttempt = session.winner?.attempt;

    if (!winningAttempt) {
        return 'No winner recorded';
    }

    const channel = [winningAttempt.endpoint?.platform, winningAttempt.endpoint?.type]
        .filter(Boolean)
        .join(' ')
        .trim();

    return channel ? `${formatStatus(winningAttempt.attempt_type)} via ${formatStatus(channel)}` : formatStatus(winningAttempt.attempt_type);
}

export default function CallHistoryPage() {
    const { activeTenant, tenantApiPrefix } = useTenant();

    const { data: sessions = [], isLoading } = useQuery({
        queryKey: ['calls', activeTenant?.id],
        queryFn: async () => {
            const response = await api.get<{ data: CallSessionSummary[] }>(`${tenantApiPrefix}/calls`);
            return response.data.data;
        },
        enabled: Boolean(activeTenant),
    });

    if (!activeTenant) {
        return (
            <div className="flex h-64 items-center justify-center text-muted-foreground">
                Select a tenant to view call history.
            </div>
        );
    }

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <PageHeader
                title="Call History"
                description="Unified view of all calls and their interaction journeys."
                breadcrumbs={`${activeTenant.name} › Calls`}
            />

            <div className="grid gap-4 md:grid-cols-4">
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">Total Calls</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">{sessions.length}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">Answered</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-emerald-600">
                            {sessions.filter((s) => s.winner?.attempt_id).length}
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">Missed</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-destructive">
                            {sessions.filter((s) => s.state === 'missed').length}
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">Latest Activity</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-sm font-medium">
                            {formatDateTime(sessions[0]?.started_at ?? sessions[0]?.created_at)}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <History className="size-5 text-primary" />
                        Recent Interactions
                    </CardTitle>
                    <CardDescription>
                        Click View Journey to see the detailed linear timeline of any call.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {isLoading ? (
                        <div className="flex h-32 items-center justify-center">
                            <div className="size-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-10"></TableHead>
                                    <TableHead>Call Identity</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Outcome</TableHead>
                                    <TableHead>Time</TableHead>
                                    <TableHead className="text-right">Action</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {sessions.map((session) => (
                                    <TableRow key={session.id}>
                                        <TableCell>
                                            {directionIcon(null)} {/* direction field coming from CDR reconciliation soon */}
                                        </TableCell>
                                        <TableCell>
                                            <div className="space-y-0.5">
                                                <div className="flex items-center gap-2 font-medium">
                                                    <span>{session.call_uuid.substring(0, 13)}...</span>
                                                </div>
                                                <p className="font-mono text-[10px] text-muted-foreground">
                                                    {session.id}
                                                </p>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant={statusVariant(session.state)}>
                                                {formatStatus(session.state)}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2 text-sm">
                                                {session.winner?.attempt_id ? (
                                                    <CheckCircle2 className="size-3.5 text-emerald-600" />
                                                ) : (
                                                    <Clock3 className="size-3.5 text-muted-foreground" />
                                                )}
                                                <span className="truncate max-w-[200px]">{sessionOutcome(session)}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-xs text-muted-foreground">
                                            {formatDateTime(session.started_at)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button variant="ghost" size="sm" asChild className="h-8">
                                                <Link to={`/admin/interactions/${session.id}`}>
                                                    View Journey
                                                    <ArrowRight className="ml-1 size-3.5" />
                                                </Link>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {sessions.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                                            No call history found.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
