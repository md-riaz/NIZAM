import { useQuery } from '@tanstack/react-query';
import { Edit, Trash2 } from 'lucide-react';
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
import { useOrganization } from '@/context/OrganizationContext';
import api from '@/lib/api';
import { useApiMutation } from '@/lib/api-hooks';

interface Extension {
    id: string;
    extension: string;
}

interface Agent {
    id: string;
    name: string;
    role: string;
    state: string;
    extension_id: string;
    is_active: boolean;
    extension?: Extension;
}

export default function AgentsPage() {
    const { activeOrganization } = useOrganization();
    const { user } = useAuth();
    const [agentToDelete, setAgentToDelete] = useState<Agent | null>(null);

    const { data: agents = [], isLoading } = useQuery<Agent[]>({
        queryKey: ['agents', activeOrganization?.id],
        queryFn: async () => {
            if (!activeOrganization) return [];
            const response = await api.get(`organizations/${activeOrganization.id}/agents`);
            return response.data.data;
        },
        enabled: !!activeOrganization,
    });

    const deleteMutation = useApiMutation({
        mutationFn: async (id: string) => {
            await api.delete(`organizations/${activeOrganization?.id}/agents/${id}`);
        },
        successMessage: 'Agent deleted successfully',
        invalidateQueries: [['agents', activeOrganization?.id || '']],
        onSettled: () => setAgentToDelete(null),
    });

    if (!activeOrganization) return null;

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <PageHeader
                title="Agents"
                description="Manage contact center agents and status mapping."
                actionLabel="Create Agent"
                actionRoute="/admin/agents/create"
            />

            <div className="rounded-md border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Extension</TableHead>
                            <TableHead>Role</TableHead>
                            <TableHead>Current State</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead className="w-[100px] text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {isLoading ? (
                            <TableRow>
                                <TableCell colSpan={6} className="py-8 text-center text-muted-foreground">
                                    Loading agents...
                                </TableCell>
                            </TableRow>
                        ) : agents.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={6} className="py-8 text-center text-muted-foreground">
                                    No agents found. Create one to get started.
                                </TableCell>
                            </TableRow>
                        ) : (
                            agents.map((agent) => (
                                <TableRow key={agent.id}>
                                    <TableCell className="font-medium">{agent.name}</TableCell>
                                    <TableCell>{agent.extension?.extension || '-'}</TableCell>
                                    <TableCell className="capitalize">{agent.role}</TableCell>
                                    <TableCell>
                                        <Badge variant="outline" className="capitalize">
                                            {agent.state.replace('_', ' ')}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant={agent.is_active ? 'default' : 'secondary'}>
                                            {agent.is_active ? 'Active' : 'Inactive'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-2">
                                            <Button variant="ghost" size="icon" asChild>
                                                <Link to={`/admin/agents/${agent.id}/edit`}>
                                                    <Edit className="size-4" />
                                                    <span className="sr-only">Edit agent</span>
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => setAgentToDelete(agent)}
                                            >
                                                <Trash2 className="size-4 text-destructive" />
                                                <span className="sr-only">Delete agent</span>
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
                open={!!agentToDelete}
                onOpenChange={(open) => !open && setAgentToDelete(null)}
                title="Delete Agent"
                description={<>Are you sure you want to delete the agent <strong>{agentToDelete?.name}</strong>?</>}
                isDeleting={deleteMutation.isPending}
                onConfirm={() => agentToDelete && deleteMutation.mutate(agentToDelete.id)}
            />
        </div>
    );
}
