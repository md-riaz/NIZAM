import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Check, Music4, Pencil, Trash2, Upload } from 'lucide-react';
import { type ChangeEvent, useMemo, useRef, useState } from 'react';

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
import { useOrganization } from '@/context/OrganizationContext';
import api from '@/lib/api';
import type { SystemMedia } from '@/types/models';

const EMPTY_FORM = {
    name: '',
};

export default function SystemMediaPage() {
    const queryClient = useQueryClient();
    const { activeOrganization, organizationApiPrefix } = useOrganization();
    const fileInputRef = useRef<HTMLInputElement>(null);
    const firstInputRef = useRef<HTMLInputElement>(null);

    const [formState, setFormState] = useState(EMPTY_FORM);
    const [editingItem, setEditingItem] = useState<SystemMedia | null>(null);
    const [itemToDelete, setItemToDelete] = useState<SystemMedia | null>(null);
    const [statusMessage, setStatusMessage] = useState<string | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    const { data: mediaItems = [], isLoading } = useQuery<SystemMedia[]>({
        queryKey: ['system-media', activeOrganization?.id],
        queryFn: async () => {
            const response = await api.get<{ data: SystemMedia[] }>(`${organizationApiPrefix}/system-media`);
            return response.data.data;
        },
        enabled: !!activeOrganization,
    });

    const sortedRows = useMemo(
        () => [...mediaItems].sort((a, b) => a.name.localeCompare(b.name)),
        [mediaItems],
    );

    const renameMutation = useMutation({
        mutationFn: async ({ id, name }: { id: string; name: string }) => {
            const response = await api.put(`${organizationApiPrefix}/system-media/${id}`, { name });
            return response.data;
        },
        onSuccess: async () => {
            setStatusMessage('Media asset updated.');
            setErrorMessage(null);
            setEditingItem(null);
            setFormState(EMPTY_FORM);
            await queryClient.invalidateQueries({ queryKey: ['system-media'] });
        },
        onError: (error: any) => {
            setStatusMessage(null);
            setErrorMessage(error?.response?.data?.message ?? 'Failed to update media asset.');
            window.setTimeout(() => firstInputRef.current?.focus(), 0);
        },
    });

    const uploadMutation = useMutation({
        mutationFn: async (file: File) => {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('name', file.name.replace(/\.[^.]+$/, ''));
            formData.append('collection', 'prompts');

            const response = await api.post(`${organizationApiPrefix}/system-media`, formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            });

            return response.data;
        },
        onSuccess: async () => {
            setStatusMessage('Media asset uploaded.');
            setErrorMessage(null);
            await queryClient.invalidateQueries({ queryKey: ['system-media'] });
            if (fileInputRef.current) fileInputRef.current.value = '';
        },
        onError: (error: any) => {
            setStatusMessage(null);
            setErrorMessage(error?.response?.data?.message ?? 'Failed to upload media asset.');
        },
    });

    const deleteMutation = useMutation({
        mutationFn: async (id: string) => {
            await api.delete(`${organizationApiPrefix}/system-media/${id}`);
        },
        onSuccess: async () => {
            setItemToDelete(null);
            setStatusMessage('Media asset deleted.');
            setErrorMessage(null);
            await queryClient.invalidateQueries({ queryKey: ['system-media'] });
        },
        onError: (error: any) => {
            setStatusMessage(null);
            setErrorMessage(error?.response?.data?.message ?? 'Failed to delete media asset.');
        },
    });

    const onStartEdit = (item: SystemMedia) => {
        setEditingItem(item);
        setFormState({ name: item.name });
        setStatusMessage(null);
        setErrorMessage(null);
        window.setTimeout(() => firstInputRef.current?.focus(), 0);
    };

    const onCancelEdit = () => {
        setEditingItem(null);
        setFormState(EMPTY_FORM);
    };

    const onSave = async () => {
        const name = formState.name.trim();
        if (!editingItem) return;
        if (!name) {
            setErrorMessage('Name is required.');
            window.setTimeout(() => firstInputRef.current?.focus(), 0);
            return;
        }

        setErrorMessage(null);
        await renameMutation.mutateAsync({ id: String(editingItem.id), name });
    };

    const onUpload = async (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        if (!file) return;
        setStatusMessage(null);
        setErrorMessage(null);
        await uploadMutation.mutateAsync(file);
    };

    if (!activeOrganization) return null;

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div>
                <p className="text-sm text-muted-foreground">Phone System &rsaquo; Media Library</p>
                <h1 className="text-2xl font-bold tracking-tight">Media Library</h1>
                <p className="text-muted-foreground">
                    Upload prompt audio once, then reuse it across flow nodes.
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Upload prompt media</CardTitle>
                    <CardDescription>
                        Upload WAV or supported prompt audio for IVR and play-message nodes.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="media-upload">Audio file</Label>
                        <Input
                            ref={fileInputRef}
                            id="media-upload"
                            type="file"
                            accept="audio/*"
                            onChange={onUpload}
                            disabled={uploadMutation.isPending}
                        />
                    </div>
                    <div className="flex justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => fileInputRef.current?.click()}
                            disabled={uploadMutation.isPending}
                        >
                            <Upload className="size-4" />
                            {uploadMutation.isPending ? 'Uploading…' : 'Choose file'}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{editingItem ? 'Rename media asset' : 'Media assets'}</CardTitle>
                    <CardDescription>
                        {editingItem ? 'Update display name for selected audio asset.' : `${sortedRows.length} reusable prompt asset${sortedRows.length === 1 ? '' : 's'}`}
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    {editingItem && (
                        <div className="grid gap-4 md:grid-cols-[1fr_auto_auto] md:items-end">
                            <div className="space-y-2">
                                <Label htmlFor="media-name">Display name</Label>
                                <Input
                                    ref={firstInputRef}
                                    id="media-name"
                                    value={formState.name}
                                    onChange={(event) => setFormState({ name: event.target.value })}
                                    placeholder="Welcome Greeting"
                                />
                            </div>
                            <Button type="button" onClick={onSave} disabled={renameMutation.isPending}>
                                <Check className="size-4" />
                                {renameMutation.isPending ? 'Saving…' : 'Save name'}
                            </Button>
                            <Button type="button" variant="outline" onClick={onCancelEdit}>
                                Cancel
                            </Button>
                        </div>
                    )}

                    {isLoading ? (
                        <div className="flex h-24 items-center justify-center">
                            <div className="size-6 motion-safe:animate-spin rounded-full border-2 border-primary border-t-transparent" />
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>File</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Size</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {sortedRows.map((item) => (
                                    <TableRow key={item.id}>
                                        <TableCell className="font-medium">
                                            <div className="flex items-center gap-2">
                                                <Music4 className="size-4 text-muted-foreground" />
                                                <span>{item.name}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">{item.file_name}</TableCell>
                                        <TableCell>
                                            <Badge variant="secondary">{item.mime_type ?? 'audio'}</Badge>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {typeof item.size === 'number' ? `${Math.max(1, Math.round(item.size / 1024))} KB` : '—'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button variant="ghost" size="icon-sm" onClick={() => onStartEdit(item)}>
                                                    <Pencil className="size-4" />
                                                    <span className="sr-only">Rename media asset</span>
                                                </Button>
                                                <Button variant="ghost" size="icon-sm" onClick={() => setItemToDelete(item)}>
                                                    <Trash2 className="size-4 text-destructive" />
                                                    <span className="sr-only">Delete media asset</span>
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {sortedRows.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={5} className="h-20 text-center text-muted-foreground">
                                            No media assets uploaded.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    )}

                    {statusMessage && (
                        <div className="rounded-md border border-emerald-500/40 bg-emerald-500/10 p-3 text-sm text-emerald-900 dark:text-emerald-200">
                            {statusMessage}
                        </div>
                    )}
                    {errorMessage && (
                        <div className="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive">
                            {errorMessage}
                        </div>
                    )}
                </CardContent>
            </Card>

            <AlertDialog open={!!itemToDelete} onOpenChange={(open) => !open && setItemToDelete(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete media asset?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will permanently remove &quot;{itemToDelete?.name}&quot;.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={() => itemToDelete && deleteMutation.mutate(String(itemToDelete.id))}
                            disabled={deleteMutation.isPending}
                            className="border-destructive/10 bg-destructive/10 text-destructive hover:bg-destructive/15"
                        >
                            {deleteMutation.isPending ? 'Deleting…' : 'Delete asset'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
