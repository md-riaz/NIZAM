import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Pencil, Plus, Trash2, ShieldAlert } from 'lucide-react';
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
import api from '@/lib/api';

type SipProfileSetting = {
    id?: string;
    name: string;
    value: string;
    is_enabled: boolean;
    description: string | null;
    is_deleted?: boolean;
};

type SipProfile = {
    id: string;
    name: string;
    hostname: string | null;
    description: string | null;
    settings: SipProfileSetting[];
    is_active: boolean;
    updated_at?: string;
};

type ProfileFormState = {
    name: string;
    hostname: string;
    description: string;
    settings: SipProfileSetting[];
    is_active: boolean;
};

const EMPTY_FORM: ProfileFormState = {
    name: '',
    hostname: '',
    description: '',
    settings: [],
    is_active: true,
};

function normalizeProfile(raw: any): SipProfile {
    return {
        id: String(raw.id),
        name: raw.name,
        hostname: raw.hostname ?? null,
        description: raw.description ?? null,
        settings: (raw.settings || []).map((s: any) => ({
            id: s.id,
            name: s.name,
            value: s.value,
            is_enabled: Boolean(s.is_enabled),
            description: s.description ?? null,
        })),
        is_active: Boolean(raw.is_active),
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
        mutationFn: async (payload: any) => {
            if (editingProfileId) {
                const res = await api.put(`admin/sip-profiles/${editingProfileId}`, payload);
                return res.data;
            }
            const res = await api.post('admin/sip-profiles', payload);
            return res.data;
        },
        onSuccess: async () => {
            setStatusMessage(editingProfileId ? 'SIP profile updated.' : 'SIP profile created.');
            setErrorMessage(null);
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
        setFormState({
            name: profile.name,
            hostname: profile.hostname ?? '',
            description: profile.description ?? '',
            settings: profile.settings.map(s => ({ ...s })),
            is_active: profile.is_active,
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
        window.setTimeout(() => firstInputRef.current?.focus(), 100);
    };

    const onCancelEdit = () => {
        setEditingProfileId(null);
        setFormState(EMPTY_FORM);
    };

    const handleSave = async () => {
        const trimmedName = formState.name.trim();
        if (!trimmedName) {
            setErrorMessage('Name is required.');
            window.setTimeout(() => firstInputRef.current?.focus(), 0);
            return;
        }

        setErrorMessage(null);

        const activeSettings = formState.settings.filter(s => !s.is_deleted);
        const deletedSettingsIds = formState.settings
            .filter(s => s.is_deleted && s.id)
            .map(s => s.id);

        await saveMutation.mutateAsync({
            name: trimmedName,
            hostname: formState.hostname.trim() || undefined,
            description: formState.description.trim() || undefined,
            is_active: formState.is_active,
            settings: activeSettings,
            settings_to_delete: deletedSettingsIds,
        });
    };

    const addSetting = () => {
        setFormState(prev => ({
            ...prev,
            settings: [
                ...prev.settings,
                { name: '', value: '', is_enabled: true, description: '' }
            ]
        }));
    };

    const updateSetting = (index: number, field: keyof SipProfileSetting, val: any) => {
        setFormState(prev => {
            const copy = [...prev.settings];
            copy[index] = { ...copy[index], [field]: val };
            return { ...prev, settings: copy };
        });
    };

    const removeSetting = (index: number) => {
        setFormState(prev => {
            const copy = [...prev.settings];
            if (copy[index].id) {
                copy[index].is_deleted = true;
            } else {
                copy.splice(index, 1);
            }
            return { ...prev, settings: copy };
        });
    };

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div>
                <p className="text-sm text-muted-foreground">Platform Admin &rsaquo; Telephony</p>
                <h1 className="text-2xl font-bold tracking-tight">SIP Profiles</h1>
                <p className="text-muted-foreground">
                    Manage robust SIP profiles and settings. The system automatically compiles these directly 
                    to the FreeSWITCH directory as XML files on save.
                </p>
            </div>

            <Card className="border-t-4 border-t-primary shadow-sm">
                <CardHeader>
                    <CardTitle>{editingProfileId ? 'Edit SIP Profile' : 'Create SIP Profile'}</CardTitle>
                    <CardDescription>
                        Define profile settings like `sip-port`, `dialplan`, or `ext-sip-ip` manually.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="space-y-2">
                            <Label htmlFor="sip-profile-name">Name <span className="text-destructive">*</span></Label>
                            <Input
                                ref={firstInputRef}
                                id="sip-profile-name"
                                value={formState.name}
                                onChange={(event) => setFormState((current) => ({
                                    ...current,
                                    name: event.target.value,
                                }))}
                                placeholder="e.g. internal"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="sip-profile-hostname">Hostname</Label>
                            <Input
                                id="sip-profile-hostname"
                                value={formState.hostname}
                                onChange={(event) => setFormState((current) => ({
                                    ...current,
                                    hostname: event.target.value,
                                }))}
                                placeholder="Specific routing host"
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

                    <div className="border rounded-md">
                        <Table>
                            <TableHeader className="bg-muted/50">
                                <TableRow>
                                    <TableHead className="w-[200px]">Name</TableHead>
                                    <TableHead>Value</TableHead>
                                    <TableHead className="w-[100px] text-center">Enabled</TableHead>
                                    <TableHead>Description</TableHead>
                                    <TableHead className="w-[50px]"></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {formState.settings.map((setting, idx) => {
                                    if (setting.is_deleted) return null;
                                    return (
                                        <TableRow key={idx}>
                                            <TableCell className="p-2">
                                                <Input 
                                                    value={setting.name} 
                                                    className="font-mono text-sm h-8" 
                                                    placeholder="e.g. sip-port"
                                                    onChange={(e) => updateSetting(idx, 'name', e.target.value)}
                                                />
                                            </TableCell>
                                            <TableCell className="p-2">
                                                <Input 
                                                    value={setting.value} 
                                                    className="font-mono text-sm h-8" 
                                                    placeholder="5060"
                                                    onChange={(e) => updateSetting(idx, 'value', e.target.value)}
                                                />
                                            </TableCell>
                                            <TableCell className="p-2 text-center align-middle">
                                                <Checkbox 
                                                    checked={setting.is_enabled}
                                                    onCheckedChange={(c) => updateSetting(idx, 'is_enabled', !!c)}
                                                />
                                            </TableCell>
                                            <TableCell className="p-2">
                                                <Input 
                                                    value={setting.description || ''} 
                                                    className="text-sm h-8" 
                                                    placeholder="Optional doc"
                                                    onChange={(e) => updateSetting(idx, 'description', e.target.value)}
                                                />
                                            </TableCell>
                                            <TableCell className="p-2 text-right">
                                                <Button size="icon-sm" variant="ghost" onClick={() => removeSetting(idx)}>
                                                    <Trash2 className="size-4 text-destructive" />
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    )
                                })}
                                {formState.settings.filter(s => !s.is_deleted).length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={5} className="h-16 text-center text-muted-foreground text-sm">
                                            No settings added yet.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                        <div className="p-2 bg-muted/20 border-t flex justify-end">
                            <Button type="button" variant="outline" size="sm" onClick={addSetting}>
                                <Plus className="size-4 mr-1" /> Add Setting
                            </Button>
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <Checkbox
                            id="sip-profile-active"
                            checked={formState.is_active}
                            onCheckedChange={(checked) => setFormState((current) => ({
                                ...current,
                                is_active: checked === true,
                            }))}
                        />
                        <Label htmlFor="sip-profile-active" className="cursor-pointer">
                            Enable Profile
                        </Label>
                    </div>

                    <div className="flex flex-wrap justify-end gap-2 pt-4">
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
                            {saveMutation.isPending
                                ? 'Saving…'
                                : editingProfileId
                                    ? 'Save Profile'
                                    : 'Create Profile'}
                        </Button>
                    </div>

                    {statusMessage && (
                        <div className="rounded-md border border-emerald-500/40 bg-emerald-500/10 p-3 text-sm text-emerald-900 dark:text-emerald-200">
                            {statusMessage}
                        </div>
                    )}
                    {errorMessage && (
                        <div className="flex items-center gap-2 rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive">
                            <ShieldAlert className="size-4" />
                            {errorMessage}
                        </div>
                    )}

                </CardContent>
            </Card>

            <Card>
                <CardHeader className="pb-3 border-b mb-4">
                    <CardTitle>Configured Profiles</CardTitle>
                    <CardDescription>
                        SIP profiles managed by the system database. Output to `/etc/freeswitch/sip_profiles`.
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
                                    <TableHead>Profile Name</TableHead>
                                    <TableHead>Hostname</TableHead>
                                    <TableHead>Settings Count</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {orderedProfiles.map((profile) => (
                                    <TableRow key={profile.id}>
                                        <TableCell className="font-medium text-primary">
                                            {profile.name}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">{profile.hostname ?? '—'}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{profile.settings.length} params</Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant={profile.is_active ? 'success' : 'secondary'}>
                                                {profile.is_active ? 'Active' : 'Disabled'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    onClick={() => onStartEdit(profile)}
                                                >
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    onClick={() => setProfileToDelete(profile)}
                                                >
                                                    <Trash2 className="size-4 text-destructive" />
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
                </CardContent>
            </Card>

            <AlertDialog open={!!profileToDelete} onOpenChange={(open) => !open && setProfileToDelete(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete SIP profile?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will permanently delete profile "{profileToDelete?.name}" and remove the XML file.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            variant="destructive"
                            onClick={() => profileToDelete && deleteMutation.mutate(profileToDelete.id)}
                            disabled={deleteMutation.isPending}
                        >
                            {deleteMutation.isPending ? 'Deleting…' : 'Delete'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
