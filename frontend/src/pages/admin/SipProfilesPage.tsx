import { useQuery } from '@tanstack/react-query';
import { Edit, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';

import { DeleteDialog } from '@/components/scaffolds/DeleteDialog';
import { PageHeader } from '@/components/scaffolds/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import api from '@/lib/api';
import { useApiMutation } from '@/lib/api-hooks';

type SipProfileSetting = {
    id?: string;
    name: string;
    value: string;
    is_enabled: boolean;
    description: string | null;
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
    const [profileToDelete, setProfileToDelete] = useState<SipProfile | null>(null);

    const { data: profiles = [], isLoading } = useQuery({
        queryKey: ['admin-sip-profiles-security'],
        queryFn: async () => {
            const res = await api.get<any[]>('admin/sip-profiles');
            return (res.data ?? []).map(normalizeProfile);
        },
    });

    const deleteMutation = useApiMutation({
        mutationFn: async (id: string) => {
            await api.delete(`admin/sip-profiles/${id}`);
        },
        successMessage: 'SIP profile deleted.',
        invalidateQueries: [['admin-sip-profiles-security']],
        onSettled: () => setProfileToDelete(null),
    });

    const orderedProfiles = useMemo(
        () => [...profiles].sort((a, b) => a.name.localeCompare(b.name)),
        [profiles],
    );

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <PageHeader
                title="SIP Profiles"
                description="Manage FreeSWITCH SIP profiles and jump straight into a dedicated editor for detailed changes."
                breadcrumbs="Platform Admin › Telephony"
                actionLabel="Create Profile"
                actionRoute="/admin/sip-profiles/create"
            />

            <div className="rounded-md border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Profile Name</TableHead>
                            <TableHead>Hostname</TableHead>
                            <TableHead>Description</TableHead>
                            <TableHead>Settings</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead className="w-[120px] text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {isLoading ? (
                            <TableRow>
                                <TableCell colSpan={6} className="py-8 text-center text-muted-foreground">
                                    Loading SIP profiles...
                                </TableCell>
                            </TableRow>
                        ) : orderedProfiles.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={6} className="py-8 text-center text-muted-foreground">
                                    No SIP profiles configured yet.
                                </TableCell>
                            </TableRow>
                        ) : (
                            orderedProfiles.map((profile) => (
                                <TableRow key={profile.id}>
                                    <TableCell className="font-medium text-primary">{profile.name}</TableCell>
                                    <TableCell className="text-muted-foreground">{profile.hostname ?? '—'}</TableCell>
                                    <TableCell className="max-w-[280px] truncate text-muted-foreground">
                                        {profile.description || '—'}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="outline">{profile.settings.length} params</Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant={profile.is_active ? 'success' : 'secondary'}>
                                            {profile.is_active ? 'Active' : 'Disabled'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-2">
                                            <Button variant="ghost" size="icon" asChild>
                                                <Link to={`/admin/sip-profiles/${profile.id}/edit`}>
                                                    <Edit className="size-4" />
                                                    <span className="sr-only">Edit SIP profile</span>
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => setProfileToDelete(profile)}
                                            >
                                                <Trash2 className="size-4 text-destructive" />
                                                <span className="sr-only">Delete SIP profile</span>
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>

            <DeleteDialog
                open={!!profileToDelete}
                onOpenChange={(open) => !open && setProfileToDelete(null)}
                title="Delete SIP profile"
                description={
                    <>
                        This will permanently delete <strong>{profileToDelete?.name}</strong> and remove its generated XML.
                    </>
                }
                isDeleting={deleteMutation.isPending}
                onConfirm={() => profileToDelete && deleteMutation.mutate(profileToDelete.id)}
            />
        </div>
    );
}
