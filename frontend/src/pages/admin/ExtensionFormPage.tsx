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
import { useOrganization } from '@/context/OrganizationContext';
import api from '@/lib/api';

const extensionSchema = z.object({
    extension: z.string().min(1, 'Extension number is required'),
    password: z.string().min(8, 'Password must be at least 8 characters'),
    directory_first_name: z.string().optional(),
    directory_last_name: z.string().optional(),
    effective_caller_id_name: z.string().optional(),
    effective_caller_id_number: z.string().optional(),
    outbound_caller_id_name: z.string().optional(),
    outbound_caller_id_number: z.string().optional(),
    voicemail_enabled: z.boolean(),
    voicemail_pin: z.string().optional(),
    is_active: z.boolean(),
});

type ExtensionFormValues = z.infer<typeof extensionSchema>;

export default function ExtensionFormPage() {
    const { id } = useParams<{ id: string }>();
    const isEdit = Boolean(id);
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { activeOrganization, organizationApiPrefix } = useOrganization();

    const form = useForm<ExtensionFormValues>({
        resolver: zodResolver(extensionSchema),
        defaultValues: {
            extension: '',
            password: '',
            directory_first_name: '',
            directory_last_name: '',
            effective_caller_id_name: '',
            effective_caller_id_number: '',
            outbound_caller_id_name: '',
            outbound_caller_id_number: '',
            voicemail_enabled: true,
            voicemail_pin: '',
            is_active: true,
        },
    });

    const { data: extension, isLoading: isFetching } = useQuery({
        queryKey: ['extension', activeOrganization?.id, id],
        queryFn: async () => {
            const response = await api.get(`${organizationApiPrefix}/extensions/${id}`);
            return response.data.data;
        },
        enabled: Boolean(id) && Boolean(activeOrganization),
    });

    useEffect(() => {
        if (extension) {
            form.reset({
                extension: extension.extension ?? '',
                password: '',
                directory_first_name: extension.directory_first_name ?? '',
                directory_last_name: extension.directory_last_name ?? '',
                effective_caller_id_name: extension.effective_caller_id_name ?? '',
                effective_caller_id_number: extension.effective_caller_id_number ?? '',
                outbound_caller_id_name: extension.outbound_caller_id_name ?? '',
                outbound_caller_id_number: extension.outbound_caller_id_number ?? '',
                voicemail_enabled: extension.voicemail_enabled ?? true,
                voicemail_pin: extension.voicemail_pin ?? '',
                is_active: extension.is_active ?? true,
            });
        }
    }, [extension, form]);

    const mutation = useMutation({
        mutationFn: async (values: ExtensionFormValues) => {
            if (isEdit) {
                return api.put(`${organizationApiPrefix}/extensions/${id}`, values);
            }

            return api.post(`${organizationApiPrefix}/extensions`, values);
        },
        onSuccess: async () => {
            await queryClient.invalidateQueries({ queryKey: ['extensions', activeOrganization?.id] });
            navigate('/admin/extensions');
        },
    });

    if (!activeOrganization) {
        return (
            <div className="flex h-64 items-center justify-center text-muted-foreground">
                Select a organization to manage extensions.
            </div>
        );
    }

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div className="flex items-center gap-4">
                <Button variant="ghost" size="icon" onClick={() => navigate('/admin/extensions')}>
                    <ArrowLeft className="size-4" />
                    <span className="sr-only">Back to extensions</span>
                </Button>
                <div>
                    <p className="text-sm text-muted-foreground">{activeOrganization.name} › Phone System</p>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {isEdit ? 'Edit Extension' : 'Create Extension'}
                    </h1>
                </div>
            </div>

            <Card className="max-w-4xl">
                <CardHeader>
                    <CardTitle>Extension profile</CardTitle>
                    <CardDescription>
                        Configure SIP credentials, directory details, and caller ID information.
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
                                onSubmit={form.handleSubmit((values: ExtensionFormValues) => mutation.mutate(values))}
                                className="space-y-6"
                            >
                                <div className="grid gap-6 md:grid-cols-2">
                                    <FormField
                                        control={form.control}
                                        name="extension"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Extension</FormLabel>
                                                <FormControl>
                                                    <Input placeholder="1001" {...field} />
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
                                                <FormLabel>SIP password</FormLabel>
                                                <FormControl>
                                                    <Input type="password" autoComplete="new-password" {...field} />
                                                </FormControl>
                                                <FormDescription>
                                                    Required for phone registration and WebRTC clients.
                                                </FormDescription>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                    <FormField
                                        control={form.control}
                                        name="directory_first_name"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Directory first name</FormLabel>
                                                <FormControl>
                                                    <Input placeholder="Jane" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                    <FormField
                                        control={form.control}
                                        name="directory_last_name"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Directory last name</FormLabel>
                                                <FormControl>
                                                    <Input placeholder="Doe" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                    <FormField
                                        control={form.control}
                                        name="effective_caller_id_name"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Caller ID name</FormLabel>
                                                <FormControl>
                                                    <Input placeholder="Jane Doe" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                    <FormField
                                        control={form.control}
                                        name="effective_caller_id_number"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Caller ID number</FormLabel>
                                                <FormControl>
                                                    <Input placeholder="1001" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                    <FormField
                                        control={form.control}
                                        name="outbound_caller_id_name"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Outbound caller ID name</FormLabel>
                                                <FormControl>
                                                    <Input placeholder="Support" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                    <FormField
                                        control={form.control}
                                        name="outbound_caller_id_number"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Outbound caller ID number</FormLabel>
                                                <FormControl>
                                                    <Input placeholder="18005550123" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                </div>

                                <div className="grid gap-6 md:grid-cols-2">
                                    <FormField
                                        control={form.control}
                                        name="voicemail_pin"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Voicemail PIN</FormLabel>
                                                <FormControl>
                                                    <Input placeholder="1234" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <FormField
                                        control={form.control}
                                        name="voicemail_enabled"
                                        render={({ field }) => (
                                            <FormItem className="flex flex-row items-start space-x-3 space-y-0 rounded-md border p-4">
                                                <FormControl>
                                                    <Checkbox checked={field.value} onCheckedChange={field.onChange} />
                                                </FormControl>
                                                <div className="space-y-1 leading-none">
                                                    <FormLabel>Enable voicemail</FormLabel>
                                                    <FormDescription>Allow callers to leave messages for this extension.</FormDescription>
                                                </div>
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
                                                    <FormLabel>Extension active</FormLabel>
                                                    <FormDescription>Inactive extensions cannot register or receive calls.</FormDescription>
                                                </div>
                                            </FormItem>
                                        )}
                                    />
                                </div>

                                <div className="flex justify-end">
                                    <Button type="submit" disabled={mutation.isPending}>
                                        <Save className="mr-2 size-4" />
                                        {mutation.isPending ? 'Saving...' : isEdit ? 'Save Extension' : 'Create Extension'}
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
