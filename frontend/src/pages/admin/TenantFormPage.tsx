import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, CalendarDays, Save } from 'lucide-react';
import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate, useParams } from 'react-router-dom';
import { z } from 'zod';

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
    status: z.string().min(1, 'Status is required'),
    max_extensions: z.coerce.number().min(0),
    max_concurrent_calls: z.coerce.number().min(0),
    max_dids: z.coerce.number().min(0),
    max_ring_groups: z.coerce.number().min(0),
    is_active: z.boolean(),
});

const normalizeTenantStatus = (status: string | null | undefined): string => {
    if (!status) return 'active';

    return tenantStatuses.includes(status as (typeof tenantStatuses)[number])
        ? status
        : 'active';
};

const serializeTenantPayload = (values: TenantFormValues) => ({
    name: values.name,
    domain: values.domain,
    status: values.status,
    max_extensions: values.max_extensions,
    max_concurrent_calls: values.max_concurrent_calls,
    max_dids: values.max_dids,
    max_ring_groups: values.max_ring_groups,
    is_active: values.is_active,
});

const getTenantStatusOptions = (currentStatus?: string | null): string[] => {
    const options = [...tenantStatuses];

    if (currentStatus && !options.includes(currentStatus as (typeof tenantStatuses)[number])) {
        return [currentStatus, ...options];
    }

    return options;
};

const formatStatusLabel = (status: string) =>
    status
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());

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
                status: normalizeTenantStatus(tenant.status),
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
            const payload = serializeTenantPayload(values);

            if (isEdit) {
                return api.put(`tenants/${id}`, payload);
            }

            return api.post('tenants', payload);
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
                    {isEdit && tenant ? (
                        <div className="mb-6 rounded-lg border border-border/70 bg-muted/30 p-4">
                            <div className="flex items-start gap-3">
                                <div className="rounded-md bg-background p-2 text-primary shadow-sm">
                                    <CalendarDays className="size-4" />
                                </div>
                                <div className="space-y-3">
                                    <div>
                                        <p className="text-sm font-medium">Business phone defaults</p>
                                        <p className="text-sm text-muted-foreground">
                                            Default schedule and holiday calendar are provisioned by backend business-phone setup.
                                        </p>
                                    </div>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="space-y-1">
                                            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                                Default schedule
                                            </p>
                                            <Badge variant="outline" className="font-mono text-xs">
                                                {tenant.default_schedule_id ?? 'Not provisioned'}
                                            </Badge>
                                        </div>
                                        <div className="space-y-1">
                                            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                                Default holiday calendar
                                            </p>
                                            <Badge variant="outline" className="font-mono text-xs">
                                                {tenant.default_holiday_calendar_id ?? 'Not provisioned'}
                                            </Badge>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    ) : null}
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
                                                        {getTenantStatusOptions(tenant?.status).map((status) => (
                                                            <SelectItem key={status} value={status}>
                                                                {formatStatusLabel(status)}
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
