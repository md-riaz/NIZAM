import { useQuery } from '@tanstack/react-query';
import { ArrowLeft, Save } from 'lucide-react';
import { useMemo } from 'react';
import { useNavigate, useParams } from 'react-router-dom';

import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import api from '@/lib/api';
import { useApiMutation } from '@/lib/api-hooks';
import type { Permission } from '@/types/models';

export default function UserPermissionsPage() {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();

    const { data: user } = useQuery({
        queryKey: ['user', id],
        queryFn: async () => {
            const response = await api.get(`users/${id}`);
            return response.data.data;
        },
        enabled: Boolean(id),
    });

    const { data: assignedPermissions = [] } = useQuery({
        queryKey: ['user-permissions', id],
        queryFn: async () => {
            const response = await api.get<{ permissions: string[] }>(`users/${id}/permissions`);
            return response.data.permissions;
        },
        enabled: Boolean(id),
    });

    const { data: availablePermissions = [] } = useQuery({
        queryKey: ['permissions'],
        queryFn: async () => {
            const response = await api.get<{ permissions: Permission[] }>('permissions');
            return response.data.permissions;
        },
    });

    const groupedPermissions = useMemo(() => {
        return availablePermissions.reduce<Record<string, Permission[]>>((groups, permission) => {
            const key = permission.module || 'general';
            groups[key] ??= [];
            groups[key].push(permission);
            return groups;
        }, {});
    }, [availablePermissions]);

    const mutation = useApiMutation({
        mutationFn: async (nextPermissions: string[]) => {
            const toGrant = nextPermissions.filter((permission) => !assignedPermissions.includes(permission));
            const toRevoke = assignedPermissions.filter((permission) => !nextPermissions.includes(permission));

            if (toGrant.length > 0) {
                await api.post(`users/${id}/permissions/grant`, { permissions: toGrant });
            }

            if (toRevoke.length > 0) {
                await api.post(`users/${id}/permissions/revoke`, { permissions: toRevoke });
            }
        },
        invalidateQueries: [['user-permissions', id || '']],
    });

    const selected = new Set(assignedPermissions);

    const handleToggle = (slug: string, checked: boolean) => {
        const next = new Set(selected);
        if (checked) {
            next.add(slug);
        } else {
            next.delete(slug);
        }

        mutation.mutate(Array.from(next));
    };

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div className="flex items-center gap-4">
                <Button variant="ghost" size="icon" onClick={() => navigate('/admin/users')}>
                    <ArrowLeft className="size-4" />
                    <span className="sr-only">Back to users</span>
                </Button>
                <div>
                    <p className="text-sm text-muted-foreground">Platform administration</p>
                    <h1 className="text-2xl font-bold tracking-tight">User Permissions</h1>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>{user?.name ?? 'User'} permissions</CardTitle>
                    <CardDescription>
                        Enable or revoke explicit permissions. Changes save immediately when toggled.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    {Object.entries(groupedPermissions).map(([module, permissions]) => (
                        <section key={module} className="space-y-4">
                            <div>
                                <h2 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                                    {module}
                                </h2>
                            </div>
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                {permissions.map((permission) => {
                                    const checked = selected.has(permission.slug);
                                    const checkboxId = `${module}-${permission.slug}`;

                                    return (
                                        <div key={permission.slug} className="rounded-lg border p-4">
                                            <div className="flex items-start gap-3">
                                                <Checkbox
                                                    id={checkboxId}
                                                    checked={checked}
                                                    onCheckedChange={(value) => handleToggle(permission.slug, value === true)}
                                                    disabled={mutation.isPending}
                                                />
                                                <div className="space-y-1">
                                                    <Label htmlFor={checkboxId} className="cursor-pointer font-medium">
                                                        {permission.slug}
                                                    </Label>
                                                    <p className="text-sm text-muted-foreground">
                                                        {permission.description || 'No description available.'}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </section>
                    ))}

                    {availablePermissions.length === 0 && (
                        <p className="text-sm text-muted-foreground">No permissions are available yet.</p>
                    )}

                    <div className="flex justify-end text-sm text-muted-foreground">
                        <span className="inline-flex items-center gap-2">
                            <Save className="size-4" />
                            {mutation.isPending ? 'Saving permission changes...' : 'Permission changes are saved immediately.'}
                        </span>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
