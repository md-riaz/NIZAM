import { useQuery } from '@tanstack/react-query';
import { Edit, Trash2 } from 'lucide-react';
import { useState } from 'react';
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
import { useOrganization } from '@/context/OrganizationContext';
import api from '@/lib/api';
import { useApiMutation } from '@/lib/api-hooks';
import type { DeviceProfile } from '@/types/models';

export default function DeviceProfilesPage() {
    const { activeOrganization } = useOrganization();
    const [deviceProfileToDelete, setDeviceProfileToDelete] = useState<DeviceProfile | null>(null);

    const { data: deviceProfiles = [], isLoading } = useQuery<DeviceProfile[]>({
        queryKey: ['device-profiles', activeOrganization?.id],
        queryFn: async () => {
            if (!activeOrganization) return [];
            const response = await api.get(`organizations/${activeOrganization.id}/device-profiles`, {
                params: { per_page: 500 },
            });
            return response.data.data;
        },
        enabled: !!activeOrganization,
    });

    const deleteMutation = useApiMutation({
        mutationFn: async (id: string) => {
            await api.delete(`organizations/${activeOrganization?.id}/device-profiles/${id}`);
        },
        successMessage: 'Device profile deleted successfully',
        invalidateQueries: [['device-profiles', activeOrganization?.id || '']],
        onSettled: () => setDeviceProfileToDelete(null),
    });

    if (!activeOrganization) return null;

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <PageHeader
                title="Devices"
                description="Manage shared and standalone device provisioning profiles."
                actionLabel="Create Device"
                actionRoute="/admin/device-profiles/create"
            />

            <div className="rounded-md border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Vendor</TableHead>
                            <TableHead>MAC Address</TableHead>
                            <TableHead>Assigned Extension</TableHead>
                            <TableHead>Default Outbound Number</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead className="w-[100px] text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {isLoading ? (
                            <TableRow>
                                <TableCell colSpan={7} className="py-8 text-center text-muted-foreground">
                                    Loading devices...
                                </TableCell>
                            </TableRow>
                        ) : deviceProfiles.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={7} className="py-8 text-center text-muted-foreground">
                                    No devices found. Create one to get started.
                                </TableCell>
                            </TableRow>
                        ) : (
                            deviceProfiles.map((deviceProfile) => {
                                const defaultDid = deviceProfile.phone_numbers.find(
                                    (phoneNumber) => phoneNumber.id === deviceProfile.default_outbound_did_id,
                                );

                                return (
                                    <TableRow key={deviceProfile.id}>
                                        <TableCell className="font-medium">{deviceProfile.name}</TableCell>
                                        <TableCell>{deviceProfile.vendor || '-'}</TableCell>
                                        <TableCell>{deviceProfile.mac_address || '-'}</TableCell>
                                        <TableCell>{deviceProfile.extension_id || '-'}</TableCell>
                                        <TableCell>
                                            {defaultDid
                                                ? (defaultDid.description ? `${defaultDid.number} — ${defaultDid.description}` : defaultDid.number)
                                                : '-'}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant={deviceProfile.is_active ? 'default' : 'secondary'}>
                                                {deviceProfile.is_active ? 'Active' : 'Inactive'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-2">
                                                <Button variant="ghost" size="icon" asChild>
                                                    <Link to={`/admin/device-profiles/${deviceProfile.id}/edit`}>
                                                        <Edit className="size-4" />
                                                        <span className="sr-only">Edit device profile</span>
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => setDeviceProfileToDelete(deviceProfile)}
                                                >
                                                    <Trash2 className="size-4 text-destructive" />
                                                    <span className="sr-only">Delete device profile</span>
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                );
                            })
                        )}
                    </TableBody>
                </Table>
            </div>

            <DeleteDialog
                open={!!deviceProfileToDelete}
                onOpenChange={(open) => !open && setDeviceProfileToDelete(null)}
                title="Delete Device"
                description={<>Are you sure you want to delete device <strong>{deviceProfileToDelete?.name}</strong>?</>}
                isDeleting={deleteMutation.isPending}
                onConfirm={() => deviceProfileToDelete && deleteMutation.mutate(deviceProfileToDelete.id)}
            />
        </div>
    );
}
