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
import api from '@/lib/api';

const tenantStatuses = ['trial', 'active', 'suspended', 'terminated'] as const;

const tenantSchema = z.object({
    name: z.string().min(1, 'Name is required'),
    domain: z.string().min(1, 'Domain is required'),
    slug: z.string().min(1, 'Slug is required'),
    status: z.enum(tenantStatuses),
    max_extensions: z.coerce.number().min(0),
    max_concurrent_calls: z.coerce.number().min(0),
    max_dids: z.coerce.number().min(0),
    max_ring_groups: z.coerce.number().min(0),
    is_active: z.boolean(),
});

type TenantFormValues = z.infer<typeof tenantSchema>;

export default function TenantFormPage() {
    const { id } = useParams<{ id: string }>();
    const isEdit = Boolean(id);
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    const form = useForm<TenantFormValues>({
        resolver: zodResolver(tenantSchema),
        defaultValues: {
            name: '',
            domain: '',
            slug: '',
            status: 'active',
            max_extensions: 0,
            max_concurrent_calls: 0,
            max_dids: 0,
            max_ring_groups: 0,
            is_active: true,
        },
    });

    const { data: tenant, isLoading: isFetching } = useQuery({
        queryKey: ['tenant', id],
        queryFn: async () => {
            const response = await api.get(`tenants/${id}`);
            return response.data.data;
        },
        enabled: isEdit,
    });

    useEffect(() => {
        if (tenant) {
            form.reset({
                name: tenant.name ?? '',
                domain: tenant.domain ?? '',
                slug: tenant.slug ?? '',
                status: tenant.status ?? 'active',
                max_extensions: tenant.max_extensions ?? 0,
                max_concurrent_calls: tenant.max_concurrent_calls ?? 0,
                max_dids: tenant.max_dids ?? 0,
                max_ring_groups: tenant.max_ring_groups ?? 0,
                is_active: tenant.is_active ?? true,
            });
        }
    }, [tenant, form]);

    const mutation = useMutation({
        mutationFn: async (values: TenantFormValues) => {
            if (isEdit) {
                return api.put(`tenants/${id}`, values);
            }

            return api.post('tenants', values);
        },
        onSuccess: async () => {
            await queryClient.invalidateQueries({ queryKey: ['tenants'] });
            navigate('/admin/tenants');
        },
    });

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div className="flex items-center gap-4">
                <Button variant="ghost" size="icon" onClick={() => navigate('/admin/tenants')}>
                    <ArrowLeft className="size-4" />
                    <span className="sr-only">Back to tenants</span>
                </Button>
                <div>
                    <p className="text-sm text-muted-foreground">Platform administration</p>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {isEdit ? 'Edit Tenant' : 'Create Tenant'}
                    </h1>
                </div>
            </div>

            <Card className="max-w-4xl">
                <CardHeader>
                    <CardTitle>Tenant profile</CardTitle>
                    <CardDescription>
                        Manage tenant identity, status, and platform resource limits.
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
                                onSubmit={form.handleSubmit((values: TenantFormValues) => mutation.mutate(values))}
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
                                                    <Input placeholder="Acme Telecom" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <FormField
                                        control={form.control}
                                        name="domain"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Domain</FormLabel>
                                                <FormControl>
                                                    <Input placeholder="acme.example.com" {...field} />
                                                </FormControl>
                                                <FormDescription>
                                                    Used for tenant-facing SIP and WebRTC identity.
                                                </FormDescription>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <FormField
                                        control={form.control}
                                        name="slug"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Slug</FormLabel>
                                                <FormControl>
                                                    <Input placeholder="acme" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <FormField
                                        control={form.control}
                                        name="status"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Status</FormLabel>
                                                <Select onValueChange={field.onChange} value={field.value}>
                                                    <FormControl>
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Select status" />
                                                        </SelectTrigger>
                                                    </FormControl>
                                                    <SelectContent>
                                                        {tenantStatuses.map((status) => (
                                                            <SelectItem key={status} value={status}>
                                                                {status}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                </div>

                                <div className="grid gap-6 md:grid-cols-2">
                                    <FormField
                                        control={form.control}
                                        name="max_extensions"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Max extensions</FormLabel>
                                                <FormControl>
                                                    <Input type="number" min="0" {...field} />
                                                </FormControl>
                                                <FormDescription>Use 0 for no enforced quota.</FormDescription>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <FormField
                                        control={form.control}
                                        name="max_concurrent_calls"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Max concurrent calls</FormLabel>
                                                <FormControl>
                                                    <Input type="number" min="0" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <FormField
                                        control={form.control}
                                        name="max_dids"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Max DIDs</FormLabel>
                                                <FormControl>
                                                    <Input type="number" min="0" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <FormField
                                        control={form.control}
                                        name="max_ring_groups"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Max ring groups</FormLabel>
                                                <FormControl>
                                                    <Input type="number" min="0" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                </div>

                                <FormField
                                    control={form.control}
                                    name="is_active"
                                    render={({ field }) => (
                                        <FormItem className="flex flex-row items-start space-x-3 space-y-0 rounded-md border p-4">
                                            <FormControl>
                                                <Checkbox checked={field.value} onCheckedChange={field.onChange} />
                                            </FormControl>
                                            <div className="space-y-1 leading-none">
                                                <FormLabel>Tenant active</FormLabel>
                                                <FormDescription>
                                                    Inactive tenants remain in the system but should not be treated as operational.
                                                </FormDescription>
                                            </div>
                                        </FormItem>
                                    )}
                                />

                                <div className="flex justify-end">
                                    <Button type="submit" disabled={mutation.isPending}>
                                        <Save className="mr-2 size-4" />
                                        {mutation.isPending ? 'Saving...' : isEdit ? 'Save Tenant' : 'Create Tenant'}
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
