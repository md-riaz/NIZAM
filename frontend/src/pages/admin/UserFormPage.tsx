import { zodResolver } from '@hookform/resolvers/zod';
import { useQuery } from '@tanstack/react-query';
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
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useAuth } from '@/context/AuthContext';
import api from '@/lib/api';
import { useApiMutation } from '@/lib/api-hooks';
import type { Organization } from '@/types/models';

const userSchema = z.object({
    name: z.string().min(1, 'Name is required'),
    email: z.string().email('A valid email is required'),
    password: z.string().optional(),
    role: z.enum(['superadmin', 'admin', 'agent']),
    organization_id: z.string().optional(),
});

type UserFormValues = z.infer<typeof userSchema>;

export default function UserFormPage() {
    const { id } = useParams<{ id: string }>();
    const isEdit = Boolean(id && id !== 'new');
    const navigate = useNavigate();
    const { user: authUser } = useAuth();

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
            organization_id: authUser?.role === 'superadmin' ? 'global' : (authUser?.organization_id ?? ''),
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
        enabled: authUser?.role === 'superadmin',
    });

    const isSuperadmin = authUser?.role === 'superadmin';
    const allowedRoles = isSuperadmin ? ['superadmin', 'admin', 'agent'] : ['admin', 'agent'];
    const scopedOrganizationId = authUser?.organization_id ?? '';

    useEffect(() => {
        if (user) {
            const nextRole = isSuperadmin
                ? (user.role === 'superadmin' || user.role === 'admin' ? user.role : 'agent')
                : (user.role === 'admin' ? 'admin' : 'agent');

            form.reset({
                name: user.name ?? '',
                email: user.email ?? '',
                password: '',
                role: nextRole,
                organization_id: isSuperadmin ? (user.organization_id ?? 'global') : scopedOrganizationId,
            });
            return;
        }

        if (!isSuperadmin && scopedOrganizationId) {
            form.setValue('organization_id', scopedOrganizationId);
            if (form.getValues('role') === 'superadmin') {
                form.setValue('role', 'admin');
            }
        }
    }, [form, isSuperadmin, scopedOrganizationId, user]);

    const mutation = useApiMutation({
        mutationFn: async (values: UserFormValues) => {
            const payload = {
                ...values,
                organization_id: values.organization_id === 'global' ? null : values.organization_id,
            };

            if (isEdit && !values.password) {
                delete (payload as { password?: string }).password;
            }

            if (isEdit) {
                return api.put(`users/${id}`, payload);
            }

            return api.post('users', payload);
        },
        invalidateQueries: [['users']],
        onSuccess: () => navigate('/admin/users'),
    });

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div className="flex items-center gap-4">
                <Button variant="ghost" size="icon" onClick={() => navigate('/admin/users')}>
                    <ArrowLeft className="size-4" />
                    <span className="sr-only">Back to users</span>
                </Button>
                <div>
                    <p className="text-sm text-muted-foreground">{isSuperadmin ? 'Platform administration' : 'Organization administration'}</p>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {isEdit ? 'Edit User' : 'Create User'}
                    </h1>
                </div>
            </div>

            <Card className="max-w-3xl">
                <CardHeader>
                    <CardTitle>User profile</CardTitle>
                    <CardDescription>
                        {isSuperadmin
                            ? 'Create platform or organization-scoped users and assign their role.'
                            : 'Create users for your organization and assign their role.'}
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
                                                        if (!allowedRoles.includes(value)) {
                                                            return;
                                                        }

                                                        field.onChange(value as UserFormValues['role']);
                                                    }}
                                                    value={field.value}
                                                >
                                                    <FormControl>
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Select role" />
                                                        </SelectTrigger>
                                                    </FormControl>
                                                    <SelectContent>
                                                        {allowedRoles.includes('superadmin') && (
                                                            <SelectItem value="superadmin">Superadmin</SelectItem>
                                                        )}
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
                                            {isSuperadmin ? (
                                                <>
                                                    <Select
                                                        onValueChange={(value) => {
                                                            if (value !== 'global' && !organizations.some((organization) => organization.id === value)) {
                                                                return;
                                                            }

                                                            field.onChange(value);
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
                                                </>
                                            ) : (
                                                <>
                                                    <FormControl>
                                                        <Input value={authUser?.organization?.name ?? 'Current organization'} disabled />
                                                    </FormControl>
                                                    <FormDescription>
                                                        Organization admins can create users only in their own organization.
                                                    </FormDescription>
                                                </>
                                            )}
                                            <FormMessage />
                                        </FormItem>
                                    )}
                                />


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
