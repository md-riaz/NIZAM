import { useQuery } from '@tanstack/react-query';
import { Clock, Download, User } from 'lucide-react';

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
import type { AuditLog } from '@/types/models';

function eventBadge(action?: string | null) {
    switch (action) {
        case 'created':
            return <Badge variant="success">CREATE</Badge>;
        case 'updated':
            return <Badge variant="default">UPDATE</Badge>;
        case 'deleted':
            return <Badge variant="destructive">DELETE</Badge>;
        default:
            return <Badge variant="secondary">{(action ?? 'unknown').toUpperCase()}</Badge>;
    }
}

export default function AuditLogsPage() {
    const { activeTenant, tenantApiPrefix } = useTenant();

    const { data: logs = [], isLoading } = useQuery({
        queryKey: ['audit-logs', activeTenant?.id],
        queryFn: async () => {
            const res = await api.get<{ data: AuditLog[] }>(
                `${tenantApiPrefix}/audit-logs`,
            );
            return res.data.data;
        },
        enabled: !!activeTenant,
    });

    if (!activeTenant) {
        return (
            <div className="flex h-64 items-center justify-center text-muted-foreground">
                Select a tenant to view audit logs.
            </div>
        );
    }

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div className="flex items-center justify-between">
                <div>
                    <p className="text-sm text-muted-foreground">
                        {activeTenant.name} &rsaquo; System
                    </p>
                    <h1 className="text-2xl font-bold tracking-tight">Audit Log</h1>
                    <p className="text-muted-foreground">
                        Immutable ledger of all configuration changes and
                        administrative actions.
                    </p>
                </div>
                <Button variant="outline">
                    <Download className="size-4" />
                    Export CSV
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Recent Activity</CardTitle>
                    <CardDescription>
                        Showing recent audit entries for {activeTenant.domain}
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
                                    <TableHead>Timestamp</TableHead>
                                    <TableHead>Actor</TableHead>
                                    <TableHead>Object</TableHead>
                                    <TableHead>Action</TableHead>
                                    <TableHead>Change Delta</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {logs.map((log) => (
                                    <TableRow key={log.id}>
                                        <TableCell className="whitespace-nowrap text-muted-foreground">
                                            <div className="flex items-center gap-2">
                                                <Clock className="size-3 text-muted-foreground" />
                                                {new Date(log.created_at).toLocaleString()}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <User className="size-4 text-muted-foreground" />
                                                <span className="text-sm">
                                                    {log.user_id ?? 'System'}
                                                </span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div>
                                                <span className="font-medium">
                                                    {log.auditable_type.split('\\').pop()}
                                                </span>
                                                <span className="ml-1 text-xs text-muted-foreground">
                                                    #{log.auditable_id}
                                                </span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {eventBadge(log.action)}
                                        </TableCell>
                                        <TableCell className="max-w-xs truncate text-xs text-muted-foreground">
                                            {log.new_values
                                                ? JSON.stringify(log.new_values).slice(0, 80)
                                                : '—'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {logs.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={5} className="h-24 text-center text-muted-foreground">
                                            No audit entries found.
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
