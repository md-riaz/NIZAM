import { zodResolver } from '@hookform/resolvers/zod';
import { useQuery } from '@tanstack/react-query';
import { Save } from 'lucide-react';
import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate, useParams } from 'react-router-dom';
import { z } from 'zod';

import { PageHeader } from '@/components/scaffolds/PageHeader';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Form,
    FormControl,
    FormDescription,
    FormField,
    FormItem,
    FormLabel,
    FormMessage,
} from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useOrganization } from '@/context/OrganizationContext';
import api from '@/lib/api';
import { useApiMutation } from '@/lib/api-hooks';
import type { DeviceProfile, Did, Extension } from '@/types/models';

const deviceProfileSchema = z.object({
    name: z.string().min(1, 'Name is required'),
    vendor: z.string().min(1, 'Vendor is required'),
    mac_address: z.string().optional(),
    template: z.string().optional(),
    extension_id: z.string().optional(),
    phone_number_ids: z.array(z.string()).default([]),
    default_outbound_did_id: z.string().optional(),
    is_active: z.boolean(),
}).superRefine((values, ctx) => {
    if (values.default_outbound_did_id && values.default_outbound_did_id !== 'none' && !values.phone_number_ids.includes(values.default_outbound_did_id)) {
        ctx.addIssue({
            code: z.ZodIssueCode.custom,
            path: ['default_outbound_did_id'],
            message: 'Default outbound number must also be granted to this device.',
        });
    }
});

type DeviceProfileFormValues = z.infer<typeof deviceProfileSchema>;

export default function DeviceProfileFormPage() {
    const { id } = useParams<{ id: string }>();
    const isEdit = Boolean(id);
    const navigate = useNavigate();
    const { activeOrganization } = useOrganization();

    const form = useForm<DeviceProfileFormValues>({
        resolver: zodResolver(deviceProfileSchema),
        defaultValues: {
            name: '',
            vendor: '',
            mac_address: '',
            template: '',
            extension_id: 'none',
            phone_number_ids: [],
            default_outbound_did_id: 'none',
            is_active: true,
        },
    });

    const { data: deviceProfile, isLoading: isFetching } = useQuery<DeviceProfile>({
        queryKey: ['device-profile', activeOrganization?.id, id],
        queryFn: async () => {
            if (!activeOrganization) {
                throw new Error('No active organization');
            }

            const response = await api.get<{ data: DeviceProfile }>(`organizations/${activeOrganization.id}/device-profiles/${id}`);
            return response.data.data;
        },
        enabled: isEdit && !!activeOrganization,
    });

    const { data: extensions = [], isLoading: isLoadingExtensions } = useQuery<Extension[]>({
        queryKey: ['extensions', activeOrganization?.id, 'device-profile-options'],
        queryFn: async () => {
            if (!activeOrganization) return [];
            const response = await api.get<{ data: Extension[] }>(`organizations/${activeOrganization.id}/extensions`, {
                params: { per_page: 500 },
            });
            return response.data.data;
        },
        enabled: !!activeOrganization,
    });

    const { data: phoneNumbers = [] } = useQuery<Did[]>({
        queryKey: ['dids', activeOrganization?.id, 'device-profile-options'],
        queryFn: async () => {
            if (!activeOrganization) return [];
            const response = await api.get<{ data: Did[] }>(`organizations/${activeOrganization.id}/dids`, {
                params: { per_page: 500 },
            });
            return response.data.data;
        },
        enabled: !!activeOrganization,
    });

    useEffect(() => {
        if (deviceProfile) {
            form.reset({
                name: deviceProfile.name ?? '',
                vendor: deviceProfile.vendor ?? '',
                mac_address: deviceProfile.mac_address ?? '',
                template: deviceProfile.template ?? '',
                extension_id: deviceProfile.extension_id ?? 'none',
                phone_number_ids: deviceProfile.phone_numbers?.map((phoneNumber) => phoneNumber.id) ?? [],
                default_outbound_did_id: deviceProfile.default_outbound_did_id ?? 'none',
                is_active: deviceProfile.is_active ?? true,
            });
        }
    }, [deviceProfile, form]);

    const mutation = useApiMutation({
        mutationFn: async (values: DeviceProfileFormValues) => {
            if (!activeOrganization) throw new Error('No active organization');

            const payload = {
                ...values,
                extension_id: values.extension_id && values.extension_id !== 'none' ? values.extension_id : null,
                default_outbound_did_id: values.default_outbound_did_id && values.default_outbound_did_id !== 'none'
                    ? values.default_outbound_did_id
                    : null,
            };

            if (isEdit) {
                return api.put(`organizations/${activeOrganization.id}/device-profiles/${id}`, payload);
            }

            return api.post(`organizations/${activeOrganization.id}/device-profiles`, payload);
        },
        successMessage: `Device ${isEdit ? 'updated' : 'created'} successfully`,
        invalidateQueries: [['device-profiles', activeOrganization?.id || '']],
        onSuccess: () => navigate('/admin/device-profiles'),
    });

    const selectedPhoneNumberIds = form.watch('phone_number_ids');

    if (!activeOrganization) return null;

    const grantedPhoneNumbers = phoneNumbers.filter((phoneNumber) =>
        selectedPhoneNumberIds.includes(phoneNumber.id),
    );

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <PageHeader
                title={isEdit ? 'Edit Device' : 'Create Device'}
                breadcrumbs="Platform administration"
                actionLabel="Back to Devices"
                actionRoute="/admin/device-profiles"
                actionIcon={null}
            />

            <Card className="max-w-4xl">
                <CardHeader>
                    <CardTitle>Device Profile</CardTitle>
                    <CardDescription>
                        Configure shared or standalone device provisioning and outbound caller-ID access.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {isFetching ? (
                        <div className="flex h-32 items-center justify-center">
                            <div className="size-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                        </div>
                    ) : (
                        <Form {...form}>
                            <form
                                onSubmit={form.handleSubmit((values) => mutation.mutate(values))}
                                className="space-y-6"
                            >
                                <div className="grid gap-6 md:grid-cols-2">
                                    <FormField
                                        control={form.control}
                                        name="name"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Device Name</FormLabel>
                                                <FormControl>
                                                    <Input placeholder="Lobby Phone" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <FormField
                                        control={form.control}
                                        name="vendor"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Vendor</FormLabel>
                                                <FormControl>
                                                    <Input placeholder="Yealink" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <FormField
                                        control={form.control}
                                        name="mac_address"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>MAC Address</FormLabel>
                                                <FormControl>
                                                    <Input placeholder="00:11:22:33:44:55" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <FormField
                                        control={form.control}
                                        name="template"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Provisioning Template</FormLabel>
                                                <FormControl>
                                                    <Input placeholder="yealink-t54w" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <FormField
                                        control={form.control}
                                        name="extension_id"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Assigned Extension</FormLabel>
                                                <Select
                                                    onValueChange={(value) => {
                                                        if (value !== 'none' && !extensions.some((extension) => extension.id === value)) {
                                                            return;
                                                        }
                                                        field.onChange(value);
                                                    }}
                                                    value={field.value}
                                                >
                                                    <FormControl>
                                                        <SelectTrigger disabled={isLoadingExtensions}>
                                                            <SelectValue placeholder="Select an extension" />
                                                        </SelectTrigger>
                                                    </FormControl>
                                                    <SelectContent>
                                                        <SelectItem value="none">No assigned extension</SelectItem>
                                                        {extensions.map((extension) => (
                                                            <SelectItem key={extension.id} value={extension.id}>
                                                                {extension.extension}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <FormDescription>
                                                    Leave unassigned for shared devices that are provisioned before extension mapping.
                                                </FormDescription>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <FormField
                                        control={form.control}
                                        name="default_outbound_did_id"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Default Outbound Number</FormLabel>
                                                <Select
                                                    onValueChange={(value) => {
                                                        if (value !== 'none' && !grantedPhoneNumbers.some((phoneNumber) => phoneNumber.id === value)) {
                                                            return;
                                                        }
                                                        field.onChange(value);
                                                    }}
                                                    value={field.value}
                                                >
                                                    <FormControl>
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Select a default outbound number" />
                                                        </SelectTrigger>
                                                    </FormControl>
                                                    <SelectContent>
                                                        <SelectItem value="none">No default outbound number</SelectItem>
                                                        {grantedPhoneNumbers.map((phoneNumber) => (
                                                            <SelectItem key={phoneNumber.id} value={phoneNumber.id}>
                                                                {phoneNumber.description ? `${phoneNumber.number} — ${phoneNumber.description}` : phoneNumber.number}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <FormDescription>
                                                    Default must be chosen from this device&apos;s granted phone numbers.
                                                </FormDescription>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                </div>

                                <FormField
                                    control={form.control}
                                    name="phone_number_ids"
                                    render={({ field }) => (
                                        <FormItem className="space-y-3">
                                            <div>
                                                <FormLabel>Granted Phone Numbers</FormLabel>
                                                <FormDescription>
                                                    These numbers become available outbound caller-ID options for this device.
                                                </FormDescription>
                                            </div>
                                            <div className="space-y-3 rounded-md border p-4">
                                                {phoneNumbers.length === 0 ? (
                                                    <p className="text-sm text-muted-foreground">No phone numbers available in this organization.</p>
                                                ) : (
                                                    phoneNumbers.map((phoneNumber) => (
                                                        <label key={phoneNumber.id} className="flex items-start gap-3 text-sm">
                                                            <Checkbox
                                                                checked={field.value.includes(phoneNumber.id)}
                                                                onCheckedChange={(checked) => {
                                                                    field.onChange(
                                                                        checked
                                                                            ? [...field.value, phoneNumber.id]
                                                                            : field.value.filter((value) => value !== phoneNumber.id),
                                                                    );
                                                                    if (form.getValues('default_outbound_did_id') === phoneNumber.id && !checked) {
                                                                        form.setValue('default_outbound_did_id', 'none');
                                                                    }
                                                                }}
                                                            />
                                                            <span>{phoneNumber.description ? `${phoneNumber.number} — ${phoneNumber.description}` : phoneNumber.number}</span>
                                                        </label>
                                                    ))
                                                )}
                                            </div>
                                            <FormMessage />
                                        </FormItem>
                                    )}
                                />

                                <FormField
                                    control={form.control}
                                    name="is_active"
                                    render={({ field }) => (
                                        <FormItem className="flex flex-row items-start space-x-3 space-y-0 rounded-md border p-4">
                                            <FormControl>
                                                <Checkbox checked={field.value} onCheckedChange={field.onChange} />
                                            </FormControl>
                                            <div className="space-y-1 leading-none">
                                                <FormLabel>Active Status</FormLabel>
                                                <FormDescription>
                                                    Inactive devices remain configured but should not be used for active provisioning or routing.
                                                </FormDescription>
                                            </div>
                                        </FormItem>
                                    )}
                                />

                                <div className="flex justify-end">
                                    <Button type="submit" disabled={mutation.isPending}>
                                        <Save className="mr-2 size-4" />
                                        {mutation.isPending ? 'Saving...' : isEdit ? 'Save Changes' : 'Create Device'}
                                    </Button>
                                </div>
                            </form>
                        </Form>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
