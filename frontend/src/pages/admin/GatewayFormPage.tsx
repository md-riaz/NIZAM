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
import { useTenant } from '@/context/TenantContext';
import api from '@/lib/api';

const gatewaySchema = z.object({
    tenant_id: z.string().nullable().optional(),
    name: z.string().min(1, 'Name is required'),
    host: z.string().min(1, 'SIP server is required'),
    username: z.string().optional(),
    password: z.string().optional(),
    register: z.boolean(),
    is_active: z.boolean(),
});

type GatewayFormValues = z.infer<typeof gatewaySchema>;

export default function GatewayFormPage() {
    const { id } = useParams<{ id: string }>();
    const isEdit = Boolean(id);
    const navigate = useNavigate();
    const { activeTenant, tenantApiPrefix, tenants } = useTenant();
    const queryClient = useQueryClient();

    const form = useForm<GatewayFormValues>({
        resolver: zodResolver(gatewaySchema),
        defaultValues: {
            tenant_id: 'global',
            name: '',
            host: '',
            username: '',
            password: '',
            register: true,
            is_active: true,
        },
    });

    const { data: gateway, isLoading: isFetching } = useQuery({
        queryKey: ['gateway', id, activeTenant?.id || 'global'],
        queryFn: async () => {
            const url = activeTenant ? `${tenantApiPrefix}/gateways/${id}` : `admin/gateways/${id}`;
            const res = await api.get(url);
            return res.data.data;
        },
        enabled: isEdit,
    });

    useEffect(() => {
        if (gateway) {
            form.reset({
                tenant_id: gateway.tenant_id || 'global',
                name: gateway.name || '',
                host: gateway.host || '',
                username: gateway.username || '',
                password: gateway.password || '',
                register: gateway.register ?? true,
                is_active: gateway.is_active ?? true,
            });
        }
    }, [gateway, form]);

    const mutation = useMutation({
        mutationFn: async (values: GatewayFormValues) => {
            const payload = { ...values, tenant_id: values.tenant_id === 'global' ? null : values.tenant_id };
            const baseUrl = activeTenant ? `${tenantApiPrefix}/gateways` : 'admin/gateways';
            
            if (isEdit) {
                return api.put(`${baseUrl}/${id}`, payload);
            }
            return api.post(baseUrl, payload);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['gateways'] });
            navigate('/admin/gateways');
        },
    });

    const onSubmit = (values: GatewayFormValues) => {
        mutation.mutate(values);
    };



    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div className="flex items-center gap-4">
                <Button variant="ghost" size="icon" onClick={() => navigate('/admin/gateways')}>
                    <ArrowLeft className="size-4" />
                </Button>
                <div>
                    <p className="text-sm text-muted-foreground">
                        {activeTenant ? activeTenant.name : 'Platform Admin'} &rsaquo; Connectivity
                    </p>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {isEdit ? 'Edit Gateway' : 'Add Gateway'}
                    </h1>
                </div>
            </div>

            <Card className="max-w-2xl">
                <CardHeader>
                    <CardTitle>Gateway Details</CardTitle>
                    <CardDescription>
                        Configure SIP trunk credentials and routing settings.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {isFetching ? (
                        <div className="flex h-32 items-center justify-center">
                            <div className="size-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                        </div>
                    ) : (
                        <Form {...form}>
                            <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-6">
                                {!activeTenant && (
                                    <FormField
                                        control={form.control}
                                        name="tenant_id"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Tenant / Owner</FormLabel>
                                                <Select onValueChange={field.onChange} value={field.value || 'global'}>
                                                    <FormControl>
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Select an owner" />
                                                        </SelectTrigger>
                                                    </FormControl>
                                                    <SelectContent>
                                                        <SelectItem value="global">Platform (Global)</SelectItem>
                                                        {tenants.map(t => (
                                                            <SelectItem key={t.id} value={t.id}>{t.name}</SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <FormDescription>
                                                    Platform gateways can be used by all tenants contextually or serve as global outbounds.
                                                </FormDescription>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                )}

                                <FormField
                                    control={form.control}
                                    name="name"
                                    render={({ field }) => (
                                        <FormItem>
                                            <FormLabel>Name</FormLabel>
                                            <FormControl>
                                                <Input placeholder="e.g. Twilio Trunk" {...field} />
                                            </FormControl>
                                            <FormDescription>
                                                A friendly name for this gateway.
                                            </FormDescription>
                                            <FormMessage />
                                        </FormItem>
                                    )}
                                />

                                <FormField
                                    control={form.control}
                                    name="host"
                                    render={({ field }) => (
                                        <FormItem>
                                            <FormLabel>SIP Server</FormLabel>
                                            <FormControl>
                                                <Input placeholder="e.g. sip.twilio.com" {...field} />
                                            </FormControl>
                                            <FormDescription>
                                                The primary SIP server hostname or IP address.
                                            </FormDescription>
                                            <FormMessage />
                                        </FormItem>
                                    )}
                                />

                                <div className="grid gap-6 sm:grid-cols-2">
                                    <FormField
                                        control={form.control}
                                        name="username"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Username</FormLabel>
                                                <FormControl>
                                                    <Input placeholder="Optional" {...field} />
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
                                                <FormLabel>Password</FormLabel>
                                                <FormControl>
                                                    <Input type="password" placeholder="Optional" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                </div>

                                <FormField
                                    control={form.control}
                                    name="register"
                                    render={({ field }) => (
                                        <FormItem className="flex flex-row items-start space-x-3 space-y-0 rounded-md border p-4">
                                            <FormControl>
                                                <Checkbox
                                                    checked={field.value}
                                                    onCheckedChange={field.onChange}
                                                />
                                            </FormControl>
                                            <div className="space-y-1 leading-none">
                                                <FormLabel>
                                                    Register
                                                </FormLabel>
                                                <FormDescription>
                                                    Send SIP REGISTER requests to this gateway.
                                                </FormDescription>
                                            </div>
                                        </FormItem>
                                    )}
                                />

                                <div className="flex justify-end">
                                    <Button type="submit" disabled={mutation.isPending}>
                                        <Save className="mr-2 size-4" />
                                        {mutation.isPending ? 'Saving...' : 'Save Gateway'}
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
