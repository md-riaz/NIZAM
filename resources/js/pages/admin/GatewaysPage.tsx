import { useQuery } from '@tanstack/react-query';
import { Plus, Globe, Pencil, Trash2 } from 'lucide-react';

import api from '@/lib/api';
import { useTenant } from '@/context/TenantContext';
import type { Gateway } from '@/types/models';
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

export default function GatewaysPage() {
    const { activeTenant, tenantApiPrefix } = useTenant();

    const { data: gateways = [], isLoading } = useQuery({
        queryKey: ['gateways', activeTenant?.id],
        queryFn: async () => {
            const res = await api.get<{ data: Gateway[] }>(
                `${tenantApiPrefix}/gateways`,
            );
            return res.data.data;
        },
        enabled: !!activeTenant,
    });

    if (!activeTenant) {
        return (
            <div className="flex h-64 items-center justify-center text-muted-foreground">
                Select a tenant to view gateways.
            </div>
        );
    }

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div className="flex items-center justify-between">
                <div>
                    <p className="text-sm text-muted-foreground">
                        {activeTenant.name} &rsaquo; Connectivity
                    </p>
                    <h1 className="text-2xl font-bold tracking-tight">Gateways</h1>
                    <p className="text-muted-foreground">
                        SIP trunk gateways for outbound and inbound connectivity.
                    </p>
                </div>
                <Button>
                    <Plus className="size-4" />
                    Add Gateway
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>All Gateways</CardTitle>
                    <CardDescription>
                        {gateways.length} gateways configured for {activeTenant.domain}
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
                                    <TableHead>Name</TableHead>
                                    <TableHead>Proxy</TableHead>
                                    <TableHead>Register</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {gateways.map((gw) => (
                                    <TableRow key={gw.id}>
                                        <TableCell className="font-medium">
                                            <div className="flex items-center gap-2">
                                                <Globe className="size-4 text-muted-foreground" />
                                                {gw.name}
                                            </div>
                                        </TableCell>
                                        <TableCell className="font-mono text-sm text-muted-foreground">
                                            {gw.proxy ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            {gw.register ? (
                                                <Badge variant="default">Yes</Badge>
                                            ) : (
                                                <Badge variant="secondary">No</Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="success">Active</Badge>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button variant="ghost" size="icon">
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <Button variant="ghost" size="icon">
                                                    <Trash2 className="size-4 text-destructive" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {gateways.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={5} className="h-24 text-center text-muted-foreground">
                                            No gateways configured.
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
