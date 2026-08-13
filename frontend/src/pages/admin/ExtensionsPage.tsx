import { useQuery } from '@tanstack/react-query';
import { Eye, Phone as PhoneIcon, Plus, SquarePen, Trash2 } from 'lucide-react';
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
import type { Extension } from '@/types/models';

export default function ExtensionsPage() {
    const { activeOrganization, organizationApiPrefix } = useOrganization();
    const navigate = useNavigate();
    const [extensionToDelete, setExtensionToDelete] = useState<Extension | null>(null);

    const { data: extensions = [], isLoading } = useQuery({
        queryKey: ['extensions', activeOrganization?.id],
        queryFn: async () => {
            const res = await api.get<{ data: Extension[] }>(`${organizationApiPrefix}/extensions`);
            return res.data.data;
        },
        enabled: !!activeOrganization,
    });

    const { data: statusMap = {} } = useQuery({
        queryKey: ['extension-status', activeOrganization?.id],
        queryFn: async () => {
            const res = await api.get<Record<string, { status: string; ip?: string; user_agent?: string }>>(
                `${organizationApiPrefix}/extensions/status/all`,
            );
            return res.data;
        },
        enabled: !!activeOrganization,
        refetchInterval: 15_000,
    });

    const deleteMutation = useApiMutation({
        mutationFn: async (id: string) => {
            await api.delete(`${organizationApiPrefix}/extensions/${id}`);
        },
        invalidateQueries: [
            ['extensions', activeOrganization?.id || ''],
            ['extension-status', activeOrganization?.id || ''],
        ],
        onSuccess: () => setExtensionToDelete(null),
    });

    if (!activeOrganization) {
        return (
            <div className="flex h-64 items-center justify-center text-muted-foreground">
                Select a organization to view extensions.
            </div>
        );
    }

    const registeredCount = Object.values(statusMap).filter((s) => s.status === 'registered').length;
    // 0 means unlimited licensing in this codebase, so only render "of N licensed" when capped.
    const maxExtensions = activeOrganization.max_extensions ?? 0;

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div className="flex items-center justify-between">
                <div>
                    <p className="text-sm text-muted-foreground">{activeOrganization.name} &rsaquo; Phone System</p>
                    <h1 className="text-2xl font-bold tracking-tight">Extensions</h1>
                    <p className="text-muted-foreground">
                        Manage and provision internal SIP extensions for {activeOrganization.domain}.
                    </p>
                </div>
                <Button onClick={() => navigate('/admin/extensions/create')}>
                    <Plus className="size-4" />
                    Create Extension
                </Button>
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">
                            Extensions Provisioned
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">
                            {maxExtensions > 0 ? `${extensions.length} of ${maxExtensions} licensed` : extensions.length}
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">
                            Online Now
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-emerald-600">{registeredCount}</div>
                        <p className="text-xs text-muted-foreground">Active SIP links</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">
                            Offline
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-amber-600">{extensions.length - registeredCount}</div>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>All Extensions</CardTitle>
                    <CardDescription>
                        Showing {extensions.length} extensions for {activeOrganization.domain}
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
                                    <TableHead>Extension</TableHead>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Owner</TableHead>
                                    <TableHead>Caller ID</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>IP / Agent</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {extensions.map((ext) => {
                                    const status = statusMap[ext.extension];
                                    const isOnline = status?.status === 'registered';
                                    const directoryName = [ext.first_name, ext.last_name]
                                        .filter(Boolean)
                                        .join(' ');

                                    return (
                                        <TableRow key={ext.id}>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <div className="flex size-8 items-center justify-center rounded-lg bg-primary/10">
                                                        <PhoneIcon className="size-4 text-primary" />
                                                    </div>
                                                    <span className="font-mono font-semibold text-primary">{ext.extension}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell>{directoryName || '—'}</TableCell>
                                            <TableCell>
                                                <Badge variant="outline">
                                                    {ext.owner_type === 'device'
                                                        ? `Device: ${ext.owner_label ?? 'Shared device'}`
                                                        : ext.owner_type === 'user'
                                                          ? `User: ${ext.owner_label ?? 'Assigned user'}`
                                                          : 'Unassigned'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {ext.effective_caller_id_name ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {isOnline ? (
                                                    <Badge variant="success">Registered</Badge>
                                                ) : (
                                                    <Badge variant="secondary">Unregistered</Badge>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-xs text-muted-foreground">{status?.ip ?? '—'}</TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        onClick={() => navigate(`/admin/extensions/${ext.id}`)}
                                                    >
                                                        <Eye className="size-4" />
                                                        <span className="sr-only">View extension details</span>
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        onClick={() => navigate(`/admin/extensions/${ext.id}/edit`)}
                                                    >
                                                        <SquarePen className="size-4" />
                                                        <span className="sr-only">Edit extension</span>
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        onClick={() => setExtensionToDelete(ext)}
                                                    >
                                                        <Trash2 className="size-4 text-destructive" />
                                                        <span className="sr-only">Delete extension</span>
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                                {extensions.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={7} className="h-24 text-center text-muted-foreground">
                                            No extensions provisioned. Create your first extension to get started.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            <AlertDialog open={!!extensionToDelete} onOpenChange={(open) => !open && setExtensionToDelete(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete extension?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will permanently delete extension &quot;{extensionToDelete?.extension}&quot;.
                            Any linked configuration depending on this extension may stop working.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            variant="destructive"
                            onClick={() => extensionToDelete && deleteMutation.mutate(String(extensionToDelete.id))}
                        >
                            {deleteMutation.isPending ? 'Deleting…' : 'Delete extension'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
