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
import { useTenant } from '@/context/TenantContext';
import api from '@/lib/api';
import { useApiMutation } from '@/lib/api-hooks';

const agentRoles = ['agent', 'supervisor'] as const;
const agentStates = ['available', 'busy', 'ringing', 'paused', 'offline'] as const;

const agentSchema = z.object({
    name: z.string().min(1, 'Name is required'),
    extension_id: z.string().uuid('Extension is required'),
    role: z.enum(agentRoles),
    state: z.enum(agentStates),
    is_active: z.boolean(),
});

type AgentFormValues = z.infer<typeof agentSchema>;

export default function AgentFormPage() {
    const { id } = useParams<{ id: string }>();
    const isEdit = Boolean(id);
    const navigate = useNavigate();
    const { activeTenant } = useTenant();

    const form = useForm<AgentFormValues>({
        resolver: zodResolver(agentSchema),
        defaultValues: {
            name: '',
            extension_id: '',
            role: 'agent',
            state: 'offline',
            is_active: true,
        },
    });

    const { data: agent, isLoading: isFetching } = useQuery({
        queryKey: ['agent', activeTenant?.id, id],
        queryFn: async () => {
            if (!activeTenant) return null;
            const response = await api.get(`tenants/${activeTenant.id}/agents/${id}`);
            return response.data.data;
        },
        enabled: isEdit && !!activeTenant,
    });

    const { data: extensions = [], isLoading: isLoadingExtensions } = useQuery({
        queryKey: ['extensions', activeTenant?.id],
        queryFn: async () => {
            if (!activeTenant) return [];
            const response = await api.get(`tenants/${activeTenant.id}/extensions`);
            return response.data.data;
        },
        enabled: !!activeTenant,
    });

    useEffect(() => {
        if (agent) {
            form.reset({
                name: agent.name ?? '',
                extension_id: agent.extension_id ?? '',
                role: (agent.role as any) ?? 'agent',
                state: (agent.state as any) ?? 'offline',
                is_active: agent.is_active ?? true,
            });
        }
    }, [agent, form]);

    const mutation = useApiMutation({
        mutationFn: async (values: AgentFormValues) => {
            if (!activeTenant) throw new Error('No active tenant');
            if (isEdit) {
                return api.put(`tenants/${activeTenant.id}/agents/${id}`, values);
            }
            return api.post(`tenants/${activeTenant.id}/agents`, values);
        },
        successMessage: `Agent ${isEdit ? 'updated' : 'created'} successfully`,
        invalidateQueries: [['agents', activeTenant?.id || '']],
        onSuccess: () => navigate('/admin/agents'),
    });

    if (!activeTenant) return null;

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <PageHeader
                title={isEdit ? 'Edit Agent' : 'Create Agent'}
                breadcrumbs="Contact Center Configuration"
                actionLabel="Back to Agents"
                actionRoute="/admin/agents"
                actionIcon={null}
            />

            <Card className="max-w-4xl">
                <CardHeader>
                    <CardTitle>Agent Profile</CardTitle>
                    <CardDescription>
                        Bind an extension to an agent identity.
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
                                                <FormLabel>Agent Name</FormLabel>
                                                <FormControl>
                                                    <Input placeholder="John Doe" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <FormField
                                        control={form.control}
                                        name="extension_id"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Linked Extension</FormLabel>
                                                <Select onValueChange={field.onChange} value={field.value}>
                                                    <FormControl>
                                                        <SelectTrigger disabled={isLoadingExtensions}>
                                                            <SelectValue placeholder="Select an extension" />
                                                        </SelectTrigger>
                                                    </FormControl>
                                                    <SelectContent>
                                                        {extensions.map((ext: any) => (
                                                            <SelectItem key={ext.id} value={ext.id}>
                                                                {ext.extension} - {ext.name ?? 'Unnamed'}
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
                                        name="role"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Role</FormLabel>
                                                <Select onValueChange={field.onChange} value={field.value}>
                                                    <FormControl>
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Select role" />
                                                        </SelectTrigger>
                                                    </FormControl>
                                                    <SelectContent>
                                                        {agentRoles.map((role) => (
                                                            <SelectItem key={role} value={role} className="capitalize">
                                                                {role}
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
                                        name="state"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Initial State</FormLabel>
                                                <Select onValueChange={field.onChange} value={field.value}>
                                                    <FormControl>
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Select state" />
                                                        </SelectTrigger>
                                                    </FormControl>
                                                    <SelectContent>
                                                        {agentStates.map((state) => (
                                                            <SelectItem key={state} value={state} className="capitalize">
                                                                {state.replace('_', ' ')}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
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
                                                    Inactive agents cannot be assigned calls.
                                                </FormDescription>
                                            </div>
                                        </FormItem>
                                    )}
                                />

                                <div className="flex justify-end">
                                    <Button type="submit" disabled={mutation.isPending}>
                                        <Save className="mr-2 size-4" />
                                        {mutation.isPending ? 'Saving...' : isEdit ? 'Save Changes' : 'Create Agent'}
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
