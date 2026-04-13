import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { AxiosError } from 'axios';
import { AlertCircle, CheckCircle, Play, RefreshCw, Square, XCircle } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { z } from 'zod';

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
import api from '@/lib/api';
import { getModuleActionState } from '@/pages/admin/freeSwitchModulesPageState';
import type { FreeSwitchModuleStatus } from '@/types/models';

const FreeSwitchModuleStatusDtoSchema = z.object({
    name: z.string(),
    type: z.string().nullable().optional().transform((value) => value ?? ''),
    status: z.string(),
    supports_start: z.coerce.boolean().optional(),
    supports_stop: z.coerce.boolean().optional(),
});

const FreeSwitchModulesEnvelopeSchema = z.object({
    data: z.array(FreeSwitchModuleStatusDtoSchema),
    meta: z.object({
        source: z.string().optional(),
        live: z.boolean().optional(),
        error: z.string().optional(),
    }).optional(),
});

const FreeSwitchModulesResponseSchema = z.union([
    FreeSwitchModulesEnvelopeSchema,
    z.array(FreeSwitchModuleStatusDtoSchema).transform((data) => ({ data })),
]);

interface ModuleActionResponse {
    message?: string;
    meta?: {
        module?: string;
        action?: string;
    };
}

function getApiErrorMessage(error: unknown): string {
    if (error instanceof AxiosError) {
        return error.response?.data?.message ?? error.response?.data?.meta?.error ?? error.message;
    }

    return error instanceof Error
        ? error.message
        : 'The module status list could not be retrieved.';
}

function getStatusBadge(status: string) {
    const normalizedStatus = status.toLowerCase();
    const label = status.replace(/_/g, ' ');

    if (normalizedStatus === 'running') {
        return <Badge variant="success">{label}</Badge>;
    }

    if (normalizedStatus === 'not_loaded') {
        return <Badge variant="secondary">{label}</Badge>;
    }

    if (normalizedStatus.includes('error') || normalizedStatus.includes('fail')) {
        return <Badge variant="destructive">{label}</Badge>;
    }

    return <Badge variant="default">{label}</Badge>;
}

function getStatusIcon(status: string) {
    const normalizedStatus = status.toLowerCase();

    if (normalizedStatus === 'running') {
        return <CheckCircle className="size-4 text-green-600" />;
    }

    if (normalizedStatus === 'not_loaded') {
        return <AlertCircle className="size-4 text-yellow-600" />;
    }

    if (normalizedStatus.includes('error') || normalizedStatus.includes('fail')) {
        return <XCircle className="size-4 text-red-600" />;
    }

    return <AlertCircle className="size-4 text-muted-foreground" />;
}

export default function FreeSwitchModulesPage() {
    const queryClient = useQueryClient();
    const [confirmAction, setConfirmAction] = useState<{
        type: 'start' | 'stop';
        module: FreeSwitchModuleStatus;
    } | null>(null);

    const {
        data: modules = [],
        isLoading,
        isError,
        error,
        refetch,
        isFetching,
    } = useQuery<FreeSwitchModuleStatus[]>({
        queryKey: ['admin-freeswitch-modules'],
        queryFn: async () => {
            const res = await api.get('admin/freeswitch/modules');
            const parsed = FreeSwitchModulesResponseSchema.parse(res.data);
            return parsed.data;
        },
        refetchInterval: 15000,
    });

    const visibleModules = useMemo(
        () => modules.filter((module) => module.name.trim() !== ''),
        [modules],
    );

    const startModuleMutation = useMutation({
        mutationFn: async (moduleName: string) => {
            const res = await api.post<ModuleActionResponse>('admin/freeswitch/modules/start', {
                module: moduleName,
            });
            return res.data;
        },
        onSuccess: (data) => {
            toast.success(data.message ?? 'FreeSWITCH module started.');
            queryClient.invalidateQueries({ queryKey: ['admin-freeswitch-modules'] });
        },
        onError: (mutationError) => {
            toast.error(getApiErrorMessage(mutationError));
        },
    });

    const stopModuleMutation = useMutation({
        mutationFn: async (moduleName: string) => {
            const res = await api.post<ModuleActionResponse>('admin/freeswitch/modules/stop', {
                module: moduleName,
            });
            return res.data;
        },
        onSuccess: (data) => {
            toast.success(data.message ?? 'FreeSWITCH module stopped.');
            queryClient.invalidateQueries({ queryKey: ['admin-freeswitch-modules'] });
        },
        onError: (mutationError) => {
            toast.error(getApiErrorMessage(mutationError));
        },
    });

    const executeAction = async () => {
        if (!confirmAction) return;

        const pending = confirmAction;
        setConfirmAction(null);

        if (pending.type === 'start') {
            await startModuleMutation.mutateAsync(pending.module.name);
            return;
        }

        await stopModuleMutation.mutateAsync(pending.module.name);
    };

    const mutationBusy = startModuleMutation.isPending || stopModuleMutation.isPending;

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div>
                <p className="text-sm text-muted-foreground">Platform Admin &rsaquo; System</p>
                <h1 className="text-2xl font-bold tracking-tight">FreeSWITCH Modules</h1>
                <p className="text-muted-foreground leading-relaxed">
                    View platform-level FreeSWITCH module availability and runtime status, with safe
                    platform-admin controls for supported modules.
                </p>
            </div>

            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between gap-4">
                        <div>
                            <CardTitle>Module Status</CardTitle>
                            <CardDescription>
                                Polled from the platform admin FreeSWITCH modules endpoint every 15 seconds.
                            </CardDescription>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => refetch()}
                            disabled={isLoading || isFetching || mutationBusy}
                            aria-label="Refresh FreeSWITCH modules"
                            className="cursor-pointer"
                        >
                            <RefreshCw
                                className={`size-4 ${isLoading || isFetching ? 'motion-safe:animate-spin' : ''}`}
                            />
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    {isLoading ? (
                        <div className="flex h-32 items-center justify-center">
                            <div
                                className="size-6 motion-safe:animate-spin rounded-full border-2 border-primary border-t-transparent"
                                aria-label="Loading modules"
                            />
                        </div>
                    ) : isError ? (
                        <div className="space-y-2 py-6 text-center">
                            <p className="text-base font-semibold">Unable to load FreeSWITCH modules</p>
                            <p className="text-sm text-muted-foreground">
                                {getApiErrorMessage(error)}
                            </p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {visibleModules.map((module) => {
                                    const { canStart, canStop, hasSafeAction } = getModuleActionState(module);
                                    const rowBusy = mutationBusy && confirmAction?.module.name === module.name;

                                    return (
                                        <TableRow key={module.name}>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    {getStatusIcon(module.status)}
                                                    {getStatusBadge(module.status)}
                                                </div>
                                            </TableCell>
                                            <TableCell className="font-medium">{module.name}</TableCell>
                                            <TableCell>{module.type || '—'}</TableCell>
                                            <TableCell>
                                                <div className="flex flex-wrap gap-2">
                                                    {canStart && (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => setConfirmAction({ type: 'start', module })}
                                                            disabled={mutationBusy}
                                                            aria-label={`Start module ${module.name}`}
                                                            className="cursor-pointer"
                                                        >
                                                            <Play className="mr-1 size-4" />
                                                            Start
                                                        </Button>
                                                    )}
                                                    {canStop && (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => setConfirmAction({ type: 'stop', module })}
                                                            disabled={mutationBusy}
                                                            aria-label={`Stop module ${module.name}`}
                                                            className="cursor-pointer"
                                                        >
                                                            <Square className="mr-1 size-4" />
                                                            Stop
                                                        </Button>
                                                    )}
                                                    {!hasSafeAction && (
                                                        <span className="text-xs text-muted-foreground">
                                                            No safe action available
                                                        </span>
                                                    )}
                                                    {rowBusy && (
                                                        <span className="text-xs text-muted-foreground">Working…</span>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                                {visibleModules.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={4}
                                            className="h-24 text-center text-muted-foreground"
                                        >
                                            No module data available
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            <AlertDialog open={!!confirmAction} onOpenChange={() => setConfirmAction(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Confirm Module Action</AlertDialogTitle>
                        <AlertDialogDescription>
                            {confirmAction?.type === 'start' &&
                                `Start FreeSWITCH module "${confirmAction.module.name}"?`}
                            {confirmAction?.type === 'stop' &&
                                `Stop FreeSWITCH module "${confirmAction.module.name}"? Only allowlisted modules can be stopped from this UI.`}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={mutationBusy}>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={executeAction}>
                            Confirm
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
