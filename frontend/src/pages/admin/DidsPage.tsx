import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { Plus, Hash, Pencil, Trash2 } from 'lucide-react';

import api from '@/lib/api';
import { useOrganization } from '@/context/OrganizationContext';
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

export default function DidsPage() {
    const { activeOrganization, organizationApiPrefix } = useOrganization();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [didToDelete, setDidToDelete] = useState<Did | null>(null);

    const { data: dids = [], isLoading } = useQuery({
        queryKey: ['dids', activeOrganization?.id],
        queryFn: async () => {
            const res = await api.get<{ data: Did[] }>(
                `${organizationApiPrefix}/dids`,
            );
            return res.data.data;
        },
        enabled: !!activeOrganization,
    });

    const deleteMutation = useMutation({
        mutationFn: async (id: string) => {
            return api.delete(`${organizationApiPrefix}/dids/${id}`);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['dids'] });
            setDidToDelete(null);
        },
    });

    if (!activeOrganization) {
        return (
            <div className="flex h-64 items-center justify-center text-muted-foreground">
                Select an organization to view numbers.
            </div>
        );
    }

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div className="flex items-center justify-between">
                <div>
                    <p className="text-sm text-muted-foreground">
                        {activeOrganization.name} &rsaquo; Routing
                    </p>
                    <h1 className="text-2xl font-bold tracking-tight">Numbers</h1>
                    <p className="text-muted-foreground">
                        Manage inbound phone numbers and where they route.
                    </p>
                </div>
                <Button onClick={() => navigate('/admin/numbers/create')}>
                    <Plus className="size-4" />
                    Add Number
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>All Numbers</CardTitle>
                    <CardDescription>
                        {dids.length} numbers assigned to {activeOrganization.domain}
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
                                                <Button 
                                                    variant="ghost" 
                                                    size="icon"
                                                    onClick={() => navigate(`/admin/numbers/${did.id}/edit`)}
                                                >
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <Button 
                                                    variant="ghost" 
                                                    size="icon"
                                                    onClick={() => setDidToDelete(did)}
                                                >
                                                    <Trash2 className="size-4 text-destructive" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {dids.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={5} className="h-24 text-center text-muted-foreground">
                                            No numbers assigned.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            <AlertDialog open={!!didToDelete} onOpenChange={(open) => !open && setDidToDelete(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Are you sure?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will permanently delete the number "{didToDelete?.number}". This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction 
                            onClick={() => didToDelete && deleteMutation.mutate(didToDelete.id)}
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
