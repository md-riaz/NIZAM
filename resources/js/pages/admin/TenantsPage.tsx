import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { Building2, Plus, Globe, Users } from 'lucide-react';

import api from '@/lib/api';
import type { Tenant } from '@/types/models';
import { useTenant } from '@/context/TenantContext';
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

export default function TenantsPage() {
    const { switchTenant } = useTenant();
    const navigate = useNavigate();

    const { data: tenants = [], isLoading } = useQuery({
        queryKey: ['tenants'],
        queryFn: async () => {
            const res = await api.get<{ data: Tenant[] }>('tenants');
            return res.data.data;
        },
    });

    return (
        <div className="space-y-6 p-6 lg:p-8">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Tenants</h1>
                    <p className="text-muted-foreground">
                        Manage organizations provisioned on this platform.
                    </p>
                </div>
                <Button>
                    <Plus className="size-4" />
                    Create Tenant
                </Button>
            </div>

            {/* Stats Cards */}
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
                        <div className="text-2xl font-bold">{tenants.length}</div>
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

            {/* Table */}
            <Card>
                <CardHeader>
                    <CardTitle>All Tenants</CardTitle>
                    <CardDescription>
                        Click on a tenant to switch context and manage its resources.
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
                                        <TableCell className="font-medium">
                                            {tenant.name}
                                        </TableCell>
                                        <TableCell className="font-mono text-sm text-muted-foreground">
                                            {tenant.domain}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {new Date(tenant.created_at).toLocaleDateString()}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="success">Active</Badge>
                                        </TableCell>
                                        <TableCell className="text-right">
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
        </div>
    );
}
