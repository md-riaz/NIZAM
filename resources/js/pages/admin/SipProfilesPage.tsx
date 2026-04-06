import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Pencil, Plus, Trash2 } from 'lucide-react';
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
import { Checkbox } from '@/components/ui/checkbox';
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
import { Textarea } from '@/components/ui/textarea';
import api from '@/lib/api';

type SipProfile = {
    id: string;
    name: string;
    description: string | null;
    settings: Record<string, unknown> | null;
    is_active: boolean;
    created_at?: string;
    updated_at?: string;
};

type ProfileFormState = {
    name: string;
    description: string;
    settingsText: string;
    is_active: boolean;
};

const EMPTY_FORM: ProfileFormState = {
    name: '',
    description: '',
    settingsText: '{\n  "sip-port": 5060\n}',
    is_active: true,
};

function normalizeProfile(raw: any): SipProfile {
    return {
        id: String(raw.id),
        name: raw.name,
        description: raw.description ?? null,
        settings: raw.settings && typeof raw.settings === 'object' ? raw.settings : null,
        is_active: Boolean(raw.is_active),
        created_at: raw.created_at,
        updated_at: raw.updated_at,
    };
}

export default function SipProfilesPage() {
    const queryClient = useQueryClient();
    const [formState, setFormState] = useState<ProfileFormState>(EMPTY_FORM);
    const [editingProfileId, setEditingProfileId] = useState<string | null>(null);
    const [profileToDelete, setProfileToDelete] = useState<SipProfile | null>(null);
    const [statusMessage, setStatusMessage] = useState<string | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [jsonError, setJsonError] = useState<string | null>(null);
    const firstInputRef = useRef<HTMLInputElement>(null);

    const { data: profiles = [], isLoading } = useQuery({
        queryKey: ['admin-sip-profiles-security'],
        queryFn: async () => {
            const res = await api.get<any[]>('admin/sip-profiles');
            return (res.data ?? []).map(normalizeProfile);
        },
    });

    const orderedProfiles = useMemo(
        () => [...profiles].sort((a, b) => a.name.localeCompare(b.name)),
        [profiles],
    );

    const saveMutation = useMutation({
        mutationFn: async (payload: {
            id?: string;
            name: string;
            description?: string;
            settings?: Record<string, unknown>;
            is_active: boolean;
        }) => {
            if (payload.id) {
                const { id, ...updatePayload } = payload;
                const res = await api.put(`admin/sip-profiles/${id}`, updatePayload);
                return res.data;
            }
            const res = await api.post('admin/sip-profiles', payload);
            return res.data;
        },
        onSuccess: async () => {
            setStatusMessage(editingProfileId ? 'SIP profile updated.' : 'SIP profile created.');
            setErrorMessage(null);
            setJsonError(null);
            setFormState(EMPTY_FORM);
            setEditingProfileId(null);
            await queryClient.invalidateQueries({ queryKey: ['admin-sip-profiles-security'] });
        },
        onError: (error: any) => {
            const message = error?.response?.data?.message ?? 'Failed to save SIP profile.';
            setStatusMessage(null);
            setErrorMessage(message);
        },
    });

    const deleteMutation = useMutation({
        mutationFn: async (id: string) => {
            await api.delete(`admin/sip-profiles/${id}`);
        },
        onSuccess: async () => {
            setProfileToDelete(null);
            setStatusMessage('SIP profile deleted.');
            setErrorMessage(null);
            await queryClient.invalidateQueries({ queryKey: ['admin-sip-profiles-security'] });
        },
        onError: (error: any) => {
            const message = error?.response?.data?.message ?? 'Failed to delete SIP profile.';
            setStatusMessage(null);
            setErrorMessage(message);
        },
    });

    const onStartEdit = (profile: SipProfile) => {
        setEditingProfileId(profile.id);
        setStatusMessage(null);
        setErrorMessage(null);
        setJsonError(null);
        setFormState({
            name: profile.name,
            description: profile.description ?? '',
            settingsText: JSON.stringify(profile.settings ?? {}, null, 2),
            is_active: profile.is_active,
        });
        window.setTimeout(() => firstInputRef.current?.focus(), 0);
    };

    const onCancelEdit = () => {
        setEditingProfileId(null);
        setFormState(EMPTY_FORM);
        setJsonError(null);
    };

    const handleSave = async () => {
        const trimmedName = formState.name.trim();
        if (!trimmedName) {
            setErrorMessage('Name is required.');
            window.setTimeout(() => firstInputRef.current?.focus(), 0);
            return;
        }

        let parsedSettings: Record<string, unknown> | undefined;
        if (formState.settingsText.trim()) {
            try {
                const parsed = JSON.parse(formState.settingsText);
                if (!parsed || Array.isArray(parsed) || typeof parsed !== 'object') {
                    throw new Error('Settings must be a JSON object.');
                }
                parsedSettings = parsed as Record<string, unknown>;
                setJsonError(null);
            } catch (error: any) {
                setJsonError(error?.message ?? 'Invalid JSON in settings field.');
                return;
            }
        } else {
            parsedSettings = {};
            setJsonError(null);
        }

        setErrorMessage(null);

        await saveMutation.mutateAsync({
            id: editingProfileId ?? undefined,
            name: trimmedName,
            description: formState.description.trim() || undefined,
            settings: parsedSettings,
            is_active: formState.is_active,
        });
    };

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div>
                <p className="text-sm text-muted-foreground">Platform Admin &rsaquo; Security</p>
                <h1 className="text-2xl font-bold tracking-tight">SIP Profiles</h1>
                <p className="text-muted-foreground">
                    Manage global SIP profile templates and JSON settings used by the platform.
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>{editingProfileId ? 'Edit SIP profile' : 'Create SIP profile'}</CardTitle>
                    <CardDescription>
                        Use JSON settings to store profile-level attributes such as ports, bind addresses, and media behavior.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="sip-profile-name">Name *</Label>
                            <Input
                                ref={firstInputRef}
                                id="sip-profile-name"
                                value={formState.name}
                                onChange={(event) => setFormState((current) => ({
                                    ...current,
                                    name: event.target.value,
                                }))}
                                placeholder="e.g. internal"
                                aria-required={true}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="sip-profile-description">Description</Label>
                            <Input
                                id="sip-profile-description"
                                value={formState.description}
                                onChange={(event) => setFormState((current) => ({
                                    ...current,
                                    description: event.target.value,
                                }))}
                                placeholder="Short profile purpose"
                            />
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="sip-profile-settings">Settings JSON</Label>
                        <Textarea
                            id="sip-profile-settings"
                            value={formState.settingsText}
                            onChange={(event) => setFormState((current) => ({
                                ...current,
                                settingsText: event.target.value,
                            }))}
                            className="min-h-44 font-mono text-xs"
                            aria-invalid={jsonError ? true : undefined}
                            aria-describedby={jsonError ? 'sip-profile-settings-error' : 'sip-profile-settings-help'}
                        />
                        <p id="sip-profile-settings-help" className="text-sm text-muted-foreground">
                            Must be a valid JSON object.
                        </p>
                        {jsonError && (
                            <p id="sip-profile-settings-error" className="text-sm text-destructive">
                                {jsonError}
                            </p>
                        )}
                    </div>

                    <div className="rounded-md border p-4">
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <Label htmlFor="sip-profile-active">Profile active</Label>
                                <p className="text-sm text-muted-foreground">
                                    Inactive profiles are kept for reference and can be re-enabled later.
                                </p>
                            </div>
                            <Checkbox
                                id="sip-profile-active"
                                checked={formState.is_active}
                                onCheckedChange={(checked) => setFormState((current) => ({
                                    ...current,
                                    is_active: checked === true,
                                }))}
                                aria-label="Set profile active"
                            />
                        </div>
                    </div>

                    <div className="flex flex-wrap justify-end gap-2">
                        {editingProfileId ? (
                            <Button type="button" variant="outline" onClick={onCancelEdit}>
                                Cancel
                            </Button>
                        ) : null}
                        <Button
                            type="button"
                            onClick={handleSave}
                            disabled={saveMutation.isPending}
                        >
                            <Plus className="size-4" />
                            {saveMutation.isPending
                                ? 'Saving…'
                                : editingProfileId
                                    ? 'Save profile'
                                    : 'Create profile'}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Configured profiles</CardTitle>
                    <CardDescription>
                        {orderedProfiles.length} SIP profile{orderedProfiles.length === 1 ? '' : 's'} available
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
                                    <TableHead>Name</TableHead>
                                    <TableHead>Description</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Updated</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {orderedProfiles.map((profile) => (
                                    <TableRow key={profile.id}>
                                        <TableCell className="font-medium">{profile.name}</TableCell>
                                        <TableCell className="text-muted-foreground">{profile.description ?? '—'}</TableCell>
                                        <TableCell>
                                            <Badge variant={profile.is_active ? 'success' : 'secondary'}>
                                                {profile.is_active ? 'Active' : 'Inactive'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {profile.updated_at
                                                ? new Date(profile.updated_at).toLocaleString()
                                                : '—'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    onClick={() => onStartEdit(profile)}
                                                >
                                                    <Pencil className="size-4" />
                                                    <span className="sr-only">Edit SIP profile</span>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    onClick={() => setProfileToDelete(profile)}
                                                >
                                                    <Trash2 className="size-4 text-destructive" />
                                                    <span className="sr-only">Delete SIP profile</span>
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {orderedProfiles.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={5} className="h-20 text-center text-muted-foreground">
                                            No SIP profiles configured.
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

            <AlertDialog open={!!profileToDelete} onOpenChange={(open) => !open && setProfileToDelete(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete SIP profile?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will permanently delete profile &quot;{profileToDelete?.name}&quot;.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            variant="destructive"
                            onClick={() => profileToDelete && deleteMutation.mutate(profileToDelete.id)}
                            disabled={deleteMutation.isPending}
                        >
                            {deleteMutation.isPending ? 'Deleting…' : 'Delete profile'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
