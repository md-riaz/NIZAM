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
import { Textarea } from '@/components/ui/textarea';
import { useTenant } from '@/context/TenantContext';
import api from '@/lib/api';

const ringGroupSchema = z.object({
    name: z.string().min(1, 'Name is required'),
    strategy: z.enum(['simultaneous', 'sequence', 'enterprise']),
    ring_timeout: z.coerce.number().min(1, 'Ring timeout must be at least 1 second'),
    fallback_destination_type: z.string().optional(),
    fallback_destination_id: z.string().optional(),
    membersText: z.string(),
    is_active: z.boolean(),
});

type RingGroupFormValues = z.infer<typeof ringGroupSchema>;

function serializeMembers(members: Array<{ extension?: string; timeout?: number; delay?: number }>) {
    return members
        .map((member) => [member.extension ?? '', member.timeout ?? '', member.delay ?? ''].join(','))
        .join('\n');
}

function parseMembers(text: string) {
    return text
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean)
        .map((line) => {
            const [extension, timeout, delay] = line.split(',').map((part) => part.trim());
            return {
                extension,
                ...(timeout ? { timeout: Number(timeout) } : {}),
                ...(delay ? { delay: Number(delay) } : {}),
            };
        });
}

export default function RingGroupFormPage() {
    const { id } = useParams<{ id: string }>();
    const isEdit = Boolean(id);
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { activeTenant, tenantApiPrefix } = useTenant();

    const form = useForm<RingGroupFormValues>({
        resolver: zodResolver(ringGroupSchema),
        defaultValues: {
            name: '',
            strategy: 'simultaneous',
            ring_timeout: 30,
            fallback_destination_type: '',
            fallback_destination_id: '',
            membersText: '',
            is_active: true,
        },
    });

    const { data: group, isLoading: isFetching } = useQuery({
        queryKey: ['ring-group', activeTenant?.id, id],
        queryFn: async () => {
            const response = await api.get(`${tenantApiPrefix}/ring-groups/${id}`);
            return response.data.data;
        },
        enabled: Boolean(id) && Boolean(activeTenant),
    });

    useEffect(() => {
        if (group) {
            form.reset({
                name: group.name ?? '',
                strategy: group.strategy ?? 'simultaneous',
                ring_timeout: group.ring_timeout ?? 30,
                fallback_destination_type: group.fallback_destination_type ?? '',
                fallback_destination_id: group.fallback_destination_id ?? '',
                membersText: serializeMembers(group.members ?? []),
                is_active: group.is_active ?? true,
            });
        }
    }, [group, form]);

    const mutation = useMutation({
        mutationFn: async (values: RingGroupFormValues) => {
            const payload = {
                name: values.name,
                strategy: values.strategy,
                ring_timeout: values.ring_timeout,
                fallback_destination_type: values.fallback_destination_type || null,
                fallback_destination_id: values.fallback_destination_id || null,
                members: parseMembers(values.membersText),
                is_active: values.is_active,
            };

            if (isEdit) {
                return api.put(`${tenantApiPrefix}/ring-groups/${id}`, payload);
            }

            return api.post(`${tenantApiPrefix}/ring-groups`, payload);
        },
        onSuccess: async () => {
            await queryClient.invalidateQueries({ queryKey: ['ring-groups', activeTenant?.id] });
            navigate('/admin/ring-groups');
        },
    });

    if (!activeTenant) {
        return (
            <div className="flex h-64 items-center justify-center text-muted-foreground">
                Select a tenant to manage ring groups.
            </div>
        );
    }

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div className="flex items-center gap-4">
                <Button variant="ghost" size="icon" onClick={() => navigate('/admin/ring-groups')}>
                    <ArrowLeft className="size-4" />
                    <span className="sr-only">Back to ring groups</span>
                </Button>
                <div>
                    <p className="text-sm text-muted-foreground">{activeTenant.name} › Routing</p>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {isEdit ? 'Edit Ring Group' : 'Create Ring Group'}
                    </h1>
                </div>
            </div>

            <Card className="max-w-4xl">
                <CardHeader>
                    <CardTitle>Ring group configuration</CardTitle>
                    <CardDescription>
                        Define the routing strategy, member order, and fallback behavior.
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
                                onSubmit={form.handleSubmit((values: RingGroupFormValues) => mutation.mutate(values))}
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
                                                    <Input placeholder="Support Hunt Group" {...field} />
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
                                                <FormLabel>Strategy</FormLabel>
                                                <Select onValueChange={field.onChange} value={field.value}>
                                                    <FormControl>
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Select strategy" />
                                                        </SelectTrigger>
                                                    </FormControl>
                                                    <SelectContent>
                                                        <SelectItem value="simultaneous">Simultaneous</SelectItem>
                                                        <SelectItem value="sequence">Sequence</SelectItem>
                                                        <SelectItem value="enterprise">Enterprise</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                    <FormField
                                        control={form.control}
                                        name="ring_timeout"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Ring timeout (seconds)</FormLabel>
                                                <FormControl>
                                                    <Input type="number" min="1" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                    <FormField
                                        control={form.control}
                                        name="fallback_destination_type"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Fallback destination type</FormLabel>
                                                <FormControl>
                                                    <Input placeholder="extension, ivr, voicemail..." {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                    <FormField
                                        control={form.control}
                                        name="fallback_destination_id"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Fallback destination ID</FormLabel>
                                                <FormControl>
                                                    <Input placeholder="Destination identifier" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                </div>

                                <FormField
                                    control={form.control}
                                    name="membersText"
                                    render={({ field }) => (
                                        <FormItem>
                                            <FormLabel>Members</FormLabel>
                                            <FormControl>
                                                <Textarea
                                                    className="min-h-48 font-mono text-xs"
                                                    placeholder="1001,20,0&#10;1002,20,5"
                                                    {...field}
                                                />
                                            </FormControl>
                                            <FormDescription>
                                                One member per line using: extension,timeout,delay. Timeout and delay are optional.
                                            </FormDescription>
                                            <FormMessage />
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
                                                <FormLabel>Ring group active</FormLabel>
                                                <FormDescription>
                                                    Inactive ring groups remain configured but are not intended for active use.
                                                </FormDescription>
                                            </div>
                                        </FormItem>
                                    )}
                                />

                                <div className="flex justify-end">
                                    <Button type="submit" disabled={mutation.isPending}>
                                        <Save className="mr-2 size-4" />
                                        {mutation.isPending ? 'Saving...' : isEdit ? 'Save Ring Group' : 'Create Ring Group'}
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
