import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Ban, Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';

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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

type BlockedDestination = {
    id: string;
    tenant_id: string | null;
    pattern: string;
    description: string | null;
    created_at?: string;
    updated_at?: string;
};

type BlockedDestinationForm = {
    pattern: string;
    description: string;
};

const EMPTY_FORM: BlockedDestinationForm = {
    pattern: '',
    description: '',
};

function normalizeBlockedDestination(raw: any): BlockedDestination {
    return {
        id: String(raw.id),
        tenant_id: raw.tenant_id ? String(raw.tenant_id) : null,
        pattern: String(raw.pattern ?? ''),
        description: raw.description ?? null,
        created_at: raw.created_at,
        updated_at: raw.updated_at,
    };
}

export default function BlockedDestinationsPage() {
    const queryClient = useQueryClient();
    const { activeTenant } = useTenant();
    const [formState, setFormState] = useState<BlockedDestinationForm>(EMPTY_FORM);
    const [editingId, setEditingId] = useState<string | null>(null);
    const [itemToDelete, setItemToDelete] = useState<BlockedDestination | null>(null);
    const [statusMessage, setStatusMessage] = useState<string | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const firstInputRef = useRef<HTMLInputElement>(null);

    const { data: blockedDestinations = [], isLoading } = useQuery({
        queryKey: ['admin-blocked-destinations', activeTenant?.id],
        queryFn: async () => {
            const params = activeTenant ? { tenant_id: activeTenant.id } : undefined;
            const res = await api.get<any[]>('admin/blocked-destinations', { params });
            return (res.data ?? []).map(normalizeBlockedDestination);
        },
    });

    const sortedRows = useMemo(
        () => [...blockedDestinations].sort((a, b) => a.pattern.localeCompare(b.pattern)),
        [blockedDestinations],
    );

    const saveMutation = useMutation({
        mutationFn: async (payload: { id?: string; pattern: string; description?: string; tenant_id?: string }) => {
            if (payload.id) {
                const { id, ...updatePayload } = payload;
                const res = await api.put(`admin/blocked-destinations/${id}`, updatePayload);
                return res.data;
            }
            const res = await api.post('admin/blocked-destinations', payload);
            return res.data;
        },
        onSuccess: async () => {
            setStatusMessage(editingId ? 'Blocked destination updated.' : 'Blocked destination created.');
            setErrorMessage(null);
            setEditingId(null);
            setFormState(EMPTY_FORM);
            await queryClient.invalidateQueries({ queryKey: ['admin-blocked-destinations'] });
        },
        onError: (error: any) => {
            const message = error?.response?.data?.message ?? 'Failed to save blocked destination.';
            setStatusMessage(null);
            setErrorMessage(message);
            window.setTimeout(() => firstInputRef.current?.focus(), 0);
        },
    });

    const deleteMutation = useMutation({
        mutationFn: async (id: string) => {
            await api.delete(`admin/blocked-destinations/${id}`);
        },
        onSuccess: async () => {
            setItemToDelete(null);
            setStatusMessage('Blocked destination deleted.');
            setErrorMessage(null);
            await queryClient.invalidateQueries({ queryKey: ['admin-blocked-destinations'] });
        },
        onError: (error: any) => {
            const message = error?.response?.data?.message ?? 'Failed to delete blocked destination.';
            setStatusMessage(null);
            setErrorMessage(message);
        },
    });

    const onStartEdit = (item: BlockedDestination) => {
        setEditingId(item.id);
        setErrorMessage(null);
        setStatusMessage(null);
        setFormState({
            pattern: item.pattern,
            description: item.description ?? '',
        });
        window.setTimeout(() => firstInputRef.current?.focus(), 0);
    };

    const onCancelEdit = () => {
        setEditingId(null);
        setFormState(EMPTY_FORM);
    };

    const onSave = async () => {
        const pattern = formState.pattern.trim();
        if (!pattern) {
            setErrorMessage('Pattern is required.');
            window.setTimeout(() => firstInputRef.current?.focus(), 0);
            return;
        }

        setErrorMessage(null);

        await saveMutation.mutateAsync({
            id: editingId ?? undefined,
            tenant_id: activeTenant?.id,
            pattern,
            description: formState.description.trim() || undefined,
        });
    };

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div>
                <p className="text-sm text-muted-foreground">Platform Admin &rsaquo; Security</p>
                <h1 className="text-2xl font-bold tracking-tight">Blocked Destinations</h1>
                <p className="text-muted-foreground">
                    Define dial patterns that must be blocked before outbound routing.
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>{editingId ? 'Edit blocked destination' : 'Add blocked destination'}</CardTitle>
                    <CardDescription>
                        Use exact prefixes or full destination patterns according to your policy.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="blocked-pattern">Pattern *</Label>
                            <Input
                                ref={firstInputRef}
                                id="blocked-pattern"
                                value={formState.pattern}
                                onChange={(event) => setFormState((current) => ({
                                    ...current,
                                    pattern: event.target.value,
                                }))}
                                placeholder="e.g. +1900"
                                aria-required={true}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="blocked-description">Description</Label>
                            <Input
                                id="blocked-description"
                                value={formState.description}
                                onChange={(event) => setFormState((current) => ({
                                    ...current,
                                    description: event.target.value,
                                }))}
                                placeholder="Policy note"
                            />
                        </div>
                    </div>

                    <div className="flex flex-wrap justify-end gap-2">
                        {editingId ? (
                            <Button type="button" variant="outline" onClick={onCancelEdit}>
                                Cancel
                            </Button>
                        ) : null}
                        <Button
                            type="button"
                            onClick={onSave}
                            disabled={saveMutation.isPending}
                        >
                            <Plus className="size-4" />
                            {saveMutation.isPending
                                ? 'Saving…'
                                : editingId
                                    ? 'Save blocked destination'
                                    : 'Create blocked destination'}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Blocked patterns</CardTitle>
                    <CardDescription>
                        {sortedRows.length} blocked destination pattern{sortedRows.length === 1 ? '' : 's'}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {isLoading ? (
                        <div className="flex h-24 items-center justify-center">
                            <div className="size-6 motion-safe:animate-spin rounded-full border-2 border-primary border-t-transparent" />
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Pattern</TableHead>
                                    <TableHead>Description</TableHead>
                                    <TableHead>Scope</TableHead>
                                    <TableHead>Updated</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {sortedRows.map((item) => (
                                    <TableRow key={item.id}>
                                        <TableCell className="font-medium">
                                            <div className="flex items-center gap-2">
                                                <Ban className="size-4 text-muted-foreground" />
                                                <span className="font-mono">{item.pattern}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">{item.description ?? '—'}</TableCell>
                                        <TableCell>
                                            <Badge variant={item.tenant_id ? 'secondary' : 'default'}>
                                                {item.tenant_id ? 'Tenant' : 'Global'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {item.updated_at ? new Date(item.updated_at).toLocaleString() : '—'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    onClick={() => onStartEdit(item)}
                                                >
                                                    <Pencil className="size-4" />
                                                    <span className="sr-only">Edit blocked destination</span>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    onClick={() => setItemToDelete(item)}
                                                >
                                                    <Trash2 className="size-4 text-destructive" />
                                                    <span className="sr-only">Delete blocked destination</span>
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {sortedRows.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={5} className="h-20 text-center text-muted-foreground">
                                            No blocked destinations configured.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    )}

                    {statusMessage && (
                        <div className="mt-4 rounded-md border border-emerald-500/40 bg-emerald-500/10 p-3 text-sm text-emerald-900 dark:text-emerald-200">
                            {statusMessage}
                        </div>
                    )}
                    {errorMessage && (
                        <div className="mt-4 rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive">
                            {errorMessage}
                        </div>
                    )}
                </CardContent>
            </Card>

            <AlertDialog open={!!itemToDelete} onOpenChange={(open) => !open && setItemToDelete(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete blocked destination?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will permanently remove pattern &quot;{itemToDelete?.pattern}&quot;.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            variant="destructive"
                            onClick={() => itemToDelete && deleteMutation.mutate(itemToDelete.id)}
                            disabled={deleteMutation.isPending}
                        >
                            {deleteMutation.isPending ? 'Deleting…' : 'Delete pattern'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
