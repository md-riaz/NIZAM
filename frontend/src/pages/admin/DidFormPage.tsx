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

    const { data: did, isLoading: isFetching } = useQuery({
        queryKey: ['did', id],
        queryFn: async () => {
            const res = await api.get(`${tenantApiPrefix}/dids/${id}`);
            return res.data.data;
        },
        enabled: isEdit && !!activeTenant,
    });

    // Fetch extensions for destination dropdown (as an example)
    const { data: extensions = [] } = useQuery({
        queryKey: ['extensions', activeTenant?.id],
        queryFn: async () => {
            const res = await api.get(`${tenantApiPrefix}/extensions`);
            return res.data.data;
        },
        enabled: !!activeTenant,
    });

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

    const destType = form.watch('destination_type');

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
                                                The inbound phone number.
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
                                                <Select onValueChange={field.onChange} defaultValue={field.value}>
                                                    <FormControl>
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Select type" />
                                                        </SelectTrigger>
                                                    </FormControl>
                                                    <SelectContent>
                                                        <SelectItem value="extension">Extension</SelectItem>
                                                        <SelectItem value="ivr">IVR</SelectItem>
                                                        <SelectItem value="ring_group">Ring Group</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    {destType === 'extension' && (
                                        <FormField
                                            control={form.control}
                                            name="destination_id"
                                            render={({ field }) => (
                                                <FormItem>
                                                    <FormLabel>Destination Extension</FormLabel>
                                                    <Select onValueChange={field.onChange} defaultValue={field.value}>
                                                        <FormControl>
                                                            <SelectTrigger>
                                                                <SelectValue placeholder="Select extension" />
                                                            </SelectTrigger>
                                                        </FormControl>
                                                        <SelectContent>
                                                            {extensions.map((ext: any) => (
                                                                <SelectItem key={ext.id} value={ext.id}>
                                                                    {ext.extension} - {ext.name}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                    <FormMessage />
                                                </FormItem>
                                            )}
                                        />
                                    )}
                                    {/* Additional destination types can be added here */}
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
