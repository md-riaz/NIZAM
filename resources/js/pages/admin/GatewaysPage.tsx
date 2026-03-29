import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Globe, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
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
import type { Gateway } from '@/types/models';

export default function GatewaysPage() {
    const { activeTenant, tenantApiPrefix } = useTenant();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [gatewayToDelete, setGatewayToDelete] = useState<Gateway | null>(null);

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

    const deleteMutation = useMutation({
        mutationFn: async (id: string) => {
            return api.delete(`${tenantApiPrefix}/gateways/${id}`);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['gateways'] });
            setGatewayToDelete(null);
        },
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
                <Button onClick={() => navigate('/admin/gateways/create')}>
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
                                    <TableHead>SIP Server</TableHead>
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
                                            {gw.host ?? '—'}
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
                                                <Button 
                                                    variant="ghost" 
                                                    size="icon"
                                                    onClick={() => navigate(`/admin/gateways/${gw.id}/edit`)}
                                                >
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <Button 
                                                    variant="ghost" 
                                                    size="icon"
                                                    onClick={() => setGatewayToDelete(gw)}
                                                >
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

            <AlertDialog open={!!gatewayToDelete} onOpenChange={(open) => !open && setGatewayToDelete(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Are you sure?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will permanently delete the gateway "{gatewayToDelete?.name}". This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction 
                            onClick={() => gatewayToDelete && deleteMutation.mutate(gatewayToDelete.id)}
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            {deleteMutation.isPending ? 'Deleting...' : 'Delete'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
