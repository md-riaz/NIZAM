import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Save } from 'lucide-react';
import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate } from 'react-router-dom';
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
import api from '@/lib/api';

const systemSettingsSchema = z.object({
    organization_domain_suffix: z.string().min(1, 'Domain suffix is required'),
});

type SystemSettingsValues = z.infer<typeof systemSettingsSchema>;

export default function SystemSettingsPage() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    const form = useForm<SystemSettingsValues>({
        resolver: zodResolver(systemSettingsSchema),
        defaultValues: {
            organization_domain_suffix: '',
        },
    });

    const { data: settings, isLoading } = useQuery({
        queryKey: ['platform-settings'],
        queryFn: async () => {
            const response = await api.get('admin/platform-settings');
            return response.data.data as SystemSettingsValues;
        },
    });

    useEffect(() => {
        if (settings) {
            form.reset({
                organization_domain_suffix: settings.organization_domain_suffix ?? '',
            });
        }
    }, [settings, form]);

    const mutation = useMutation({
        mutationFn: async (values: SystemSettingsValues) => {
            return api.put('admin/platform-settings', values);
        },
        onSuccess: async () => {
            await Promise.all([
                queryClient.invalidateQueries({ queryKey: ['platform-settings'] }),
                queryClient.invalidateQueries({ queryKey: ['organization', 'create'] }),
                queryClient.invalidateQueries({ queryKey: ['organizations'] }),
            ]);
        },
    });

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div className="flex items-center gap-4">
                <Button variant="ghost" size="icon" onClick={() => navigate('/admin')}>
                    <ArrowLeft className="size-4" />
                    <span className="sr-only">Back to dashboard</span>
                </Button>
                <div>
                    <p className="text-sm text-muted-foreground">Platform administration</p>
                    <h1 className="text-2xl font-bold tracking-tight">System Settings</h1>
                </div>
            </div>

            <Card className="max-w-3xl">
                <CardHeader>
                    <CardTitle>Organization domain suffix</CardTitle>
                    <CardDescription>
                        Set shared readonly suffix appended to organization domain prefixes during create and edit flows.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {isLoading ? (
                        <div className="flex h-32 items-center justify-center">
                            <div className="size-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                        </div>
                    ) : (
                        <Form {...form}>
                            <form
                                onSubmit={form.handleSubmit((values: SystemSettingsValues) => mutation.mutate(values))}
                                className="space-y-6"
                            >
                                <FormField
                                    control={form.control}
                                    name="organization_domain_suffix"
                                    render={({ field }) => (
                                        <FormItem>
                                            <FormLabel>Domain suffix</FormLabel>
                                            <FormControl>
                                                <Input placeholder="example.com" {...field} />
                                            </FormControl>
                                            <FormDescription>
                                                Stored once for platform. Organization forms show this as readonly suffix.
                                            </FormDescription>
                                            <FormMessage />
                                        </FormItem>
                                    )}
                                />

                                <div className="flex justify-end">
                                    <Button type="submit" disabled={mutation.isPending}>
                                        <Save className="mr-2 size-4" />
                                        {mutation.isPending ? 'Saving...' : 'Save Settings'}
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
