import { useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Save } from 'lucide-react';

import api from '@/lib/api';
import { useOrganization } from '@/context/OrganizationContext';
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
    const { activeOrganization, organizationApiPrefix } = useOrganization();
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
            const res = await api.get(`${organizationApiPrefix}/dids/${id}`);
            return res.data.data;
        },
        enabled: isEdit && !!activeOrganization,
    });

    const { data: extensions = [] } = useQuery<Extension[]>({
        queryKey: ['extensions', activeOrganization?.id],
        queryFn: async () => {
            const res = await api.get<{ data: Extension[] }>(`${organizationApiPrefix}/extensions`);
            return res.data.data;
        },
        enabled: !!activeOrganization,
    });

    const { data: flows = [] } = useQuery<Flow[]>({
        queryKey: ['flows', activeOrganization?.id],
        queryFn: async () => {
            const res = await api.get<{ data: Flow[] }>(`${organizationApiPrefix}/flows`);
            return res.data.data;
        },
        enabled: !!activeOrganization,
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

    const selectedDestinationOption = selectedDestinationId
        ? destinationOptions.find((option) => option.id === selectedDestinationId)
        : undefined;

    const destinationOptionsWithCurrent = !selectedDestinationOption && selectedDestinationId
        ? [
            ...destinationOptions,
            {
                id: selectedDestinationId,
                label: destType === 'flow'
                    ? 'Current flow (not in published list)'
                    : 'Current destination',
            },
        ]
        : destinationOptions;

    useEffect(() => {
        if (did) {
            const destinationType = did.destination_type || '';
            const destinationId = did.destination_id || '';

            form.reset({
                number: did.number || '',
                description: did.description || '',
                destination_type: destinationType,
                destination_id: destinationId,
                is_active: did.is_active ?? true,
            });

            form.setValue('destination_type', destinationType, { shouldDirty: false });
            form.setValue('destination_id', destinationId, { shouldDirty: false });
        }
    }, [did, form]);

    useEffect(() => {
        if (!did) return;

        const destinationType = did.destination_type || '';
        const destinationId = did.destination_id || '';

        if (!destinationType) return;
        if (destType === destinationType && form.getValues('destination_id') === destinationId) return;

        form.setValue('destination_type', destinationType, { shouldDirty: false });
        form.setValue('destination_id', destinationId, { shouldDirty: false });
    }, [did, destType, form]);

    useEffect(() => {
        if (!selectedDestinationId) return;
        if (destinationOptionsWithCurrent.some((option) => option.id === selectedDestinationId)) return;
        form.setValue('destination_id', '');
    }, [destinationOptionsWithCurrent, selectedDestinationId, form]);

    const mutation = useMutation({
        mutationFn: async (values: DidFormValues) => {
            if (isEdit) {
                return api.put(`${organizationApiPrefix}/dids/${id}`, values);
            }
            return api.post(`${organizationApiPrefix}/dids`, values);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['dids'] });
            navigate('/admin/numbers');
        },
    });

    const onSubmit = (values: DidFormValues) => {
        mutation.mutate(values);
    };

    if (!activeOrganization) return null;

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div className="flex items-center gap-4">
                <Button variant="ghost" size="icon" onClick={() => navigate('/admin/numbers')}>
                    <ArrowLeft className="size-4" />
                </Button>
                <div>
                    <p className="text-sm text-muted-foreground">
                        {activeOrganization.name} &rsaquo; Routing
                    </p>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {isEdit ? 'Edit Number' : 'Add Number'}
                    </h1>
                </div>
            </div>

            <Card className="max-w-2xl">
                <CardHeader>
                    <CardTitle>Number Details</CardTitle>
                    <CardDescription>
                        Configure an inbound phone number and where calls should route.
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
                                                Phone number customers dial to reach this route.
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
                                                            {destinationOptionsWithCurrent.map((option) => (
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
                                        {mutation.isPending ? 'Saving...' : 'Save Number'}
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
