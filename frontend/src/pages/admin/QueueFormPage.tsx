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

const queueStrategies = ['ring_all', 'round_robin', 'least_recent'] as const;
const overflowActions = ['voicemail', 'hangup', 'extension'] as const;

const queueSchema = z.object({
    name: z.string().min(1, 'Name is required').max(255),
    strategy: z.enum(queueStrategies),
    max_wait_time: z.coerce.number().min(10).max(3600),
    overflow_action: z.enum(overflowActions),
    overflow_destination: z.string().optional().nullable(),
    music_on_hold: z.string().optional().nullable(),
    service_level_threshold: z.coerce.number().min(1).max(300),
    is_active: z.boolean(),
});

type QueueFormValues = z.infer<typeof queueSchema>;

export default function QueueFormPage() {
    const { id } = useParams<{ id: string }>();
    const isEdit = Boolean(id);
    const navigate = useNavigate();
    const { activeOrganization } = useOrganization();

    const form = useForm<QueueFormValues>({
        resolver: zodResolver(queueSchema),
        defaultValues: {
            name: '',
            strategy: 'ring_all',
            max_wait_time: 120,
            overflow_action: 'hangup',
            overflow_destination: '',
            music_on_hold: '',
            service_level_threshold: 60,
            is_active: true,
        },
    });

    const { data: queue, isLoading: isFetching } = useQuery({
        queryKey: ['queue', activeOrganization?.id, id],
        queryFn: async () => {
            if (!activeOrganization) return null;
            const response = await api.get(`organizations/${activeOrganization.id}/queues/${id}`);
            return response.data.data;
        },
        enabled: isEdit && !!activeOrganization,
    });

    useEffect(() => {
        if (queue) {
            form.reset({
                name: queue.name ?? '',
                strategy: (queue.strategy as any) ?? 'ring_all',
                max_wait_time: queue.max_wait_time ?? 120,
                overflow_action: (queue.overflow_action as any) ?? 'hangup',
                overflow_destination: queue.overflow_destination ?? '',
                music_on_hold: queue.music_on_hold ?? '',
                service_level_threshold: queue.service_level_threshold ?? 60,
                is_active: queue.is_active ?? true,
            });
        }
    }, [queue, form]);

    const mutation = useApiMutation({
        mutationFn: async (values: QueueFormValues) => {
            if (!activeOrganization) throw new Error('No active organization');
            if (isEdit) {
                return api.put(`organizations/${activeOrganization.id}/queues/${id}`, values);
            }
            return api.post(`organizations/${activeOrganization.id}/queues`, values);
        },
        successMessage: `Queue ${isEdit ? 'updated' : 'created'} successfully`,
        invalidateQueries: [['queues', activeOrganization?.id || '']],
        onSuccess: () => navigate('/admin/queues'),
    });

    if (!activeOrganization) return null;

    const currentOverflowAction = form.watch('overflow_action');

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <PageHeader
                title={isEdit ? 'Edit Queue' : 'Create Queue'}
                breadcrumbs="Contact Center Configuration"
                actionLabel="Back to Queues"
                actionRoute="/admin/queues"
                actionIcon={null}
            />

            <Card className="max-w-4xl">
                <CardHeader>
                    <CardTitle>Queue Configuration</CardTitle>
                    <CardDescription>
                        Define how inbound calls queue up and wait for agents.
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
                                onSubmit={form.handleSubmit((v) => mutation.mutate(v))}
                                className="space-y-6"
                            >
                                <div className="grid gap-6 md:grid-cols-2">
                                    <FormField
                                        control={form.control}
                                        name="name"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Queue Name</FormLabel>
                                                <FormControl>
                                                    <Input placeholder="Support Queue" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <FormField
                                        control={form.control}
                                        name="strategy"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Dispatch Strategy</FormLabel>
                                                <Select onValueChange={field.onChange} value={field.value}>
                                                    <FormControl>
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Select strategy" />
                                                        </SelectTrigger>
                                                    </FormControl>
                                                    <SelectContent>
                                                        {queueStrategies.map((strategy) => (
                                                            <SelectItem key={strategy} value={strategy} className="capitalize">
                                                                {strategy.replace(/_/g, ' ')}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <FormField
                                        control={form.control}
                                        name="max_wait_time"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Max Wait Time (seconds)</FormLabel>
                                                <FormControl>
                                                    <Input type="number" min="10" max="3600" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <FormField
                                        control={form.control}
                                        name="service_level_threshold"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>SLA Threshold (seconds)</FormLabel>
                                                <FormControl>
                                                    <Input type="number" min="1" max="300" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <FormField
                                        control={form.control}
                                        name="overflow_action"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Overflow Action</FormLabel>
                                                <Select onValueChange={field.onChange} value={field.value}>
                                                    <FormControl>
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Select overflow" />
                                                        </SelectTrigger>
                                                    </FormControl>
                                                    <SelectContent>
                                                        {overflowActions.map((action) => (
                                                            <SelectItem key={action} value={action} className="capitalize">
                                                                {action}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <FormDescription>What to do if max wait time is reached.</FormDescription>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    {(currentOverflowAction === 'extension' || currentOverflowAction === 'voicemail') && (
                                        <FormField
                                            control={form.control}
                                            name="overflow_destination"
                                            render={({ field }) => (
                                                <FormItem>
                                                    <FormLabel>Overflow Destination</FormLabel>
                                                    <FormControl>
                                                        <Input placeholder="e.g. 101" {...field} value={field.value ?? ''} />
                                                    </FormControl>
                                                    <FormMessage />
                                                </FormItem>
                                            )}
                                        />
                                    )}

                                    <FormField
                                        control={form.control}
                                        name="music_on_hold"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Music on Hold Profile (Optional)</FormLabel>
                                                <FormControl>
                                                    <Input placeholder="local_stream://default" {...field} value={field.value ?? ''} />
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
                                                <FormLabel>Active Context</FormLabel>
                                                <FormDescription>
                                                    Inactive queues drop calls immediately.
                                                </FormDescription>
                                            </div>
                                        </FormItem>
                                    )}
                                />

                                <div className="flex justify-end">
                                    <Button type="submit" disabled={mutation.isPending}>
                                        <Save className="mr-2 size-4" />
                                        {mutation.isPending ? 'Saving...' : isEdit ? 'Save Changes' : 'Create Queue'}
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
