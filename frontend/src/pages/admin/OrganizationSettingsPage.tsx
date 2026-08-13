import { zodResolver } from '@hookform/resolvers/zod';
import { useQuery } from '@tanstack/react-query';
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
import {
    Form,
    FormControl,
    FormField,
    FormItem,
    FormLabel,
    FormMessage,
} from '@/components/ui/form';
import { Textarea } from '@/components/ui/textarea';
import api from '@/lib/api';
import { useApiMutation } from '@/lib/api-hooks';

const organizationSettingsSchema = z.object({
    settingsText: z.string().superRefine((value: string, ctx: z.RefinementCtx) => {
        try {
            const parsed = JSON.parse(value || '{}');
            if (parsed === null || Array.isArray(parsed) || typeof parsed !== 'object') {
                ctx.addIssue({
                    code: z.ZodIssueCode.custom,
                    message: 'Settings must be a JSON object.',
                });
            }
        } catch {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                message: 'Enter valid JSON.',
            });
        }
    }),
});

type OrganizationSettingsValues = z.infer<typeof organizationSettingsSchema>;

export default function OrganizationSettingsPage() {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();

    const form = useForm<OrganizationSettingsValues>({
        resolver: zodResolver(organizationSettingsSchema),
        defaultValues: {
            settingsText: '{\n  \n}',
        },
    });

    const { data: organization } = useQuery({
        queryKey: ['organization', id],
        queryFn: async () => {
            const response = await api.get(`organizations/${id}`);
            return response.data.data;
        },
        enabled: Boolean(id),
    });

    const { data: settings, isLoading } = useQuery({
        queryKey: ['organization-settings', id],
        queryFn: async () => {
            const response = await api.get(`organizations/${id}/settings`);
            return response.data.data;
        },
        enabled: Boolean(id),
    });

    useEffect(() => {
        if (settings) {
            form.reset({
                settingsText: JSON.stringify(settings, null, 2),
            });
        }
    }, [settings, form]);

    const mutation = useApiMutation({
        mutationFn: async (values: OrganizationSettingsValues) => {
            return api.put(`organizations/${id}/settings`, {
                settings: JSON.parse(values.settingsText),
            });
        },
        successMessage: 'Organization settings saved successfully',
        invalidateQueries: [
            ['organization-settings', id || ''],
            ['organization', id || ''],
            ['organizations'],
        ],
    });

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div className="flex items-center gap-4">
                <Button variant="ghost" size="icon" onClick={() => navigate('/admin/organizations')}>
                    <ArrowLeft className="size-4" />
                    <span className="sr-only">Back to organizations</span>
                </Button>
                <div>
                    <p className="text-sm text-muted-foreground">Platform administration</p>
                    <h1 className="text-2xl font-bold tracking-tight">Organization Settings</h1>
                </div>
            </div>

            <Card className="max-w-4xl">
                <CardHeader>
                    <CardTitle>{organization?.name ?? 'Organization'} settings</CardTitle>
                    <CardDescription>
                        Edit the organization settings payload as JSON. Changes are merged on save.
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
                                onSubmit={form.handleSubmit((values: OrganizationSettingsValues) => mutation.mutate(values))}
                                className="space-y-6"
                            >
                                <FormField
                                    control={form.control}
                                    name="settingsText"
                                    render={({ field }: { field: { value: string; onChange: (value: string) => void; onBlur: () => void; name: string; ref: React.Ref<HTMLTextAreaElement> } }) => (
                                        <FormItem>
                                            <FormLabel>Settings JSON</FormLabel>
                                            <FormControl>
                                                <Textarea
                                                    className="min-h-96 font-mono text-xs"
                                                    spellCheck={false}
                                                    {...field}
                                                />
                                            </FormControl>
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
