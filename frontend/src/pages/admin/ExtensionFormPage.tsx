import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Save } from 'lucide-react';
import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate, useParams } from 'react-router-dom';
import { z } from 'zod';

import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useOrganization } from '@/context/OrganizationContext';
import api from '@/lib/api';
import type { DeviceProfile, Did, Gateway, User } from '@/types/models';

const ownerTypes = ['unassigned', 'user', 'device'] as const;

const extensionSchema = z.object({
    extension: z.string().min(1, 'Extension number is required'),
    password: z.string().min(8, 'Password must be at least 8 characters'),
    first_name: z.string().min(1, 'First name is required'),
    last_name: z.string().min(1, 'Last name is required'),
    owner_type: z.enum(ownerTypes),
    user_id: z.string().optional(),
    device_profile_id: z.string().optional(),
    effective_caller_id_name: z.string().optional(),
    allowed_outbound_did_ids: z.array(z.string()).default([]),
    default_outbound_did_id: z.string().optional(),
    allowed_outbound_gateway_ids: z.array(z.string()).default([]),
    default_outbound_gateway_id: z.string().optional(),
    voicemail_enabled: z.boolean(),
    voicemail_pin: z.string().optional(),
    is_active: z.boolean(),
}).superRefine((values, ctx) => {
    if (values.owner_type === 'user' && !values.user_id) {
        ctx.addIssue({
            code: z.ZodIssueCode.custom,
            path: ['user_id'],
            message: 'Select a user for this personal extension.',
        });
    }

    if (values.owner_type === 'device' && !values.device_profile_id) {
        ctx.addIssue({
            code: z.ZodIssueCode.custom,
            path: ['device_profile_id'],
            message: 'Select a device for this shared extension.',
        });
    }

    if (values.default_outbound_did_id && values.default_outbound_did_id !== 'none' && !values.allowed_outbound_did_ids.includes(values.default_outbound_did_id)) {
        ctx.addIssue({
            code: z.ZodIssueCode.custom,
            path: ['default_outbound_did_id'],
            message: 'Default outbound number must be included in allowed outbound numbers.',
        });
    }

    if (values.default_outbound_gateway_id && values.default_outbound_gateway_id !== 'none' && !values.allowed_outbound_gateway_ids.includes(values.default_outbound_gateway_id)) {
        ctx.addIssue({
            code: z.ZodIssueCode.custom,
            path: ['default_outbound_gateway_id'],
            message: 'Default outbound gateway must be included in allowed outbound gateways.',
        });
    }
});

const getOwnerLabel = (ownerType: (typeof ownerTypes)[number]) => {
    if (ownerType === 'user') {
        return 'User';
    }

    if (ownerType === 'device') {
        return 'Device';
    }

    return 'Unassigned';
};

const getUserOptionLabel = (user: User) => user.name || user.email;

const getDeviceOptionLabel = (device: DeviceProfile) => device.name;

const normalizeOwnerPayload = (values: ExtensionFormValues) => ({
    ...values,
    user_id: values.owner_type === 'user' ? values.user_id || null : null,
    device_profile_id: values.owner_type === 'device' ? values.device_profile_id || null : null,
});

const toSelectValue = (value?: string | null) => value ?? 'none';

const fromSelectValue = (value: string) => (value === 'none' ? '' : value);

const getErrorMessage = (error: unknown, fallback: string) => {
    if (typeof error === 'object' && error !== null) {
        const response = (error as { response?: { data?: { message?: string } } }).response;
        if (typeof response?.data?.message === 'string' && response.data.message.length > 0) {
            return response.data.message;
        }
    }

    return fallback;
};

const applyServerErrors = (
    error: unknown,
    setError: ReturnType<typeof useForm<ExtensionFormValues>>['setError'],
) => {
    if (typeof error !== 'object' || error === null) {
        return;
    }

    const response = (error as {
        response?: {
            data?: {
                errors?: Record<string, string[] | string>;
            };
        };
    }).response;

    const errors = response?.data?.errors;

    if (!errors) {
        return;
    }

    Object.entries(errors).forEach(([key, messages]) => {
        const message = Array.isArray(messages) ? messages[0] : messages;
        if (!message) {
            return;
        }

        if (key === 'user_id' || key === 'device_profile_id' || key === 'extension' || key === 'password' || key === 'first_name' || key === 'last_name' || key === 'voicemail_pin' || key === 'default_outbound_did_id' || key === 'default_outbound_gateway_id') {
            setError(key, { type: 'server', message });
        }
    });
};

const getNameParts = (user: User) => {
    const parts = user.name.split(' ');

    return {
        first_name: parts[0] ?? '',
        last_name: parts.slice(1).join(' '),
    };
};

const getExtensionName = (values: ExtensionFormValues) =>
    `${values.first_name} ${values.last_name}`.trim() || 'Unassigned';

const getOwnerTypeDescription = (ownerType: (typeof ownerTypes)[number]) => {
    if (ownerType === 'user') {
        return 'Personal extension. One extension per user.';
    }

    if (ownerType === 'device') {
        return 'Shared device extension. Device can work without user login.';
    }

    return 'Reserved extension with no current owner.';
};

const getAssignedUserId = (extension: { user_id?: string | null }) => extension.user_id ?? '';
const getAssignedDeviceId = (extension: { device_profile_id?: string | null }) => extension.device_profile_id ?? '';

type ExtensionFormValues = z.infer<typeof extensionSchema>;

export default function ExtensionFormPage() {
    const { id } = useParams<{ id: string }>();
    const isEdit = Boolean(id && id !== 'new');
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { activeOrganization, organizationApiPrefix } = useOrganization();

    const form = useForm<ExtensionFormValues>({
        resolver: zodResolver(extensionSchema),
        defaultValues: {
            extension: '',
            password: '',
            first_name: '',
            last_name: '',
            owner_type: 'unassigned',
            user_id: '',
            device_profile_id: '',
            effective_caller_id_name: '',
            allowed_outbound_did_ids: [],
            default_outbound_did_id: 'none',
            allowed_outbound_gateway_ids: [],
            default_outbound_gateway_id: 'none',
            voicemail_enabled: true,
            voicemail_pin: '',
            is_active: true,
        },
    });

    const { data: extension, isLoading: isFetching } = useQuery({
        queryKey: ['extension', activeOrganization?.id, id],
        queryFn: async () => {
            const response = await api.get(`${organizationApiPrefix}/extensions/${id}`);
            return response.data.data;
        },
        enabled: isEdit && Boolean(activeOrganization),
    });

    const { data: users = [], isLoading: isLoadingUsers } = useQuery({
        queryKey: ['users', activeOrganization?.id, 'extension-owner-options'],
        queryFn: async () => {
            const response = await api.get<{ data: User[] }>('users', {
                params: { organization_id: activeOrganization?.id, per_page: 500 },
            });
            return response.data.data;
        },
        enabled: Boolean(activeOrganization),
    });

    const { data: deviceProfiles = [], isLoading: isLoadingDeviceProfiles } = useQuery({
        queryKey: ['device-profiles', activeOrganization?.id, 'extension-owner-options'],
        queryFn: async () => {
            const response = await api.get<{ data: DeviceProfile[] }>(`${organizationApiPrefix}/device-profiles`, {
                params: { per_page: 500 },
            });
            return response.data.data;
        },
        enabled: Boolean(activeOrganization),
    });

    const { data: dids = [] } = useQuery({
        queryKey: ['dids', activeOrganization?.id, 'extension-outbound-options'],
        queryFn: async () => {
            const response = await api.get<{ data: Did[] }>(`${organizationApiPrefix}/dids`, {
                params: { per_page: 500 },
            });
            return response.data.data;
        },
        enabled: Boolean(activeOrganization),
    });

    const { data: gateways = [] } = useQuery({
        queryKey: ['gateways', activeOrganization?.id, 'extension-outbound-options'],
        queryFn: async () => {
            const response = await api.get<{ data: Gateway[] }>(`${organizationApiPrefix}/gateways`, {
                params: { per_page: 500 },
            });
            return response.data.data;
        },
        enabled: Boolean(activeOrganization),
    });

    useEffect(() => {
        if (extension) {
            form.reset({
                extension: extension.extension ?? '',
                password: '',
                first_name: extension.first_name ?? '',
                last_name: extension.last_name ?? '',
                owner_type: extension.owner_type ?? 'unassigned',
                user_id: getAssignedUserId(extension),
                device_profile_id: getAssignedDeviceId(extension),
                effective_caller_id_name: extension.effective_caller_id_name ?? '',
                allowed_outbound_did_ids: extension.allowed_outbound_did_ids ?? [],
                default_outbound_did_id: extension.default_outbound_did_id ?? 'none',
                allowed_outbound_gateway_ids: extension.allowed_outbound_gateway_ids ?? [],
                default_outbound_gateway_id: extension.default_outbound_gateway_id ?? 'none',
                voicemail_enabled: extension.voicemail_enabled ?? true,
                voicemail_pin: extension.voicemail_pin ?? '',
                is_active: extension.is_active ?? true,
            });
        }
    }, [extension, form]);

    const mutation = useMutation({
        mutationFn: async (values: ExtensionFormValues) => {
            const payload = {
                ...normalizeOwnerPayload(values),
                default_outbound_did_id: values.default_outbound_did_id && values.default_outbound_did_id !== 'none'
                    ? values.default_outbound_did_id
                    : null,
                default_outbound_gateway_id: values.default_outbound_gateway_id && values.default_outbound_gateway_id !== 'none'
                    ? values.default_outbound_gateway_id
                    : null,
            };

            if (isEdit) {
                return api.put(`${organizationApiPrefix}/extensions/${id}`, payload);
            }

            return api.post(`${organizationApiPrefix}/extensions`, payload);
        },
        onError: (error) => {
            applyServerErrors(error, form.setError);
        },
        onSuccess: async () => {
            await Promise.all([
                queryClient.invalidateQueries({ queryKey: ['extensions', activeOrganization?.id] }),
                queryClient.invalidateQueries({ queryKey: ['device-profiles', activeOrganization?.id] }),
                queryClient.invalidateQueries({ queryKey: ['users', activeOrganization?.id] }),
            ]);
            navigate('/admin/extensions');
        },
    });

    const ownerType = form.watch('owner_type');
    const selectedUserId = form.watch('user_id');
    const isLoadingOwnerOptions = isLoadingUsers || isLoadingDeviceProfiles;

    useEffect(() => {
        if (ownerType === 'user') {
            form.setValue('device_profile_id', '', { shouldDirty: true, shouldValidate: true });
            return;
        }

        if (ownerType === 'device') {
            form.setValue('user_id', '', { shouldDirty: true, shouldValidate: true });
            return;
        }

        form.setValue('user_id', '', { shouldDirty: true, shouldValidate: true });
        form.setValue('device_profile_id', '', { shouldDirty: true, shouldValidate: true });
    }, [form, ownerType]);

    useEffect(() => {
        if (ownerType !== 'user' || !selectedUserId) {
            return;
        }

        const selectedUser = users.find((user) => user.id === selectedUserId);

        if (!selectedUser) {
            return;
        }

        const currentName = getExtensionName(form.getValues());
        const { first_name, last_name } = getNameParts(selectedUser);

        if (!currentName || currentName === 'Unassigned') {
            form.setValue('first_name', first_name, { shouldDirty: true });
            form.setValue('last_name', last_name, { shouldDirty: true });
        }
    }, [form, ownerType, selectedUserId, users]);

    const allowedOutboundDids = dids.filter((did) => did.is_active ?? true);
    const allowedOutboundGateways = gateways.filter((gateway) => gateway.is_active ?? gateway.enabled ?? true);

    const availableUsers = users;

    const availableDeviceProfiles = deviceProfiles.filter((device) => {
        if (extension?.device_profile_id === device.id) {
            return true;
        }

        return !device.extension_id;
    });

    const ownerTypeDescription = getOwnerTypeDescription(ownerType);
    const mutationError = mutation.error ? getErrorMessage(mutation.error, 'Unable to save extension.') : null;
    const isOwnerLocked = mutation.isPending || isLoadingOwnerOptions;
    const isUserOwner = ownerType === 'user';
    const isDeviceOwner = ownerType === 'device';

    if (!activeOrganization) {
        return (
            <div className="flex h-64 items-center justify-center text-muted-foreground">
                Select a organization to manage extensions.
            </div>
        );
    }

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div className="flex items-center gap-4">
                <Button variant="ghost" size="icon" onClick={() => navigate('/admin/extensions')}>
                    <ArrowLeft className="size-4" />
                    <span className="sr-only">Back to extensions</span>
                </Button>
                <div>
                    <p className="text-sm text-muted-foreground">{activeOrganization.name} › Phone System</p>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {isEdit ? 'Edit Extension' : 'Create Extension'}
                    </h1>
                </div>
            </div>

            <Card className="max-w-4xl">
                <CardHeader>
                    <CardTitle>Extension profile</CardTitle>
                    <CardDescription>
                        Configure SIP credentials, ownership, and outbound DID and gateway policy.
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
                                onSubmit={form.handleSubmit((values: ExtensionFormValues) => mutation.mutate(values))}
                                className="space-y-6"
                            >
                                {mutationError && (
                                    <div className="rounded-md border border-destructive/40 bg-destructive/5 px-4 py-3 text-sm text-destructive">
                                        {mutationError}
                                    </div>
                                )}

                                <div className="grid gap-6 md:grid-cols-2">
                                    <FormField
                                        control={form.control}
                                        name="extension"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Extension</FormLabel>
                                                <FormControl>
                                                    <Input placeholder="1001" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                    <FormField
                                        control={form.control}
                                        name="password"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>SIP password</FormLabel>
                                                <FormControl>
                                                    <Input type="password" autoComplete="new-password" {...field} />
                                                </FormControl>
                                                <FormDescription>
                                                    Required for phone registration and WebRTC clients.
                                                </FormDescription>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                    <FormField
                                        control={form.control}
                                        name="owner_type"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Owner type</FormLabel>
                                                <Select
                                                    onValueChange={(value) => {
                                                        if (!ownerTypes.includes(value as (typeof ownerTypes)[number])) {
                                                            return;
                                                        }

                                                        field.onChange(value);
                                                    }}
                                                    value={field.value}
                                                >
                                                    <FormControl>
                                                        <SelectTrigger disabled={isOwnerLocked}>
                                                            <SelectValue placeholder="Select owner type" />
                                                        </SelectTrigger>
                                                    </FormControl>
                                                    <SelectContent>
                                                        {ownerTypes.map((type) => (
                                                            <SelectItem key={type} value={type}>
                                                                {getOwnerLabel(type)}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <FormDescription>{ownerTypeDescription}</FormDescription>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                    {isUserOwner ? (
                                        <FormField
                                            control={form.control}
                                            name="user_id"
                                            render={({ field }) => (
                                                <FormItem>
                                                    <FormLabel>Assigned user</FormLabel>
                                                    <Select
                                                        onValueChange={(value) => field.onChange(fromSelectValue(value))}
                                                        value={toSelectValue(field.value)}
                                                    >
                                                        <FormControl>
                                                            <SelectTrigger disabled={isOwnerLocked}>
                                                                <SelectValue placeholder="Select a user" />
                                                            </SelectTrigger>
                                                        </FormControl>
                                                        <SelectContent>
                                                            <SelectItem value="none">No user selected</SelectItem>
                                                            {availableUsers.map((user) => (
                                                                <SelectItem key={user.id} value={user.id}>
                                                                    {getUserOptionLabel(user)}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                    <FormDescription>Each user can have only one personal extension.</FormDescription>
                                                    <FormMessage />
                                                </FormItem>
                                            )}
                                        />
                                    ) : isDeviceOwner ? (
                                        <FormField
                                            control={form.control}
                                            name="device_profile_id"
                                            render={({ field }) => (
                                                <FormItem>
                                                    <FormLabel>Assigned device</FormLabel>
                                                    <Select
                                                        onValueChange={(value) => field.onChange(fromSelectValue(value))}
                                                        value={toSelectValue(field.value)}
                                                    >
                                                        <FormControl>
                                                            <SelectTrigger disabled={isOwnerLocked}>
                                                                <SelectValue placeholder="Select a device" />
                                                            </SelectTrigger>
                                                        </FormControl>
                                                        <SelectContent>
                                                            <SelectItem value="none">No device selected</SelectItem>
                                                            {availableDeviceProfiles.map((device) => (
                                                                <SelectItem key={device.id} value={device.id}>
                                                                    {getDeviceOptionLabel(device)}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                    <FormDescription>Shared devices can own one extension directly.</FormDescription>
                                                    <FormMessage />
                                                </FormItem>
                                            )}
                                        />
                                    ) : (
                                        <div className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                                            This extension is reserved and not assigned yet.
                                        </div>
                                    )}
                                    <FormField
                                        control={form.control}
                                        name="first_name"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>First name</FormLabel>
                                                <FormControl>
                                                    <Input placeholder="Jane" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                    <FormField
                                        control={form.control}
                                        name="last_name"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Last name</FormLabel>
                                                <FormControl>
                                                    <Input placeholder="Doe" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                    <FormField
                                        control={form.control}
                                        name="effective_caller_id_name"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Caller ID name</FormLabel>
                                                <FormControl>
                                                    <Input placeholder="Jane Doe" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                </div>

                                <div className="grid gap-6 lg:grid-cols-2">
                                    <FormField
                                        control={form.control}
                                        name="allowed_outbound_did_ids"
                                        render={({ field }) => (
                                            <FormItem className="space-y-3">
                                                <div>
                                                    <FormLabel>Allowed outbound numbers</FormLabel>
                                                    <FormDescription>
                                                        Select numbers this extension can present on outbound calls.
                                                    </FormDescription>
                                                </div>
                                                <div className="space-y-3 rounded-md border p-4">
                                                    {allowedOutboundDids.length === 0 ? (
                                                        <p className="text-sm text-muted-foreground">No active numbers available in this organization.</p>
                                                    ) : (
                                                        allowedOutboundDids.map((did) => (
                                                            <label key={did.id} className="flex items-start gap-3 text-sm">
                                                                <Checkbox
                                                                    checked={field.value.includes(did.id)}
                                                                    onCheckedChange={(checked) => {
                                                                        field.onChange(
                                                                            checked
                                                                                ? [...field.value, did.id]
                                                                                : field.value.filter((value) => value !== did.id),
                                                                        );
                                                                        if (form.getValues('default_outbound_did_id') === did.id && !checked) {
                                                                            form.setValue('default_outbound_did_id', 'none');
                                                                        }
                                                                    }}
                                                                />
                                                                <span>{did.description ? `${did.number} — ${did.description}` : did.number}</span>
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
                                        name="allowed_outbound_gateway_ids"
                                        render={({ field }) => (
                                            <FormItem className="space-y-3">
                                                <div>
                                                    <FormLabel>Allowed outbound gateways</FormLabel>
                                                    <FormDescription>
                                                        Select gateways this extension can use for outbound routing.
                                                    </FormDescription>
                                                </div>
                                                <div className="space-y-3 rounded-md border p-4">
                                                    {allowedOutboundGateways.length === 0 ? (
                                                        <p className="text-sm text-muted-foreground">No active gateways available in this organization.</p>
                                                    ) : (
                                                        allowedOutboundGateways.map((gateway) => (
                                                            <label key={gateway.id} className="flex items-start gap-3 text-sm">
                                                                <Checkbox
                                                                    checked={field.value.includes(gateway.id)}
                                                                    onCheckedChange={(checked) => {
                                                                        field.onChange(
                                                                            checked
                                                                                ? [...field.value, gateway.id]
                                                                                : field.value.filter((value) => value !== gateway.id),
                                                                        );
                                                                        if (form.getValues('default_outbound_gateway_id') === gateway.id && !checked) {
                                                                            form.setValue('default_outbound_gateway_id', 'none');
                                                                        }
                                                                    }}
                                                                />
                                                                <span>{gateway.name}</span>
                                                            </label>
                                                        ))
                                                    )}
                                                </div>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                </div>

                                <div className="grid gap-6 md:grid-cols-2">
                                    <FormField
                                        control={form.control}
                                        name="default_outbound_did_id"
                                        render={({ field }) => {
                                            const availableDefaultDids = allowedOutboundDids.filter((did) =>
                                                form.getValues('allowed_outbound_did_ids').includes(did.id),
                                            );

                                            return (
                                                <FormItem>
                                                    <FormLabel>Default outbound number</FormLabel>
                                                    <Select
                                                        onValueChange={field.onChange}
                                                        value={field.value || 'none'}
                                                    >
                                                        <FormControl>
                                                            <SelectTrigger>
                                                                <SelectValue placeholder="Select default outbound number" />
                                                            </SelectTrigger>
                                                        </FormControl>
                                                        <SelectContent>
                                                            <SelectItem value="none">No default outbound number</SelectItem>
                                                            {availableDefaultDids.map((did) => (
                                                                <SelectItem key={did.id} value={did.id}>
                                                                    {did.description ? `${did.number} — ${did.description}` : did.number}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                    <FormDescription>
                                                        Default must come from this extension&apos;s allowed outbound numbers.
                                                    </FormDescription>
                                                    <FormMessage />
                                                </FormItem>
                                            );
                                        }}
                                    />
                                    <FormField
                                        control={form.control}
                                        name="default_outbound_gateway_id"
                                        render={({ field }) => {
                                            const availableDefaultGateways = allowedOutboundGateways.filter((gateway) =>
                                                form.getValues('allowed_outbound_gateway_ids').includes(gateway.id),
                                            );

                                            return (
                                                <FormItem>
                                                    <FormLabel>Default outbound gateway</FormLabel>
                                                    <Select
                                                        onValueChange={field.onChange}
                                                        value={field.value || 'none'}
                                                    >
                                                        <FormControl>
                                                            <SelectTrigger>
                                                                <SelectValue placeholder="Select default outbound gateway" />
                                                            </SelectTrigger>
                                                        </FormControl>
                                                        <SelectContent>
                                                            <SelectItem value="none">No default outbound gateway</SelectItem>
                                                            {availableDefaultGateways.map((gateway) => (
                                                                <SelectItem key={gateway.id} value={gateway.id}>
                                                                    {gateway.name}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                    <FormDescription>
                                                        Default must come from this extension&apos;s allowed outbound gateways.
                                                    </FormDescription>
                                                    <FormMessage />
                                                </FormItem>
                                            );
                                        }}
                                    />
                                </div>

                                <div className="grid gap-6 md:grid-cols-2">
                                    <FormField
                                        control={form.control}
                                        name="voicemail_pin"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Voicemail PIN</FormLabel>
                                                <FormControl>
                                                    <Input placeholder="1234" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <FormField
                                        control={form.control}
                                        name="voicemail_enabled"
                                        render={({ field }) => (
                                            <FormItem className="flex flex-row items-start space-x-3 space-y-0 rounded-md border p-4">
                                                <FormControl>
                                                    <Checkbox checked={field.value} onCheckedChange={field.onChange} />
                                                </FormControl>
                                                <div className="space-y-1 leading-none">
                                                    <FormLabel>Enable voicemail</FormLabel>
                                                    <FormDescription>Allow callers to leave messages for this extension.</FormDescription>
                                                </div>
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
                                                    <FormLabel>Extension active</FormLabel>
                                                    <FormDescription>Inactive extensions cannot register or receive calls.</FormDescription>
                                                </div>
                                            </FormItem>
                                        )}
                                    />
                                </div>

                                <div className="flex justify-end">
                                    <Button type="submit" disabled={mutation.isPending}>
                                        <Save className="mr-2 size-4" />
                                        {mutation.isPending ? 'Saving...' : isEdit ? 'Save Extension' : 'Create Extension'}
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
