import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { GitBranch, Plus, SquarePen, Trash2 } from 'lucide-react';
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
import type { RingGroup } from '@/types/models';

export default function RingGroupsPage() {
    const { activeTenant, tenantApiPrefix } = useTenant();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [groupToDelete, setGroupToDelete] = useState<RingGroup | null>(null);

    const { data: groups = [], isLoading } = useQuery({
        queryKey: ['ring-groups', activeTenant?.id],
        queryFn: async () => {
            const res = await api.get<{ data: RingGroup[] }>(`${tenantApiPrefix}/ring-groups`);
            return res.data.data;
        },
        enabled: !!activeTenant,
    });

    const deleteMutation = useMutation({
        mutationFn: async (id: string) => {
            await api.delete(`${tenantApiPrefix}/ring-groups/${id}`);
        },
        onSuccess: async () => {
            await queryClient.invalidateQueries({ queryKey: ['ring-groups', activeTenant?.id] });
            setGroupToDelete(null);
        },
    });

    if (!activeTenant) {
        return (
            <div className="flex h-64 items-center justify-center text-muted-foreground">
                Select a tenant to view ring groups.
            </div>
        );
    }

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div className="flex items-center justify-between">
                <div>
                    <p className="text-sm text-muted-foreground">{activeTenant.name} &rsaquo; Routing</p>
                    <h1 className="text-2xl font-bold tracking-tight">Ring Groups</h1>
                    <p className="text-muted-foreground">
                        Simultaneous and sequential ring strategies for call distribution.
                    </p>
                </div>
                <Button onClick={() => navigate('/admin/ring-groups/create')}>
                    <Plus className="size-4" />
                    Create Ring Group
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>All Ring Groups</CardTitle>
                    <CardDescription>{groups.length} ring groups for {activeTenant.domain}</CardDescription>
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
                                    <TableHead>Strategy</TableHead>
                                    <TableHead>Timeout</TableHead>
                                    <TableHead>Members</TableHead>
                                    <TableHead>Created</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {groups.map((group) => (
                                    <TableRow key={group.id}>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <GitBranch className="size-4 text-muted-foreground" />
                                                <span className="font-medium">{group.name}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{group.strategy}</Badge>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">{group.ring_timeout ?? 0}s</TableCell>
                                        <TableCell className="text-muted-foreground">{group.members.length}</TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {new Date(group.created_at).toLocaleDateString()}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    onClick={() => navigate(`/admin/ring-groups/${group.id}/edit`)}
                                                >
                                                    <SquarePen className="size-4" />
                                                    <span className="sr-only">Edit ring group</span>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    onClick={() => setGroupToDelete(group)}
                                                >
                                                    <Trash2 className="size-4 text-destructive" />
                                                    <span className="sr-only">Delete ring group</span>
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {groups.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                                            No ring groups configured.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            <AlertDialog open={!!groupToDelete} onOpenChange={(open) => !open && setGroupToDelete(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete ring group?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will permanently delete the ring group &quot;{groupToDelete?.name}&quot; and stop
                            calls from using this routing target.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            variant="destructive"
                            onClick={() => groupToDelete && deleteMutation.mutate(String(groupToDelete.id))}
                        >
                            {deleteMutation.isPending ? 'Deleting…' : 'Delete ring group'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
