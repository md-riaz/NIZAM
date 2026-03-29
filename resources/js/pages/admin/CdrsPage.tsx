import { useQuery } from '@tanstack/react-query';
import {
    Download,
    PhoneIncoming,
    PhoneOutgoing,
    Clock,
} from 'lucide-react';

import api from '@/lib/api';
import { useTenant } from '@/context/TenantContext';
import type { Cdr } from '@/types/models';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
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

function formatDuration(seconds: number | null | undefined): string {
    if (!seconds) return '0:00';
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
}

function directionIcon(direction: string | null | undefined) {
    switch (direction) {
        case 'inbound':
            return <PhoneIncoming className="size-4 text-emerald-600" />;
        case 'outbound':
            return <PhoneOutgoing className="size-4 text-blue-600" />;
        default:
            return <Clock className="size-4 text-muted-foreground" />;
    }
}

function hangupBadge(cause: string | null | undefined) {
    if (!cause) return <Badge variant="secondary">Unknown</Badge>;
    switch (cause) {
        case 'NORMAL_CLEARING':
            return <Badge variant="success">Completed</Badge>;
        case 'NO_ANSWER':
        case 'NO_USER_RESPONSE':
            return <Badge variant="warning">No Answer</Badge>;
        case 'USER_BUSY':
            return <Badge variant="warning">Busy</Badge>;
        default:
            return <Badge variant="destructive">{cause}</Badge>;
    }
}

export default function CdrsPage() {
    const { activeTenant, tenantApiPrefix } = useTenant();

    const { data: cdrs = [], isLoading } = useQuery({
        queryKey: ['cdrs', activeTenant?.id],
        queryFn: async () => {
            const res = await api.get<{ data: Cdr[] }>(
                `${tenantApiPrefix}/cdrs`,
            );
            return res.data.data;
        },
        enabled: !!activeTenant,
    });

    if (!activeTenant) {
        return (
            <div className="flex h-64 items-center justify-center text-muted-foreground">
                Select a tenant to view call records.
            </div>
        );
    }

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div className="flex items-center justify-between">
                <div>
                    <p className="text-sm text-muted-foreground">
                        {activeTenant.name} &rsaquo; Calls
                    </p>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Call Detail Records
                    </h1>
                    <p className="text-muted-foreground">
                        Historical call logs and billing records.
                    </p>
                </div>
                <Button variant="outline">
                    <Download className="size-4" />
                    Export CSV
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Recent Calls</CardTitle>
                    <CardDescription>
                        Showing the latest call records for {activeTenant.domain}
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
                                    <TableHead>Caller</TableHead>
                                    <TableHead>Destination</TableHead>
                                    <TableHead>Duration</TableHead>
                                    <TableHead>Result</TableHead>
                                    <TableHead>Date</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {cdrs.map((cdr) => (
                                    <TableRow key={cdr.id}>
                                        <TableCell>
                                            {directionIcon(cdr.direction)}
                                        </TableCell>
                                        <TableCell>
                                            <div>
                                                <span className="font-medium">
                                                    {cdr.caller_id_name ?? 'Unknown'}
                                                </span>
                                                <p className="font-mono text-xs text-muted-foreground">
                                                    {cdr.caller_id_number}
                                                </p>
                                            </div>
                                        </TableCell>
                                        <TableCell className="font-mono">
                                            {cdr.destination_number}
                                        </TableCell>
                                        <TableCell>
                                            {formatDuration(cdr.billsec)}
                                        </TableCell>
                                        <TableCell>
                                            {hangupBadge(cdr.hangup_cause)}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {cdr.start_stamp
                                                ? new Date(cdr.start_stamp).toLocaleString()
                                                : '—'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {cdrs.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                                            No call records found.
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
