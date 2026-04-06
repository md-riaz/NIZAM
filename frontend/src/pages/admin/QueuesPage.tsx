import { useQuery } from '@tanstack/react-query';
import { Edit, Eye, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Link } from 'react-router-dom';

import { PageHeader } from '@/components/scaffolds/PageHeader';
import { DeleteDialog } from '@/components/scaffolds/DeleteDialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useAuth } from '@/context/AuthContext';
import { useTenant } from '@/context/TenantContext';
import api from '@/lib/api';
import { useApiMutation } from '@/lib/api-hooks';

interface Queue {
    id: string;
    name: string;
    strategy: string;
    max_wait_time: number;
    is_active: boolean;
}

export default function QueuesPage() {
    const { currentTenant } = useTenant();
    const { user } = useAuth();
    const [queueToDelete, setQueueToDelete] = useState<Queue | null>(null);

    const { data: queues = [], isLoading } = useQuery<Queue[]>({
        queryKey: ['queues', currentTenant?.id],
        queryFn: async () => {
            if (!currentTenant) return [];
            const response = await api.get(`tenants/${currentTenant.id}/queues`);
            return response.data.data;
        },
        enabled: !!currentTenant,
    });

    const deleteMutation = useApiMutation({
        mutationFn: async (id: string) => {
            await api.delete(`tenants/${currentTenant?.id}/queues/${id}`);
        },
        successMessage: 'Queue deleted successfully',
        invalidateQueries: [['queues', currentTenant?.id || '']],
        onSettled: () => setQueueToDelete(null),
    });

    if (!currentTenant) return null;

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <PageHeader
                title="Call Queues"
                description="Manage inbound call queues and agent dispatching logic."
                actionLabel="Create Queue"
                actionRoute="/admin/queues/create"
            />

            <div className="rounded-md border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Queue Name</TableHead>
                            <TableHead>Strategy</TableHead>
                            <TableHead>Max Wait</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead className="w-[120px] text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {isLoading ? (
                            <TableRow>
                                <TableCell colSpan={5} className="py-8 text-center text-muted-foreground">
                                    Loading queues...
                                </TableCell>
                            </TableRow>
                        ) : queues.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={5} className="py-8 text-center text-muted-foreground">
                                    No queues found. Create one to get started.
                                </TableCell>
                            </TableRow>
                        ) : (
                            queues.map((queue) => (
                                <TableRow key={queue.id}>
                                    <TableCell className="font-medium">{queue.name}</TableCell>
                                    <TableCell className="capitalize">{queue.strategy.replace(/_/g, ' ')}</TableCell>
                                    <TableCell>{queue.max_wait_time} s</TableCell>
                                    <TableCell>
                                        <Badge variant={queue.is_active ? 'default' : 'secondary'}>
                                            {queue.is_active ? 'Active' : 'Inactive'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-2">
                                            <Button variant="ghost" size="icon" asChild>
                                                <Link to={`/admin/queues/${queue.id}`}>
                                                    <Eye className="size-4 text-muted-foreground" />
                                                    <span className="sr-only">View members</span>
                                                </Link>
                                            </Button>
                                            <Button variant="ghost" size="icon" asChild>
                                                <Link to={`/admin/queues/${queue.id}/edit`}>
                                                    <Edit className="size-4" />
                                                    <span className="sr-only">Edit queue</span>
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => setQueueToDelete(queue)}
                                            >
                                                <Trash2 className="size-4 text-destructive" />
                                                <span className="sr-only">Delete queue</span>
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>

            <DeleteDialog
                open={!!queueToDelete}
                onOpenChange={(open) => !open && setQueueToDelete(null)}
                title="Delete Queue"
                description={<>Are you sure you want to delete the queue <strong>{queueToDelete?.name}</strong>? This will detach all agents and could disrupt inbound call flows.</>}
                isDeleting={deleteMutation.isPending}
                onConfirm={() => queueToDelete && deleteMutation.mutate(queueToDelete.id)}
            />
        </div>
    );
}
