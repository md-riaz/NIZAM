import { useQuery } from '@tanstack/react-query';
import { Building2, CalendarDays, Globe, Plus, Settings, SquarePen, Trash2, Users } from 'lucide-react';
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
import { useOrganization } from '@/context/OrganizationContext';
import api from '@/lib/api';
import { useApiMutation } from '@/lib/api-hooks';
import type { Organization } from '@/types/models';

export default function OrganizationsPage() {
    const { switchOrganization, activeOrganization } = useOrganization();
    const navigate = useNavigate();
    const [organizationToDelete, setOrganizationToDelete] = useState<Organization | null>(null);

    const { data: organizations = [], isLoading } = useQuery({
        queryKey: ['organizations'],
        queryFn: async () => {
            const res = await api.get<{ data: Organization[] }>('organizations');
            return res.data.data;
        },
    });

    const activeOrganizations = organizations.filter((organization) => organization.is_active).length;

    const deleteMutation = useApiMutation({
        mutationFn: async (id: string) => {
            await api.delete(`organizations/${id}`);
        },
        invalidateQueries: [['organizations']],
        onSuccess: (_, deletedId) => {
            if (activeOrganization && String(activeOrganization.id) === deletedId) {
                const nextOrganization = organizations.find((organization) => String(organization.id) !== deletedId);
                if (nextOrganization) {
                    switchOrganization(nextOrganization);
                }
            }
            setOrganizationToDelete(null);
        },
    });

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Organizations</h1>
                    <p className="text-muted-foreground">
                        Manage organizations provisioned on this platform.
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Button onClick={() => navigate('/admin/organizations/create')}>
                        <Plus className="size-4" />
                        Create Organization
                    </Button>
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">
                            Total Organizations
                        </CardTitle>
                        <Building2 className="size-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">{organizations.length}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">
                            Active Domains
                        </CardTitle>
                        <Globe className="size-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">{activeOrganizations}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">
                            Total Users
                        </CardTitle>
                        <Users className="size-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">—</div>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>All Organizations</CardTitle>
                    <CardDescription>
                        Switch context, edit organization configuration, or open settings.
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
                                    <TableHead>Domain</TableHead>
                                    <TableHead>Organization defaults</TableHead>
                                    <TableHead>Created</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {organizations.map((organization) => (
                                    <TableRow key={organization.id}>
                                        <TableCell className="font-medium">{organization.name}</TableCell>
                                        <TableCell className="font-mono text-sm text-muted-foreground">
                                            {organization.domain}
                                        </TableCell>
                                        <TableCell>
                                            <div className="space-y-1 text-sm text-muted-foreground">
                                                <div className="flex items-center gap-2">
                                                    <CalendarDays className="size-4" />
                                                    <span>Schedule:</span>
                                                    <span className="font-mono text-xs text-foreground">
                                                        {organization.default_schedule_id ?? 'Not provisioned'}
                                                    </span>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <CalendarDays className="size-4" />
                                                    <span>Holiday calendar:</span>
                                                    <span className="font-mono text-xs text-foreground">
                                                        {organization.default_holiday_calendar_id ?? 'Not provisioned'}
                                                    </span>
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {new Date(organization.created_at).toLocaleDateString()}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant={organization.is_active ? 'success' : 'secondary'}>
                                                {organization.status ?? (organization.is_active ? 'active' : 'inactive')}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => {
                                                        switchOrganization(organization);
                                                        navigate('/admin');
                                                    }}
                                                >
                                                    Switch to
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    onClick={() => navigate(`/admin/organizations/${organization.id}/edit`)}
                                                >
                                                    <SquarePen className="size-4" />
                                                    <span className="sr-only">Edit organization</span>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    onClick={() => navigate(`/admin/organizations/${organization.id}/settings`)}
                                                >
                                                    <Settings className="size-4" />
                                                    <span className="sr-only">Open organization settings</span>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    onClick={() => setOrganizationToDelete(organization)}
                                                    disabled={activeOrganization?.id === organization.id}
                                                >
                                                    <Trash2 className="size-4 text-destructive" />
                                                    <span className="sr-only">Delete organization</span>
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {organizations.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                                            No organizations provisioned yet.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            <AlertDialog open={!!organizationToDelete} onOpenChange={(open) => !open && setOrganizationToDelete(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete organization?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will permanently delete organization &quot;{organizationToDelete?.name}&quot; and all associated
                            records. Switch active context first if this organization is in use.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            variant="destructive"
                            onClick={() => organizationToDelete && deleteMutation.mutate(String(organizationToDelete.id))}
                            disabled={deleteMutation.isPending}
                        >
                            {deleteMutation.isPending ? 'Deleting…' : 'Delete organization'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
