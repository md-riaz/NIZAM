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
import { useTenant } from '@/context/TenantContext';
import api from '@/lib/api';
import { useApiMutation } from '@/lib/api-hooks';

interface Team {
    id: string;
    name: string;
    strategy: string;
    timeout: number;
    is_active: boolean;
    created_at: string;
}

export default function TeamsPage() {
    const { activeTenant } = useTenant();
    const { user } = useAuth();
    const [teamToDelete, setTeamToDelete] = useState<Team | null>(null);

    const { data: teams = [], isLoading } = useQuery<Team[]>({
        queryKey: ['teams', activeTenant?.id],
        queryFn: async () => {
            if (!activeTenant) return [];
            const response = await api.get(`tenants/${activeTenant.id}/teams`);
            return response.data.data;
        },
        enabled: !!activeTenant,
    });

    const deleteMutation = useApiMutation({
        mutationFn: async (id: string) => {
            await api.delete(`tenants/${activeTenant?.id}/teams/${id}`);
        },
        successMessage: 'Team deleted successfully',
        invalidateQueries: [['teams', activeTenant?.id || '']],
        onSettled: () => setTeamToDelete(null),
    });

    if (!activeTenant) return null;

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <PageHeader
                title="Teams"
                description="Manage agent groups and team routing strategies."
                actionLabel="Create Team"
                actionRoute="/admin/teams/create"
            />

            <div className="rounded-md border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Strategy</TableHead>
                            <TableHead>Timeout (s)</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead className="w-[100px] text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {isLoading ? (
                            <TableRow>
                                <TableCell colSpan={5} className="py-8 text-center text-muted-foreground">
                                    Loading teams...
                                </TableCell>
                            </TableRow>
                        ) : teams.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={5} className="py-8 text-center text-muted-foreground">
                                    No teams found. Create one to get started.
                                </TableCell>
                            </TableRow>
                        ) : (
                            teams.map((team) => (
                                <TableRow key={team.id}>
                                    <TableCell className="font-medium">{team.name}</TableCell>
                                    <TableCell className="capitalize">{team.strategy.replace('_', ' ')}</TableCell>
                                    <TableCell>{team.timeout}</TableCell>
                                    <TableCell>
                                        <Badge variant={team.is_active ? 'default' : 'secondary'}>
                                            {team.is_active ? 'Active' : 'Inactive'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-2">
                                            <Button variant="ghost" size="icon" asChild>
                                                <Link to={`/admin/teams/${team.id}/edit`}>
                                                    <Edit className="size-4" />
                                                    <span className="sr-only">Edit team</span>
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => setTeamToDelete(team)}
                                            >
                                                <Trash2 className="size-4 text-destructive" />
                                                <span className="sr-only">Delete team</span>
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
                open={!!teamToDelete}
                onOpenChange={(open) => !open && setTeamToDelete(null)}
                title="Delete Team"
                description={<>Are you sure you want to delete the team <strong>{teamToDelete?.name}</strong>? This action cannot be undone.</>}
                isDeleting={deleteMutation.isPending}
                onConfirm={() => teamToDelete && deleteMutation.mutate(teamToDelete.id)}
            />
        </div>
    );
}
