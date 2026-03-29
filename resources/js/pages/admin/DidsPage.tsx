import { useQuery } from '@tanstack/react-query';
import { Plus, Hash, Pencil, Trash2 } from 'lucide-react';

import api from '@/lib/api';
import { useTenant } from '@/context/TenantContext';
import type { Did } from '@/types/models';
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

export default function DidsPage() {
    const { activeTenant, tenantApiPrefix } = useTenant();

    const { data: dids = [], isLoading } = useQuery({
        queryKey: ['dids', activeTenant?.id],
        queryFn: async () => {
            const res = await api.get<{ data: Did[] }>(
                `${tenantApiPrefix}/dids`,
            );
            return res.data.data;
        },
        enabled: !!activeTenant,
    });

    if (!activeTenant) {
        return (
            <div className="flex h-64 items-center justify-center text-muted-foreground">
                Select a tenant to view DIDs.
            </div>
        );
    }

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div className="flex items-center justify-between">
                <div>
                    <p className="text-sm text-muted-foreground">
                        {activeTenant.name} &rsaquo; Routing
                    </p>
                    <h1 className="text-2xl font-bold tracking-tight">DIDs</h1>
                    <p className="text-muted-foreground">
                        Inbound phone numbers and their routing destinations.
                    </p>
                </div>
                <Button>
                    <Plus className="size-4" />
                    Add DID
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>All DIDs</CardTitle>
                    <CardDescription>
                        {dids.length} numbers assigned to {activeTenant.domain}
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
                                    <TableHead>Number</TableHead>
                                    <TableHead>Description</TableHead>
                                    <TableHead>Destination</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {dids.map((did) => (
                                    <TableRow key={did.id}>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <Hash className="size-4 text-muted-foreground" />
                                                <span className="font-mono font-semibold">
                                                    {did.number}
                                                </span>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {did.description ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            {did.destination_type ? (
                                                <Badge variant="outline">
                                                    {did.destination_type}
                                                </Badge>
                                            ) : (
                                                <span className="text-muted-foreground">Unrouted</span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant={did.enabled !== false ? 'success' : 'secondary'}>
                                                {did.enabled !== false ? 'Active' : 'Disabled'}
                                            </Badge>
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
                                {dids.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={5} className="h-24 text-center text-muted-foreground">
                                            No DIDs assigned.
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
