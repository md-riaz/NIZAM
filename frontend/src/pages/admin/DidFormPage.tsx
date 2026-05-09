import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Eye, EyeOff, Save, Trash2 } from 'lucide-react';
import { isAxiosError } from 'axios';
import { toast } from 'sonner';

import api from '@/lib/api';
import { useOrganization } from '@/context/OrganizationContext';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { Did, Extension, Flow, Gateway } from '@/types/models';

const recordingPolicyOptions = [
    { value: 'inherit', label: 'Inherit' },
    { value: 'off', label: 'Off' },
    { value: 'all', label: 'All calls' },
    { value: 'incoming', label: 'Incoming only' },
    { value: 'outgoing', label: 'Outgoing only' },
] as const;

const didSchema = z.object({
    number: z.string().min(1, 'Number is required'),
    description: z.string().optional(),
    recording_policy: z.enum(['inherit', 'off', 'all', 'incoming', 'outgoing']),
    destination_type: z.enum(['extension', 'flow'], {
        errorMap: () => ({ message: 'Destination type is required' }),
    }),
    destination_id: z.string().uuid('Destination is required'),
    is_active: z.boolean(),
});

const providerSchema = z.object({
    name: z.string().min(1, 'Provider name is required'),
    host: z.string().min(1, 'SIP host is required'),
    username: z.string().optional(),
    password: z.string().optional(),
    register: z.boolean(),
    is_active: z.boolean(),
});

type DidFormValues = z.infer<typeof didSchema>;
type ProviderFormValues = z.infer<typeof providerSchema>;

type DidResponse = Did;
type ProviderResponse = Gateway;

type DestinationOption = {
    id: string;
    label: string;
};

type CountryCallingCodeOption = {
    value: string;
    storedValue: string;
    label: string;
};

const countryCallingCodeOptions: CountryCallingCodeOption[] = [
    { value: '__none__', storedValue: '', label: 'No country code' },
    { value: '+880', storedValue: '+880', label: 'Bangladesh (+880)' },
    { value: '+1', storedValue: '+1', label: 'United States/Canada (+1)' },
    { value: '+44', storedValue: '+44', label: 'United Kingdom (+44)' },
    { value: '+61', storedValue: '+61', label: 'Australia (+61)' },
    { value: '+65', storedValue: '+65', label: 'Singapore (+65)' },
    { value: '+91', storedValue: '+91', label: 'India (+91)' },
    { value: '+971', storedValue: '+971', label: 'UAE (+971)' },
];

const noCountryCodeOptionValue = '__none__';

function getCountryOptionValue(storedValue: string): string {
    return storedValue || noCountryCodeOptionValue;
}

function getStoredCountryCode(optionValue: string): string {
    return countryCallingCodeOptions.find((option) => option.value === optionValue)?.storedValue ?? '';
}

function getCountryCodeMatches(): CountryCallingCodeOption[] {
    return countryCallingCodeOptions
        .filter((option) => option.storedValue)
        .sort((left, right) => right.storedValue.length - left.storedValue.length);
}

const emptyProviderValues: ProviderFormValues = {
    name: '',
    host: '',
    username: '',
    password: '',
    register: true,
    is_active: true,
};

function isUuid(value?: string | null): value is string {
    return typeof value === 'string' && z.string().uuid().safeParse(value).success;
}

function toDidFormValues(did?: DidResponse | null): DidFormValues {
    const destinationType = did?.destination_type;

    return {
        number: did?.number || '',
        description: did?.description || '',
        recording_policy: did?.recording_policy ?? 'inherit',
        destination_type: destinationType === 'flow' ? destinationType : 'extension',
        destination_id: isUuid(did?.destination_id) ? did.destination_id : '00000000-0000-0000-0000-000000000000',
        is_active: did?.is_active ?? true,
    };
}

function toProviderFormValues(gateway?: ProviderResponse | null): ProviderFormValues {
    return {
        name: gateway?.name || '',
        host: gateway?.host || '',
        username: gateway?.username || '',
        password: gateway?.password || '',
        register: gateway?.register ?? true,
        is_active: gateway?.is_active ?? true,
    };
}

function splitPhoneNumber(value?: string | null): { countryCode: string; nationalNumber: string } {
    const normalized = value?.trim() ?? '';

    if (!normalized.startsWith('+')) {
        return {
            countryCode: '',
            nationalNumber: normalized,
        };
    }

    const matchedCountryCode = getCountryCodeMatches()
        .find((option) => normalized.startsWith(option.storedValue));

    if (!matchedCountryCode) {
        return {
            countryCode: '',
            nationalNumber: normalized,
        };
    }

    return {
        countryCode: matchedCountryCode.storedValue,
        nationalNumber: normalized.slice(matchedCountryCode.storedValue.length),
    };
}

function buildStoredPhoneNumber(countryCode: string, nationalNumber: string): string {
    if (!countryCode) {
        return nationalNumber.replace(/[^\d+]/g, '');
    }

    const digits = nationalNumber.replace(/\D/g, '');

    return digits ? `${countryCode}${digits}` : '';
}

function getDestinationOptions(
    destinationType: DidFormValues['destination_type'],
    extensions: Extension[],
    flows: Flow[],
): DestinationOption[] {
    if (destinationType === 'flow') {
        const availableFlows = flows.filter((flow) => !!flow.active_version);
        const source = availableFlows.length > 0 ? availableFlows : flows;

        return source.map((flow) => ({
            id: flow.id,
            label: `${flow.name}${flow.active_version?.is_published ? ' (published)' : ' (draft)'}`,
        }));
    }

    return extensions.map((ext) => {
        const displayName = [ext.first_name, ext.last_name]
            .filter(Boolean)
            .join(' ');

        return {
            id: ext.id,
            label: displayName ? `${ext.extension} - ${displayName}` : ext.extension,
        };
    });
}

function getApiErrorMessage(error: unknown, fallback: string): string {
    if (!isAxiosError(error)) {
        return fallback;
    }

    const data = error.response?.data;
    if (!data || typeof data !== 'object') {
        return fallback;
    }

    const message = Reflect.get(data, 'message');
    if (typeof message === 'string' && message.length > 0) {
        return message;
    }

    const errors = Reflect.get(data, 'errors');
    if (!errors || typeof errors !== 'object') {
        return fallback;
    }

    for (const value of Object.values(errors)) {
        if (Array.isArray(value)) {
            const first = value.find((entry) => typeof entry === 'string');
            if (typeof first === 'string' && first.length > 0) {
                return first;
            }
        }

        if (typeof value === 'string' && value.length > 0) {
            return value;
        }
    }

    return fallback;
}

export default function DidFormPage() {
    const { id } = useParams<{ id: string }>();
    const isEdit = Boolean(id && id !== 'new');
    const navigate = useNavigate();
    const { activeOrganization, organizationApiPrefix } = useOrganization();
    const queryClient = useQueryClient();
    const [savedDidId, setSavedDidId] = useState<string | null>(isEdit ? (id ?? null) : null);
    const [activeTab, setActiveTab] = useState<'number' | 'provider'>('number');
    const [isProviderPasswordVisible, setIsProviderPasswordVisible] = useState(false);

    const currentDidId = savedDidId ?? id ?? null;

    const numberForm = useForm<DidFormValues>({
        resolver: zodResolver(didSchema),
        defaultValues: {
            number: '',
            description: '',
            recording_policy: 'inherit',
            destination_type: 'extension',
            destination_id: '00000000-0000-0000-0000-000000000000',
            is_active: true,
        },
    });

    const providerForm = useForm<ProviderFormValues>({
        resolver: zodResolver(providerSchema),
        defaultValues: emptyProviderValues,
    });

    const phoneNumber = numberForm.watch('number');
    const destType = numberForm.watch('destination_type');
    const selectedDestinationId = numberForm.watch('destination_id');

    const { data: did, isLoading: isFetching } = useQuery<DidResponse>({
        queryKey: ['did', currentDidId],
        queryFn: async () => {
            const res = await api.get(`${organizationApiPrefix}/dids/${currentDidId}`);
            return res.data.data;
        },
        enabled: isEdit && !!currentDidId && !!activeOrganization,
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


    const destinationOptions = useMemo(
        () => getDestinationOptions(destType, extensions, flows),
        [destType, extensions, flows],
    );

    const phoneNumberParts = useMemo(
        () => splitPhoneNumber(phoneNumber),
        [phoneNumber],
    );

    const destinationOptionsWithCurrent = useMemo(() => {
        if (!selectedDestinationId || destinationOptions.some((option) => option.id === selectedDestinationId)) {
            return destinationOptions;
        }

        return [
            ...destinationOptions,
            {
                id: selectedDestinationId,
                label: destType === 'flow'
                    ? 'Current flow'
                    : 'Current extension',
            },
        ];
    }, [destType, destinationOptions, selectedDestinationId]);

    useEffect(() => {
        if (!did) return;

        numberForm.reset(toDidFormValues(did));

        providerForm.reset(did.gateway ? toProviderFormValues(did.gateway) : emptyProviderValues);

        setSavedDidId(did.id);
    }, [did, numberForm, providerForm]);


    useEffect(() => {
        const emptyDestinationId = '00000000-0000-0000-0000-000000000000';

        if (currentDidId) {
            return;
        }

        if (destinationOptions.length === 0) {
            if (selectedDestinationId !== emptyDestinationId) {
                numberForm.setValue('destination_id', emptyDestinationId, {
                    shouldValidate: true,
                    shouldDirty: true,
                });
            }
            return;
        }

        if (!destinationOptions.some((option) => option.id === selectedDestinationId)) {
            numberForm.setValue('destination_id', destinationOptions[0].id, {
                shouldValidate: true,
                shouldDirty: true,
            });
        }
    }, [currentDidId, destinationOptions, numberForm, selectedDestinationId]);

    const saveNumberMutation = useMutation({
        mutationFn: async (values: DidFormValues) => {
            const payload = {
                ...values,
                description: values.description?.trim() || null,
            };

            if (currentDidId) {
                const response = await api.put(`${organizationApiPrefix}/dids/${currentDidId}`, payload);
                return response.data.data as DidResponse;
            }

            const response = await api.post(`${organizationApiPrefix}/dids`, payload);
            return response.data.data as DidResponse;
        },
        onSuccess: async (savedDid) => {
            setSavedDidId(savedDid.id);
            queryClient.invalidateQueries({ queryKey: ['dids'] });
            queryClient.setQueryData(['did', savedDid.id], savedDid);
            numberForm.reset(toDidFormValues(savedDid));
            toast.success(currentDidId ? 'Number updated.' : 'Number created.');

            if (!currentDidId) {
                navigate(`/admin/numbers/${savedDid.id}/edit`, { replace: true });
            }
        },
        onError: (error) => {
            const message = getApiErrorMessage(error, 'Failed to save number.');

            if (isAxiosError(error)) {
                const data = error.response?.data;
                const errors = data && typeof data === 'object' ? Reflect.get(data, 'errors') : null;

                if (errors && typeof errors === 'object') {
                    for (const fieldName of ['number', 'description', 'recording_policy', 'destination_type', 'destination_id'] as const) {
                        const fieldErrors = Reflect.get(errors, fieldName);
                        const fieldMessage = Array.isArray(fieldErrors)
                            ? fieldErrors.find((value) => typeof value === 'string')
                            : typeof fieldErrors === 'string'
                                ? fieldErrors
                                : null;

                        if (fieldMessage) {
                            numberForm.setError(fieldName, { type: 'server', message: fieldMessage });
                        }
                    }
                }
            }

            toast.error(message);
        },
    });

    const saveProviderMutation = useMutation({
        mutationFn: async (values: ProviderFormValues) => {
            if (!savedDidId) {
                throw new Error('Save number first.');
            }

            if (did?.gateway_id) {
                const response = await api.put(`${organizationApiPrefix}/dids/${savedDidId}/provider`, values);
                return response.data.data as DidResponse;
            }

            const response = await api.post(`${organizationApiPrefix}/dids/${savedDidId}/provider`, values);
            return response.data.data as DidResponse;
        },
        onSuccess: (updatedDid) => {
            setSavedDidId(updatedDid.id);
            queryClient.invalidateQueries({ queryKey: ['dids'] });
            queryClient.setQueryData(['did', updatedDid.id], updatedDid);
            providerForm.reset(toProviderFormValues(updatedDid.gateway));
            toast.success('Provider saved.');
        },
        onError: (error) => {
            toast.error(getApiErrorMessage(error, 'Failed to save provider.'));
        },
    });

    const removeProviderMutation = useMutation({
        mutationFn: async () => {
            if (!savedDidId) return null;

            const response = await api.delete(`${organizationApiPrefix}/dids/${savedDidId}/provider`);
            return response.data.data as DidResponse;
        },
        onSuccess: (updatedDid) => {
            if (!updatedDid) return;

            queryClient.invalidateQueries({ queryKey: ['dids'] });
            queryClient.setQueryData(['did', updatedDid.id], updatedDid);
            providerForm.reset(emptyProviderValues);
            toast.success('Provider removed from number.');
        },
        onError: (error) => {
            toast.error(getApiErrorMessage(error, 'Failed to remove provider.'));
        },
    });

    const onSubmitNumber = (values: DidFormValues) => {
        if (destinationOptions.length === 0) {
            numberForm.setError('destination_id', {
                type: 'manual',
                message: destType === 'flow'
                    ? 'Create or publish a call flow before assigning this number.'
                    : 'Create an extension before assigning this number.',
            });
            return;
        }

        saveNumberMutation.mutate({
            ...values,
            destination_id: destinationOptions.some((option) => option.id === values.destination_id)
                ? values.destination_id
                : destinationOptions[0].id,
        });
    };

    const onSubmitProvider = (values: ProviderFormValues) => {
        if (!savedDidId) {
            toast.message('Save number first. Provider draft stays on this page until number exists.');
            return;
        }

        saveProviderMutation.mutate(values);
    };

    const hasSavedProvider = Boolean(did?.gateway_id);
    const isBusy = saveNumberMutation.isPending || saveProviderMutation.isPending || removeProviderMutation.isPending;

    if (!activeOrganization) return null;

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div className="flex items-center gap-4">
                <Button variant="ghost" size="icon" onClick={() => navigate('/admin/numbers')}>
                    <ArrowLeft className="size-4" />
                </Button>
                <div>
                    <p className="text-sm text-muted-foreground">
                        {activeOrganization.name} &rsaquo; Numbers
                    </p>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {currentDidId ? 'Edit Number' : 'Add Number'}
                    </h1>
                </div>
            </div>

            <Tabs value={activeTab} onValueChange={(value) => setActiveTab(value as 'number' | 'provider')} className="space-y-6 max-w-4xl">
                <TabsList>
                    <TabsTrigger value="number">Number Details</TabsTrigger>
                    <TabsTrigger value="provider">Provider</TabsTrigger>
                </TabsList>

                <TabsContent value="number">
                    <Card>
                        <CardHeader>
                            <CardTitle>Number Details</CardTitle>
                            <CardDescription>
                                Configure inbound number details and where calls should route.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {isFetching ? (
                                <div className="flex h-32 items-center justify-center">
                                    <div className="size-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                                </div>
                            ) : (
                                <Form {...numberForm}>
                                    <form onSubmit={numberForm.handleSubmit(onSubmitNumber)} className="space-y-6">
                                        <FormField
                                            control={numberForm.control}
                                            name="number"
                                            render={({ field }) => (
                                                <FormItem>
                                                    <FormLabel>Number</FormLabel>
                                                    <div className="grid gap-3 sm:grid-cols-[220px_minmax(0,1fr)]">
                                                        <Select
                                                            value={getCountryOptionValue(phoneNumberParts.countryCode)}
                                                            onValueChange={(value) => {
                                                                field.onChange(buildStoredPhoneNumber(getStoredCountryCode(value), phoneNumberParts.nationalNumber));
                                                            }}
                                                        >
                                                            <FormControl>
                                                                <SelectTrigger>
                                                                    <SelectValue placeholder="Select country code" />
                                                                </SelectTrigger>
                                                            </FormControl>
                                                            <SelectContent>
                                                                {countryCallingCodeOptions.map((option) => (
                                                                    <SelectItem key={option.value} value={option.value}>
                                                                        {option.label}
                                                                    </SelectItem>
                                                                ))}
                                                            </SelectContent>
                                                        </Select>
                                                        <FormControl>
                                                            <Input
                                                                type="tel"
                                                                inputMode="tel"
                                                                placeholder="e.g. 9644196197"
                                                                value={phoneNumberParts.nationalNumber}
                                                                onChange={(event) => {
                                                                    field.onChange(buildStoredPhoneNumber(phoneNumberParts.countryCode, event.target.value));
                                                                }}
                                                                name={field.name}
                                                                onBlur={field.onBlur}
                                                                ref={field.ref}
                                                            />
                                                        </FormControl>
                                                    </div>
                                                    <FormDescription>
                                                        Choose country code for E.164 format, or leave empty to keep local number format.
                                                    </FormDescription>
                                                    <FormMessage />
                                                </FormItem>
                                            )}
                                        />

                                        <FormField
                                            control={numberForm.control}
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

                                        <FormField
                                            control={numberForm.control}
                                            name="recording_policy"
                                            render={({ field }) => (
                                                <FormItem>
                                                    <FormLabel>Recording policy</FormLabel>
                                                    <Select onValueChange={field.onChange} value={field.value}>
                                                        <FormControl>
                                                            <SelectTrigger>
                                                                <SelectValue placeholder="Select recording policy" />
                                                            </SelectTrigger>
                                                        </FormControl>
                                                        <SelectContent>
                                                            {recordingPolicyOptions.map((option) => (
                                                                <SelectItem key={option.value} value={option.value}>
                                                                    {option.label}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                    <FormDescription>Override inbound automatic recording for calls answered from this number.</FormDescription>
                                                    <FormMessage />
                                                </FormItem>
                                            )}
                                        />

                                        <div className="grid gap-6 sm:grid-cols-2">
                                            <FormField
                                                control={numberForm.control}
                                                name="destination_type"
                                                render={({ field }) => (
                                                    <FormItem>
                                                        <FormLabel>Destination Type</FormLabel>
                                                        <Select
                                                            onValueChange={(value) => {
                                                                if (value !== 'extension' && value !== 'flow') {
                                                                    return;
                                                                }

                                                                field.onChange(value);
                                                                const nextOptions = getDestinationOptions(value, extensions, flows);
                                                                numberForm.setValue(
                                                                    'destination_id',
                                                                    nextOptions[0]?.id ?? '00000000-0000-0000-0000-000000000000',
                                                                    { shouldValidate: true, shouldDirty: true },
                                                                );
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

                                            <FormField
                                                control={numberForm.control}
                                                name="destination_id"
                                                render={({ field }) => (
                                                    <FormItem>
                                                        <FormLabel>
                                                            {destType === 'flow'
                                                                ? 'Destination Flow'
                                                                : 'Destination Extension'}
                                                        </FormLabel>
                                                        <Select
                                                            onValueChange={field.onChange}
                                                            value={destinationOptionsWithCurrent.some((option) => option.id === field.value) ? field.value : ''}
                                                            disabled={destinationOptions.length === 0}
                                                        >
                                                            <FormControl>
                                                                <SelectTrigger>
                                                                    <SelectValue placeholder={destType === 'flow' ? 'Select flow' : 'Select extension'} />
                                                                </SelectTrigger>
                                                            </FormControl>
                                                            <SelectContent>
                                                                {destinationOptionsWithCurrent.length > 0 ? (
                                                                    destinationOptionsWithCurrent.map((option) => (
                                                                        <SelectItem key={option.id} value={option.id}>
                                                                            {option.label}
                                                                        </SelectItem>
                                                                    ))
                                                                ) : (
                                                                    <SelectItem value="__empty" disabled>
                                                                        {destType === 'flow'
                                                                            ? 'Create or publish a call flow first'
                                                                            : 'Create an extension first'}
                                                                    </SelectItem>
                                                                )}
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
                                        </div>

                                        <FormField
                                            control={numberForm.control}
                                            name="is_active"
                                            render={({ field }) => (
                                                <FormItem className="flex flex-row items-start space-x-3 space-y-0 rounded-md border p-4">
                                                    <FormControl>
                                                        <Checkbox checked={field.value} onCheckedChange={(checked) => field.onChange(Boolean(checked))} />
                                                    </FormControl>
                                                    <div className="space-y-1 leading-none">
                                                        <FormLabel>Active</FormLabel>
                                                        <FormDescription>
                                                            Inactive numbers stay saved but will not be treated as active routes.
                                                        </FormDescription>
                                                    </div>
                                                </FormItem>
                                            )}
                                        />

                                        <div className="flex justify-end">
                                            <Button type="submit" disabled={isBusy || destinationOptions.length === 0}>
                                                <Save className="mr-2 size-4" />
                                                {saveNumberMutation.isPending ? 'Saving...' : 'Save Number'}
                                            </Button>
                                        </div>
                                    </form>
                                </Form>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="provider">
                    <Card>
                        <CardHeader>
                            <CardTitle>Provider</CardTitle>
                            <CardDescription>
                                Save provider settings separately from number routing. Before first number save, provider details stay as draft on this page only.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {isFetching ? (
                                <div className="flex h-32 items-center justify-center">
                                    <div className="size-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                                </div>
                            ) : (
                                <Form {...providerForm}>
                                    <form onSubmit={providerForm.handleSubmit(onSubmitProvider)} className="space-y-6">
                                        {!savedDidId && (
                                            <div className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                                                Save number first to create provider connection. Anything entered here stays in memory until then.
                                            </div>
                                        )}

                                        <FormField
                                            control={providerForm.control}
                                            name="name"
                                            render={({ field }) => (
                                                <FormItem>
                                                    <FormLabel>Provider Name</FormLabel>
                                                    <FormControl>
                                                        <Input placeholder="e.g. Twilio Elastic SIP" {...field} />
                                                    </FormControl>
                                                    <FormDescription>
                                                        Friendly name used for this number's provider connection.
                                                    </FormDescription>
                                                    <FormMessage />
                                                </FormItem>
                                            )}
                                        />

                                        <FormField
                                            control={providerForm.control}
                                            name="host"
                                            render={({ field }) => (
                                                <FormItem>
                                                    <FormLabel>SIP Host</FormLabel>
                                                    <FormControl>
                                                        <Input placeholder="e.g. sip.twilio.com" {...field} />
                                                    </FormControl>
                                                    <FormDescription>
                                                        Primary SIP server hostname or IP address for this provider.
                                                    </FormDescription>
                                                    <FormMessage />
                                                </FormItem>
                                            )}
                                        />

                                        <div className="grid gap-6 sm:grid-cols-2">
                                            <FormField
                                                control={providerForm.control}
                                                name="username"
                                                render={({ field }) => (
                                                    <FormItem>
                                                        <FormLabel>Username</FormLabel>
                                                        <FormControl>
                                                            <Input placeholder="Optional" {...field} />
                                                        </FormControl>
                                                        <FormMessage />
                                                    </FormItem>
                                                )}
                                            />

                                            <FormField
                                                control={providerForm.control}
                                                name="password"
                                                render={({ field }) => (
                                                    <FormItem>
                                                        <FormLabel>Password</FormLabel>
                                                        <FormControl>
                                                            <div className="relative">
                                                                <Input
                                                                    type={isProviderPasswordVisible ? 'text' : 'password'}
                                                                    placeholder="Optional"
                                                                    className="pr-11"
                                                                    {...field}
                                                                />
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="absolute right-1 top-1/2 size-8 -translate-y-1/2"
                                                                    onClick={() => setIsProviderPasswordVisible((visible) => !visible)}
                                                                    aria-label={isProviderPasswordVisible ? 'Hide password' : 'Show password'}
                                                                >
                                                                    {isProviderPasswordVisible ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                                                </Button>
                                                            </div>
                                                        </FormControl>
                                                        <FormDescription>
                                                            Hidden by default. Reveal to verify saved provider password.
                                                        </FormDescription>
                                                        <FormMessage />
                                                    </FormItem>
                                                )}
                                            />
                                        </div>

                                        <FormField
                                            control={providerForm.control}
                                            name="register"
                                            render={({ field }) => (
                                                <FormItem className="flex flex-row items-start space-x-3 space-y-0 rounded-md border p-4">
                                                    <FormControl>
                                                        <Checkbox checked={field.value} onCheckedChange={(checked) => field.onChange(Boolean(checked))} />
                                                    </FormControl>
                                                    <div className="space-y-1 leading-none">
                                                        <FormLabel>Register</FormLabel>
                                                        <FormDescription>
                                                            Send SIP REGISTER requests to this provider.
                                                        </FormDescription>
                                                    </div>
                                                </FormItem>
                                            )}
                                        />

                                        <FormField
                                            control={providerForm.control}
                                            name="is_active"
                                            render={({ field }) => (
                                                <FormItem className="flex flex-row items-start space-x-3 space-y-0 rounded-md border p-4">
                                                    <FormControl>
                                                        <Checkbox checked={field.value} onCheckedChange={(checked) => field.onChange(Boolean(checked))} />
                                                    </FormControl>
                                                    <div className="space-y-1 leading-none">
                                                        <FormLabel>Active</FormLabel>
                                                        <FormDescription>
                                                            Keep provider enabled for this number.
                                                        </FormDescription>
                                                    </div>
                                                </FormItem>
                                            )}
                                        />

                                        <div className="flex flex-col gap-3 sm:flex-row sm:justify-end">
                                            {hasSavedProvider && savedDidId && (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    className="text-destructive"
                                                    disabled={isBusy}
                                                    onClick={() => removeProviderMutation.mutate()}
                                                >
                                                    <Trash2 className="mr-2 size-4" />
                                                    {removeProviderMutation.isPending ? 'Removing...' : 'Remove Provider'}
                                                </Button>
                                            )}
                                            <Button type="submit" disabled={isBusy}>
                                                <Save className="mr-2 size-4" />
                                                {saveProviderMutation.isPending ? 'Saving...' : 'Save Provider'}
                                            </Button>
                                        </div>
                                    </form>
                                </Form>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    );
}
