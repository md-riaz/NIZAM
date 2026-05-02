import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { AxiosError } from 'axios';
import { ArrowLeft, CalendarDays, Save } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate, useParams } from 'react-router-dom';
import { z } from 'zod';

import { Badge } from '@/components/ui/badge';
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
import api from '@/lib/api';
import type { Organization } from '@/types/models';

const organizationStatuses = ['trial', 'active', 'suspended', 'terminated'] as const;

const organizationSchema = z.object({
    name: z.string().min(1, 'Name is required'),
    domain_prefix: z.string().min(1, 'Domain prefix is required'),
    status: z.string().min(1, 'Status is required'),
    max_extensions: z.coerce.number().min(0),
    max_concurrent_calls: z.coerce.number().min(0),
    max_dids: z.coerce.number().min(0),
    max_teams: z.coerce.number().min(0),
    is_active: z.boolean(),
});

const normalizeOrganizationStatus = (status: string | null | undefined): string => {
    if (!status) return 'active';

    return organizationStatuses.includes(status as (typeof organizationStatuses)[number])
        ? status
        : 'active';
};

const normalizeDomainPrefix = (value: string) =>
    value
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9-]/g, '')
        .replace(/^-+/, '')
        .replace(/-+$/, '');

const normalizeDomainSuffix = (value: string | undefined) =>
    (value ?? '').trim().toLowerCase().replace(/^\.+/, '').replace(/\.+$/, '');

const serializeOrganizationPayload = (values: OrganizationFormValues) => ({
    name: values.name,
    domain_prefix: normalizeDomainPrefix(values.domain_prefix),
    status: values.status,
    max_extensions: values.max_extensions,
    max_concurrent_calls: values.max_concurrent_calls,
    max_dids: values.max_dids,
    max_teams: values.max_teams,
    is_active: values.is_active,
});

const getOrganizationStatusOptions = (currentStatus?: string | null): string[] => {
    const options = [...organizationStatuses];

    if (currentStatus && !options.includes(currentStatus as (typeof organizationStatuses)[number])) {
        return [currentStatus, ...options];
    }

    return options;
};

const formatStatusLabel = (status: string) =>
    status
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());

type OrganizationFormValues = z.infer<typeof organizationSchema>;

type PlatformSettingsResponse = {
    organization_domain_suffix?: string;
};

type DomainSuggestionResponse = {
    prefix: string;
    suffix: string;
    domain: string;
};

function getApiErrorMessages(error: unknown): Record<string, string[]> {
    if (error instanceof AxiosError) {
        return (error.response?.data as { errors?: Record<string, string[]> } | undefined)?.errors ?? {};
    }

    return {};
}

export default function OrganizationFormPage() {
    const { id } = useParams<{ id: string }>();
    const isEdit = Boolean(id);
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [prefixTouched, setPrefixTouched] = useState(false);
    const [isSuggesting, setIsSuggesting] = useState(false);
    const initializedCreateForm = useRef(false);

    const form = useForm<OrganizationFormValues>({
        resolver: zodResolver(organizationSchema),
        defaultValues: {
            name: '',
            domain_prefix: '',
            status: 'active',
            max_extensions: 0,
            max_concurrent_calls: 0,
            max_dids: 0,
            max_teams: 0,
            is_active: true,
        },
    });

    const { data: platformSettings } = useQuery<PlatformSettingsResponse>({
        queryKey: ['platform-settings'],
        queryFn: async () => {
            const response = await api.get('admin/platform-settings');
            return response.data.data;
        },
    });

    const configuredSuffix = normalizeDomainSuffix(platformSettings?.organization_domain_suffix);

    const { data: organization, isLoading: isFetching } = useQuery<Organization>({
        queryKey: ['organization', id],
        queryFn: async () => {
            const response = await api.get(`organizations/${id}`);
            return response.data.data;
        },
        enabled: isEdit,
    });

    useEffect(() => {
        if (!isEdit && !initializedCreateForm.current) {
            form.reset({
                name: '',
                domain_prefix: '',
                status: 'active',
                max_extensions: 0,
                max_concurrent_calls: 0,
                max_dids: 0,
                max_teams: 0,
                is_active: true,
            });
            setPrefixTouched(false);
            initializedCreateForm.current = true;
        }

        if (organization) {
            form.reset({
                name: organization.name ?? '',
                domain_prefix: organization.domain_prefix ?? organization.domain ?? '',
                status: normalizeOrganizationStatus(organization.status),
                max_extensions: organization.max_extensions ?? 0,
                max_concurrent_calls: organization.max_concurrent_calls ?? 0,
                max_dids: organization.max_dids ?? 0,
                max_teams: organization.max_teams ?? 0,
                is_active: organization.is_active ?? true,
            });
            setPrefixTouched(true);
        }
    }, [form, isEdit, organization]);

    const mutation = useMutation({
        mutationFn: async (values: OrganizationFormValues) => {
            const payload = serializeOrganizationPayload(values);

            if (isEdit) {
                return api.put(`organizations/${id}`, payload);
            }

            return api.post('organizations', payload);
        },
        onSuccess: async () => {
            await queryClient.invalidateQueries({ queryKey: ['organizations'] });
            navigate('/admin/organizations');
        },
        onError: (error) => {
            const errors = getApiErrorMessages(error);

            Object.entries(errors).forEach(([field, messages]) => {
                if (field === 'domain') {
                    form.setError('domain_prefix', { type: 'server', message: messages[0] });
                    return;
                }

                if (field === 'domain_prefix' || field === 'name' || field === 'status' || field === 'max_extensions' || field === 'max_concurrent_calls' || field === 'max_dids' || field === 'max_teams' || field === 'is_active') {
                    form.setError(field, { type: 'server', message: messages[0] });
                }
            });
        },
    });

    const composedDomain = useMemo(() => {
        const prefix = normalizeDomainPrefix(form.watch('domain_prefix'));

        if (!prefix) {
            return configuredSuffix ? `.${configuredSuffix}` : '';
        }

        return configuredSuffix ? `${prefix}.${configuredSuffix}` : prefix;
    }, [configuredSuffix, form.watch('domain_prefix')]);

    const requestSuggestedPrefix = async () => {
        if (isEdit || prefixTouched) {
            return;
        }

        const name = form.getValues('name').trim();
        const currentPrefix = normalizeDomainPrefix(form.getValues('domain_prefix'));

        if (!name || currentPrefix) {
            return;
        }

        setIsSuggesting(true);

        try {
            const response = await api.get<{ data: DomainSuggestionResponse }>('organizations/domain-suggestion', {
                params: { name },
            });
            const suggestion = response.data.data;
            form.setValue('domain_prefix', suggestion.prefix, { shouldDirty: true, shouldValidate: true });
        } finally {
            setIsSuggesting(false);
        }
    };

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div className="flex items-center gap-4">
                <Button variant="ghost" size="icon" onClick={() => navigate('/admin/organizations')}>
                    <ArrowLeft className="size-4" />
                    <span className="sr-only">Back to organizations</span>
                </Button>
                <div>
                    <p className="text-sm text-muted-foreground">Platform administration</p>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {isEdit ? 'Edit Organization' : 'Create Organization'}
                    </h1>
                </div>
            </div>

            <Card className="max-w-4xl">
                <CardHeader>
                    <CardTitle>Organization profile</CardTitle>
                    <CardDescription>
                        Manage organization identity, status, and platform resource limits.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {isEdit && organization ? (
                        <div className="mb-6 rounded-lg border border-border/70 bg-muted/30 p-4">
                            <div className="flex items-start gap-3">
                                <div className="rounded-md bg-background p-2 text-primary shadow-sm">
                                    <CalendarDays className="size-4" />
                                </div>
                                <div className="space-y-3">
                                    <div>
                                        <p className="text-sm font-medium">Business phone defaults</p>
                                        <p className="text-sm text-muted-foreground">
                                            Default schedule and holiday calendar are provisioned by backend business-phone setup.
                                        </p>
                                    </div>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="space-y-1">
                                            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                                Default schedule
                                            </p>
                                            <Badge variant="outline" className="font-mono text-xs">
                                                {organization.default_schedule_id ?? 'Not provisioned'}
                                            </Badge>
                                        </div>
                                        <div className="space-y-1">
                                            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                                Default holiday calendar
                                            </p>
                                            <Badge variant="outline" className="font-mono text-xs">
                                                {organization.default_holiday_calendar_id ?? 'Not provisioned'}
                                            </Badge>
                                        </div>
                                    </div>
                                    {organization.domain_matches_configured_suffix === false ? (
                                        <div className="rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-900 dark:text-amber-200">
                                            Existing domain does not match current configured suffix. Prefix field shows full legacy domain so you can review before saving.
                                        </div>
                                    ) : null}
                                </div>
                            </div>
                        </div>
                    ) : null}
                    {isFetching ? (
                        <div className="flex h-32 items-center justify-center">
                            <div className="size-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                        </div>
                    ) : (
                        <Form {...form}>
                            <form
                                onSubmit={form.handleSubmit((values: OrganizationFormValues) => mutation.mutate(values))}
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
                                                    <Input placeholder="Acme Telecom" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <FormField
                                        control={form.control}
                                        name="domain_prefix"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Domain</FormLabel>
                                                <FormControl>
                                                    <div className="flex overflow-hidden rounded-lg border border-input bg-card focus-within:ring-[3px] focus-within:ring-ring/50">
                                                        <Input
                                                            placeholder="acme"
                                                            className="rounded-none border-0 shadow-none focus-visible:ring-0"
                                                            {...field}
                                                            onChange={(event) => {
                                                                setPrefixTouched(true);
                                                                field.onChange(normalizeDomainPrefix(event.target.value));
                                                            }}
                                                            onFocus={async () => {
                                                                await requestSuggestedPrefix();
                                                            }}
                                                        />
                                                        {configuredSuffix ? (
                                                            <div className="flex items-center border-l bg-muted px-3 text-sm text-muted-foreground">
                                                                .{configuredSuffix}
                                                            </div>
                                                        ) : null}
                                                    </div>
                                                </FormControl>
                                                <FormDescription>
                                                    {isSuggesting
                                                        ? 'Generating unique prefix suggestion from organization name...'
                                                        : `Used for organization-facing SIP and WebRTC identity. Full domain: ${composedDomain || '—'}`}
                                                </FormDescription>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <FormField
                                        control={form.control}
                                        name="status"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Status</FormLabel>
                                                <Select
                                                    onValueChange={(value) => {
                                                        if (!getOrganizationStatusOptions(organization?.status).includes(value)) {
                                                            return;
                                                        }

                                                        field.onChange(value);
                                                    }}
                                                    value={field.value}
                                                >
                                                    <FormControl>
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Select status" />
                                                        </SelectTrigger>
                                                    </FormControl>
                                                    <SelectContent>
                                                        {getOrganizationStatusOptions(organization?.status).map((status) => (
                                                            <SelectItem key={status} value={status}>
                                                                {formatStatusLabel(status)}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                </div>

                                <div className="grid gap-6 md:grid-cols-2">
                                    <FormField
                                        control={form.control}
                                        name="max_extensions"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Max extensions</FormLabel>
                                                <FormControl>
                                                    <Input type="number" min="0" {...field} />
                                                </FormControl>
                                                <FormDescription>Use 0 for no enforced quota.</FormDescription>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <FormField
                                        control={form.control}
                                        name="max_concurrent_calls"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Max concurrent calls</FormLabel>
                                                <FormControl>
                                                    <Input type="number" min="0" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <FormField
                                        control={form.control}
                                        name="max_dids"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Max DIDs</FormLabel>
                                                <FormControl>
                                                    <Input type="number" min="0" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />

                                    <FormField
                                        control={form.control}
                                        name="max_teams"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Max teams</FormLabel>
                                                <FormControl>
                                                    <Input type="number" min="0" {...field} />
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
                                                <FormLabel>Organization active</FormLabel>
                                                <FormDescription>
                                                    Inactive organizations remain in system but should not be treated as operational.
                                                </FormDescription>
                                            </div>
                                        </FormItem>
                                    )}
                                />

                                <div className="flex justify-end">
                                    <Button type="submit" disabled={mutation.isPending}>
                                        <Save className="mr-2 size-4" />
                                        {mutation.isPending ? 'Saving...' : isEdit ? 'Save Organization' : 'Create Organization'}
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
