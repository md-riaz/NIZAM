import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Building2, Globe, Plus, Settings, SquarePen, Trash2, Users, WandSparkles } from 'lucide-react';
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
import type { Tenant } from '@/types/models';

export default function TenantsPage() {
    const { switchTenant, activeTenant } = useTenant();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [tenantToDelete, setTenantToDelete] = useState<Tenant | null>(null);

    const { data: tenants = [], isLoading } = useQuery({
        queryKey: ['tenants'],
        queryFn: async () => {
            const res = await api.get<{ data: Tenant[] }>('tenants');
            return res.data.data;
        },
    });

    const activeTenants = tenants.filter((tenant) => tenant.is_active).length;

    const provisionMutation = useMutation({
        mutationFn: async () => {
            const stamp = new Date().toISOString().replace(/[-:TZ.]/g, '').slice(0, 14);
            return api.post('tenants/provision', {
                name: `Provisioned Tenant ${stamp}`,
                slug: `provisioned-${stamp}`,
                domain: `provisioned-${stamp}.nizam.local`,
                max_extensions: 100,
                max_concurrent_calls: 30,
                max_dids: 20,
                max_ring_groups: 20,
            });
        },
        onSuccess: async () => {
            await queryClient.invalidateQueries({ queryKey: ['tenants'] });
        },
    });

    const deleteMutation = useMutation({
        mutationFn: async (id: string) => {
            await api.delete(`tenants/${id}`);
        },
        onSuccess: async (_, deletedId) => {
            await queryClient.invalidateQueries({ queryKey: ['tenants'] });
            if (activeTenant && String(activeTenant.id) === deletedId) {
                const nextTenant = tenants.find((tenant) => String(tenant.id) !== deletedId);
                if (nextTenant) {
                    switchTenant(nextTenant);
                }
            }
            setTenantToDelete(null);
        },
    });

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Tenants</h1>
                    <p className="text-muted-foreground">
                        Manage organizations provisioned on this platform.
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        onClick={() => provisionMutation.mutate()}
                        disabled={provisionMutation.isPending}
                    >
                        <WandSparkles className="size-4" />
                        {provisionMutation.isPending ? 'Provisioning…' : 'Quick Provision'}
                    </Button>
                    <Button onClick={() => navigate('/admin/tenants/create')}>
                        <Plus className="size-4" />
                        Create Tenant
                    </Button>
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">
                            Total Tenants
                        </CardTitle>
                        <Building2 className="size-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">{tenants.length}</div>
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
                        <div className="text-2xl font-bold">{activeTenants}</div>
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
                    <CardTitle>All Tenants</CardTitle>
                    <CardDescription>
                        Switch context, edit tenant configuration, or open settings.
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
                                    <TableHead>Created</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {tenants.map((tenant) => (
                                    <TableRow key={tenant.id}>
                                        <TableCell className="font-medium">{tenant.name}</TableCell>
                                        <TableCell className="font-mono text-sm text-muted-foreground">
                                            {tenant.domain}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {new Date(tenant.created_at).toLocaleDateString()}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant={tenant.is_active ? 'success' : 'secondary'}>
                                                {tenant.status ?? (tenant.is_active ? 'active' : 'inactive')}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => {
                                                        switchTenant(tenant);
                                                        navigate('/admin');
                                                    }}
                                                >
                                                    Switch to
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    onClick={() => navigate(`/admin/tenants/${tenant.id}/edit`)}
                                                >
                                                    <SquarePen className="size-4" />
                                                    <span className="sr-only">Edit tenant</span>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    onClick={() => navigate(`/admin/tenants/${tenant.id}/settings`)}
                                                >
                                                    <Settings className="size-4" />
                                                    <span className="sr-only">Open tenant settings</span>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    onClick={() => setTenantToDelete(tenant)}
                                                    disabled={activeTenant?.id === tenant.id}
                                                >
                                                    <Trash2 className="size-4 text-destructive" />
                                                    <span className="sr-only">Delete tenant</span>
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {tenants.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={5} className="h-24 text-center text-muted-foreground">
                                            No tenants provisioned yet.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            <AlertDialog open={!!tenantToDelete} onOpenChange={(open) => !open && setTenantToDelete(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete tenant?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will permanently delete tenant &quot;{tenantToDelete?.name}&quot; and all associated
                            records. Switch active context first if this tenant is in use.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            variant="destructive"
                            onClick={() => tenantToDelete && deleteMutation.mutate(String(tenantToDelete.id))}
                            disabled={deleteMutation.isPending}
                        >
                            {deleteMutation.isPending ? 'Deleting…' : 'Delete tenant'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
