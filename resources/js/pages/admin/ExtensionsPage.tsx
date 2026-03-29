import { useQuery } from '@tanstack/react-query';
import { Plus, Phone as PhoneIcon, Pencil, Trash2, Copy } from 'lucide-react';

import api from '@/lib/api';
import { useTenant } from '@/context/TenantContext';
import type { Extension } from '@/types/models';
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

export default function ExtensionsPage() {
    const { activeTenant, tenantApiPrefix } = useTenant();

    const { data: extensions = [], isLoading } = useQuery({
        queryKey: ['extensions', activeTenant?.id],
        queryFn: async () => {
            const res = await api.get<{ data: Extension[] }>(
                `${tenantApiPrefix}/extensions`,
            );
            return res.data.data;
        },
        enabled: !!activeTenant,
    });

    // Fetch registration status
    const { data: statusMap = {} } = useQuery({
        queryKey: ['extension-status', activeTenant?.id],
        queryFn: async () => {
            const res = await api.get<Record<string, { status: string; ip?: string; user_agent?: string }>>(
                `${tenantApiPrefix}/extensions/status/all`,
            );
            return res.data;
        },
        enabled: !!activeTenant,
        refetchInterval: 15_000,
    });

    if (!activeTenant) {
        return (
            <div className="flex h-64 items-center justify-center text-muted-foreground">
                Select a tenant to view extensions.
            </div>
        );
    }

    const registeredCount = Object.values(statusMap).filter(
        (s) => s.status === 'registered',
    ).length;

    return (
        <div className="space-y-6 p-6 lg:p-8">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div>
                    <p className="text-sm text-muted-foreground">
                        {activeTenant.name} &rsaquo; Phone System
                    </p>
                    <h1 className="text-2xl font-bold tracking-tight">Extensions</h1>
                    <p className="text-muted-foreground">
                        Manage and provision internal SIP extensions for{' '}
                        {activeTenant.domain}.
                    </p>
                </div>
                <Button>
                    <Plus className="size-4" />
                    Create Extension
                </Button>
            </div>

            {/* Stats */}
            <div className="grid gap-4 sm:grid-cols-3">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">
                            Total Capacity
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">{extensions.length}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">
                            Online Now
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-emerald-600">
                            {registeredCount}
                        </div>
                        <p className="text-xs text-muted-foreground">Active SIP links</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">
                            Offline
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-amber-600">
                            {extensions.length - registeredCount}
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Table */}
            <Card>
                <CardHeader>
                    <CardTitle>All Extensions</CardTitle>
                    <CardDescription>
                        Showing {extensions.length} extensions for {activeTenant.domain}
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
                                    <TableHead>Extension</TableHead>
                                    <TableHead>Display Name</TableHead>
                                    <TableHead>Caller ID</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>IP / Agent</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {extensions.map((ext) => {
                                    const status = statusMap[ext.extension];
                                    const isOnline = status?.status === 'registered';
                                    return (
                                        <TableRow key={ext.id}>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <div className="flex size-8 items-center justify-center rounded-lg bg-primary/10">
                                                        <PhoneIcon className="size-4 text-primary" />
                                                    </div>
                                                    <span className="font-mono font-semibold text-primary">
                                                        {ext.extension}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {ext.effective_caller_id_name ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {ext.effective_caller_id_number ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {isOnline ? (
                                                    <Badge variant="success">Registered</Badge>
                                                ) : (
                                                    <Badge variant="secondary">Unregistered</Badge>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-xs text-muted-foreground">
                                                {status?.ip ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-1">
                                                    <Button variant="ghost" size="icon">
                                                        <Pencil className="size-4" />
                                                    </Button>
                                                    <Button variant="ghost" size="icon">
                                                        <Copy className="size-4" />
                                                    </Button>
                                                    <Button variant="ghost" size="icon">
                                                        <Trash2 className="size-4 text-destructive" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                                {extensions.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                                            No extensions provisioned. Create your first extension to get started.
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
