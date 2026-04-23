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
import {
    Form,
    FormControl,
    FormDescription,
    FormField,
    FormItem,
    FormLabel,
    FormMessage,
} from '@/components/ui/form';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import api from '@/lib/api';
import type { Did, Organization } from '@/types/models';

const userSchema = z.object({
    name: z.string().min(1, 'Name is required'),
    email: z.string().email('A valid email is required'),
    password: z.string().optional(),
    role: z.enum(['superadmin', 'admin', 'agent']),
    organization_id: z.string().optional(),
    direct_phone_number_ids: z.array(z.string()).default([]),
    default_outbound_did_id: z.string().optional(),
}).superRefine((values, ctx) => {
    if (values.default_outbound_did_id && !values.direct_phone_number_ids.includes(values.default_outbound_did_id)) {
        ctx.addIssue({
            code: z.ZodIssueCode.custom,
            path: ['default_outbound_did_id'],
            message: 'Default outbound number must also be directly granted.',
        });
    }
});

type UserFormValues = z.infer<typeof userSchema>;

export default function UserFormPage() {
    const { id } = useParams<{ id: string }>();
    const isEdit = Boolean(id);
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    const form = useForm<UserFormValues>({
        resolver: zodResolver(
            userSchema.superRefine((values: UserFormValues, ctx: z.RefinementCtx) => {
                if (!isEdit && (!values.password || values.password.length < 8)) {
                    ctx.addIssue({
                        code: z.ZodIssueCode.custom,
                        path: ['password'],
                        message: 'Password must be at least 8 characters.',
                    });
                }
            }),
        ),
        defaultValues: {
            name: '',
            email: '',
            password: '',
            role: 'agent',
            organization_id: 'global',
            direct_phone_number_ids: [],
            default_outbound_did_id: 'none',
        } satisfies UserFormValues,
    });

    const { data: user, isLoading: isFetching } = useQuery({
        queryKey: ['user', id],
        queryFn: async () => {
            const response = await api.get(`users/${id}`);
            return response.data.data;
        },
        enabled: isEdit,
    });

    const { data: organizations = [] } = useQuery({
        queryKey: ['organizations'],
        queryFn: async () => {
            const response = await api.get<{ data: Organization[] }>('organizations');
            return response.data.data;
        },
    });

    const selectedOrganizationId = form.watch('organization_id');

    const { data: directPhoneNumbers = [] } = useQuery({
        queryKey: ['dids', selectedOrganizationId, 'user-direct-phone-options'],
        queryFn: async () => {
            if (!selectedOrganizationId || selectedOrganizationId === 'global') {
                return [] as Did[];
            }

            const response = await api.get<{ data: Did[] }>(`organizations/${selectedOrganizationId}/dids`);
            return response.data.data;
        },
        enabled: Boolean(selectedOrganizationId) && selectedOrganizationId !== 'global',
    });

    useEffect(() => {
        if (user) {
            form.reset({
                name: user.name ?? '',
                email: user.email ?? '',
                password: '',
                role: user.role === 'superadmin' || user.role === 'admin' ? user.role : 'agent',
                organization_id: user.organization_id ?? 'global',
                direct_phone_number_ids: user.direct_phone_numbers?.map((phoneNumber: Did) => phoneNumber.id) ?? [],
                default_outbound_did_id: user.default_outbound_did_id ?? 'none',
            });
        }
    }, [user, form]);

    const mutation = useMutation({
        mutationFn: async (values: UserFormValues) => {
            const payload = {
                ...values,
                organization_id: values.organization_id === 'global' ? null : values.organization_id,
                default_outbound_did_id: values.default_outbound_did_id && values.default_outbound_did_id !== 'none'
                    ? values.default_outbound_did_id
                    : null,
                direct_phone_number_ids: values.organization_id === 'global'
                    ? []
                    : values.direct_phone_number_ids,
            };

            if (isEdit && !values.password) {
                delete (payload as { password?: string }).password;
            }

            if (isEdit) {
                return api.put(`users/${id}`, payload);
            }

            return api.post('users', payload);
        },
        onSuccess: async () => {
            await queryClient.invalidateQueries({ queryKey: ['users'] });
            navigate('/admin/users');
        },
    });

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div className="flex items-center gap-4">
                <Button variant="ghost" size="icon" onClick={() => navigate('/admin/users')}>
                    <ArrowLeft className="size-4" />
                    <span className="sr-only">Back to users</span>
                </Button>
                <div>
                    <p className="text-sm text-muted-foreground">Platform administration</p>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {isEdit ? 'Edit User' : 'Create User'}
                    </h1>
                </div>
            </div>

            <Card className="max-w-3xl">
                <CardHeader>
                    <CardTitle>User profile</CardTitle>
                    <CardDescription>
                        Create platform or organization-scoped users and assign their role.
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
                                onSubmit={form.handleSubmit((values: UserFormValues) => mutation.mutate(values))}
                                className="space-y-6"
                            >
                                <div className="grid gap-6 md:grid-cols-2">
                                    <FormField
                                        control={form.control}
                                        name="name"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Name</FormLabel>
                                                <FormControl>
                                                    <Input placeholder="Jane Doe" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <FormField
                                        control={form.control}
                                        name="email"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Email</FormLabel>
                                                <FormControl>
                                                    <Input type="email" placeholder="jane@example.com" {...field} />
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
                                                <FormLabel>{isEdit ? 'New password' : 'Password'}</FormLabel>
                                                <FormControl>
                                                    <Input type="password" autoComplete="new-password" {...field} />
                                                </FormControl>
                                                <FormDescription>
                                                    {isEdit ? 'Leave blank to keep the current password.' : 'Use at least 8 characters.'}
                                                </FormDescription>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <FormField
                                        control={form.control}
                                        name="role"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Role</FormLabel>
                                                <Select
                                                    onValueChange={(value) => {
                                                        if (value !== 'superadmin' && value !== 'admin' && value !== 'agent') {
                                                            return;
                                                        }

                                                        field.onChange(value);
                                                    }}
                                                    value={field.value}
                                                >
                                                    <FormControl>
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Select role" />
                                                        </SelectTrigger>
                                                    </FormControl>
                                                    <SelectContent>
                                                        <SelectItem value="superadmin">Superadmin</SelectItem>
                                                        <SelectItem value="admin">Admin</SelectItem>
                                                        <SelectItem value="agent">Agent</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                </div>

                                <FormField
                                    control={form.control}
                                    name="organization_id"
                                    render={({ field }) => (
                                        <FormItem>
                                            <FormLabel>Organization scope</FormLabel>
                                            <Select
                                                onValueChange={(value) => {
                                                    if (value !== 'global' && !organizations.some((organization) => organization.id === value)) {
                                                        return;
                                                    }

                                                    field.onChange(value);
                                                    form.setValue('direct_phone_number_ids', []);
                                                    form.setValue('default_outbound_did_id', 'none');
                                                }}
                                                value={field.value}
                                            >
                                                <FormControl>
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="Select organization scope" />
                                                    </SelectTrigger>
                                                </FormControl>
                                                <SelectContent>
                                                    <SelectItem value="global">Global platform user</SelectItem>
                                                    {organizations.map((organization) => (
                                                        <SelectItem key={organization.id} value={organization.id}>
                                                            {organization.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <FormDescription>
                                                Global users have no organization assignment. Organization users are scoped to one organization.
                                            </FormDescription>
                                            <FormMessage />
                                        </FormItem>
                                    )}
                                />

                                {selectedOrganizationId !== 'global' && (
                                    <>
                                        <FormField
                                            control={form.control}
                                            name="direct_phone_number_ids"
                                            render={({ field }) => (
                                                <FormItem className="space-y-3">
                                                    <div>
                                                        <FormLabel>Direct phone number grants</FormLabel>
                                                        <FormDescription>
                                                            Direct grants are always available to this user. Team-based numbers appear automatically in effective access.
                                                        </FormDescription>
                                                    </div>
                                                    <div className="space-y-3 rounded-md border p-4">
                                                        {directPhoneNumbers.length === 0 ? (
                                                            <p className="text-sm text-muted-foreground">No phone numbers available in this organization.</p>
                                                        ) : (
                                                            directPhoneNumbers.map((phoneNumber: Did) => (
                                                                <label key={phoneNumber.id} className="flex items-start gap-3 text-sm">
                                                                    <Checkbox
                                                                        checked={field.value.includes(phoneNumber.id)}
                                                                        onCheckedChange={(checked) => {
                                                                            field.onChange(
                                                                                checked
                                                                                    ? [...field.value, phoneNumber.id]
                                                                                    : field.value.filter((id) => id !== phoneNumber.id),
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
                                            name="default_outbound_did_id"
                                            render={({ field }) => {
                                                const availableDirectNumbers = directPhoneNumbers.filter((phoneNumber: Did) =>
                                                    form.getValues('direct_phone_number_ids').includes(phoneNumber.id),
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
                                                                {availableDirectNumbers.map((phoneNumber: Did) => (
                                                                    <SelectItem key={phoneNumber.id} value={phoneNumber.id}>
                                                                        {phoneNumber.number}
                                                                        {phoneNumber.description ? ` — ${phoneNumber.description}` : ''}
                                                                    </SelectItem>
                                                                ))}
                                                            </SelectContent>
                                                        </Select>
                                                        <FormDescription>
                                                            Default outbound number must come from direct user grants.
                                                        </FormDescription>
                                                        <FormMessage />
                                                    </FormItem>
                                                );
                                            }}
                                        />
                                    </>
                                )}

                                <div className="flex justify-end">
                                    <Button type="submit" disabled={mutation.isPending}>
                                        <Save className="mr-2 size-4" />
                                        {mutation.isPending ? 'Saving...' : isEdit ? 'Save User' : 'Create User'}
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
