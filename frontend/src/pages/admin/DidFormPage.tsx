import { useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Save } from 'lucide-react';

import api from '@/lib/api';
import { useTenant } from '@/context/TenantContext';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Form,
    FormControl,
    FormDescription,
    FormField,
    FormItem,
    FormLabel,
    FormMessage,
} from '@/components/ui/form';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { Extension, Flow } from '@/types/models';

const didSchema = z.object({
    number: z.string().min(1, 'Number is required'),
    description: z.string().optional(),
    destination_type: z.string().optional(),
    destination_id: z.string().optional(),
    is_active: z.boolean(),
});

type DidFormValues = z.infer<typeof didSchema>;

export default function DidFormPage() {
    const { id } = useParams<{ id: string }>();
    const isEdit = Boolean(id);
    const navigate = useNavigate();
    const { activeTenant, tenantApiPrefix } = useTenant();
    const queryClient = useQueryClient();

    const form = useForm<DidFormValues>({
        resolver: zodResolver(didSchema),
        defaultValues: {
            number: '',
            description: '',
            destination_type: '',
            destination_id: '',
            is_active: true,
        },
    });

    const destType = form.watch('destination_type');
    const selectedDestinationId = form.watch('destination_id');

    const { data: did, isLoading: isFetching } = useQuery({
        queryKey: ['did', id],
        queryFn: async () => {
            const res = await api.get(`${tenantApiPrefix}/dids/${id}`);
            return res.data.data;
        },
        enabled: isEdit && !!activeTenant,
    });

    const { data: extensions = [] } = useQuery<Extension[]>({
        queryKey: ['extensions', activeTenant?.id],
        queryFn: async () => {
            const res = await api.get<{ data: Extension[] }>(`${tenantApiPrefix}/extensions`);
            return res.data.data;
        },
        enabled: !!activeTenant,
    });

    const { data: flows = [] } = useQuery<Flow[]>({
        queryKey: ['flows', activeTenant?.id],
        queryFn: async () => {
            const res = await api.get<{ data: Flow[] }>(`${tenantApiPrefix}/flows`);
            return res.data.data;
        },
        enabled: !!activeTenant,
    });

    const destinationOptions = destType === 'flow'
        ? flows
            .filter((flow) => !!flow.active_version)
            .map((flow) => ({
                id: flow.id,
                label: `${flow.name}${flow.active_version?.is_published ? ' (published)' : ' (draft)'}`,
            }))
        : destType === 'extension'
            ? extensions.map((ext) => ({
                id: ext.id,
                label: `${ext.extension} - ${ext.directory_first_name ?? ext.directory_last_name ?? 'Extension'}`,
            }))
            : [];

    useEffect(() => {
        if (did) {
            form.reset({
                number: did.number || '',
                description: did.description || '',
                destination_type: did.destination_type || '',
                destination_id: did.destination_id || '',
                is_active: did.is_active ?? true,
            });
        }
    }, [did, form]);

    useEffect(() => {
        if (!selectedDestinationId) return;
        if (destinationOptions.some((option) => option.id === selectedDestinationId)) return;
        form.setValue('destination_id', '');
    }, [destinationOptions, selectedDestinationId, form]);

    const mutation = useMutation({
        mutationFn: async (values: DidFormValues) => {
            if (isEdit) {
                return api.put(`${tenantApiPrefix}/dids/${id}`, values);
            }
            return api.post(`${tenantApiPrefix}/dids`, values);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['dids'] });
            navigate('/admin/dids');
        },
    });

    const onSubmit = (values: DidFormValues) => {
        mutation.mutate(values);
    };

    if (!activeTenant) return null;

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div className="flex items-center gap-4">
                <Button variant="ghost" size="icon" onClick={() => navigate('/admin/dids')}>
                    <ArrowLeft className="size-4" />
                </Button>
                <div>
                    <p className="text-sm text-muted-foreground">
                        {activeTenant.name} &rsaquo; Routing
                    </p>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {isEdit ? 'Edit DID' : 'Add DID'}
                    </h1>
                </div>
            </div>

            <Card className="max-w-2xl">
                <CardHeader>
                    <CardTitle>DID Details</CardTitle>
                    <CardDescription>
                        Configure inbound number and its routing destination.
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
                                <FormField
                                    control={form.control}
                                    name="number"
                                    render={({ field }) => (
                                        <FormItem>
                                            <FormLabel>Number</FormLabel>
                                            <FormControl>
                                                <Input placeholder="e.g. 18005551234" {...field} />
                                            </FormControl>
                                            <FormDescription>
                                                Inbound phone number.
                                            </FormDescription>
                                            <FormMessage />
                                        </FormItem>
                                    )}
                                />

                                <FormField
                                    control={form.control}
                                    name="description"
                                    render={({ field }) => (
                                        <FormItem>
                                            <FormLabel>Description</FormLabel>
                                            <FormControl>
                                                <Input placeholder="e.g. Main Support Line" {...field} />
                                            </FormControl>
                                            <FormMessage />
                                        </FormItem>
                                    )}
                                />

                                <div className="grid gap-6 sm:grid-cols-2">
                                    <FormField
                                        control={form.control}
                                        name="destination_type"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Destination Type</FormLabel>
                                                <Select
                                                    onValueChange={(value) => {
                                                        field.onChange(value);
                                                        form.setValue('destination_id', '');
                                                    }}
                                                    value={field.value}
                                                >
                                                    <FormControl>
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Select type" />
                                                        </SelectTrigger>
                                                    </FormControl>
                                                    <SelectContent>
                                                        <SelectItem value="extension">Extension</SelectItem>
                                                        <SelectItem value="flow">Call Flow</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    {(destType === 'extension' || destType === 'flow') && (
                                        <FormField
                                            control={form.control}
                                            name="destination_id"
                                            render={({ field }) => (
                                                <FormItem>
                                                    <FormLabel>
                                                        {destType === 'flow' ? 'Destination Flow' : 'Destination Extension'}
                                                    </FormLabel>
                                                    <Select onValueChange={field.onChange} value={field.value}>
                                                        <FormControl>
                                                            <SelectTrigger>
                                                                <SelectValue placeholder={destType === 'flow' ? 'Select flow' : 'Select extension'} />
                                                            </SelectTrigger>
                                                        </FormControl>
                                                        <SelectContent>
                                                            {destinationOptions.map((option) => (
                                                                <SelectItem key={option.id} value={option.id}>
                                                                    {option.label}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                    <FormDescription>
                                                        {destType === 'flow'
                                                            ? 'Choose flow that should answer inbound call and execute published routing graph.'
                                                            : 'Choose extension that should receive inbound calls.'}
                                                    </FormDescription>
                                                    <FormMessage />
                                                </FormItem>
                                            )}
                                        />
                                    )}
                                </div>

                                <div className="flex justify-end">
                                    <Button type="submit" disabled={mutation.isPending}>
                                        <Save className="mr-2 size-4" />
                                        {mutation.isPending ? 'Saving...' : 'Save DID'}
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
