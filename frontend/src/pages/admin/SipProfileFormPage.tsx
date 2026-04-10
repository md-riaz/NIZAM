import { zodResolver } from '@hookform/resolvers/zod';
import { useQuery } from '@tanstack/react-query';
import { AlertTriangle, Plus, Save, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useForm } from 'react-hook-form';
import { useBlocker, useNavigate, useParams } from 'react-router-dom';
import { z } from 'zod';

import { PageHeader } from '@/components/scaffolds/PageHeader';
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
    FormField,
    FormItem,
    FormLabel,
    FormMessage,
} from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import api from '@/lib/api';
import { getErrorMessage, useApiMutation } from '@/lib/api-hooks';

const sipProfileSchema = z.object({
    name: z.string().trim().min(1, 'Name is required'),
    hostname: z.string(),
    description: z.string(),
    is_active: z.boolean(),
});

type SipProfileFormValues = z.infer<typeof sipProfileSchema>;

type SipProfileSetting = {
    id?: string;
    name: string;
    value: string;
    is_enabled: boolean;
    description: string | null;
    is_deleted?: boolean;
};

type SipProfile = {
    id: string;
    name: string;
    hostname: string | null;
    description: string | null;
    settings: SipProfileSetting[];
    is_active: boolean;
};

const WS_SETTING_FIELDS = [
    { name: 'ws-binding', label: 'WS binding', placeholder: ':5066' },
] as const;

const WSS_SETTING_FIELDS = [
    { name: 'wss-binding', label: 'WSS binding', placeholder: ':7443' },
    { name: 'tls-cert-dir', label: 'TLS certificate directory', placeholder: '/usr/local/freeswitch/certs' },
    { name: 'tls-sip-port', label: 'TLS SIP port', placeholder: '7443' },
    { name: 'tls-version', label: 'TLS version', placeholder: 'tlsv1.2' },
] as const;

const SHARED_BOOLEAN_FIELDS = [
    { name: 'enable-ice', label: 'Enable ICE' },
] as const;

const WSS_BOOLEAN_FIELDS = [
    { name: 'dtls-srtp', label: 'Enable DTLS-SRTP' },
    { name: 'tls-only', label: 'TLS only' },
    { name: 'tls-verify-date', label: 'Verify TLS date' },
] as const;

const WEBRTC_HIDDEN_SETTINGS = new Set([
    'ws-binding',
    'wss-binding',
    'tls-cert-dir',
    'tls-sip-port',
    'tls-version',
    'dtls-srtp',
    'enable-ice',
    'tls-only',
    'tls-verify-date',
    'tls',
    'tls-bind-params',
    'tls-verify-policy',
    'tls-verify-depth',
    'dtls-verify-policy',
]);

function getDefaultWebRtcValue(name: string): string {
    switch (name) {
        case 'ws-binding': return ':5066';
        case 'wss-binding': return ':7443';
        case 'tls': return 'true';
        case 'tls-bind-params': return 'transport=wss';
        case 'tls-sip-port': return '7443';
        case 'tls-cert-dir': return '/usr/local/freeswitch/certs';
        case 'tls-version': return 'tlsv1.2';
        case 'tls-verify-date': return 'true';
        case 'tls-verify-policy': return 'none';
        case 'tls-verify-depth': return '2';
        case 'dtls-srtp': return 'true';
        case 'dtls-verify-policy': return 'fingerprint';
        case 'enable-ice': return 'true';
        default: return 'true';
    }
}

function normalizeProfile(raw: any): SipProfile {
    return {
        id: String(raw.id),
        name: raw.name,
        hostname: raw.hostname ?? null,
        description: raw.description ?? null,
        settings: (Array.isArray(raw.settings) ? raw.settings : [])
            .filter((setting: any) => setting && typeof setting === 'object')
            .map((setting: any) => ({
                id: setting.id,
                name: setting.name,
                value: setting.value,
                is_enabled: Boolean(setting.is_enabled),
                description: setting.description ?? null,
            })),
        is_active: Boolean(raw.is_active),
    };
}

function serializeSettings(settings: SipProfileSetting[]) {
    return JSON.stringify(
        settings.map((setting) => ({
            id: setting.id ?? null,
            name: setting.name,
            value: setting.value,
            is_enabled: setting.is_enabled,
            description: setting.description ?? '',
            is_deleted: Boolean(setting.is_deleted),
        })),
    );
}

export default function SipProfileFormPage() {
    const { id } = useParams<{ id: string }>();
    const isEdit = Boolean(id);
    const navigate = useNavigate();
    const [settings, setSettings] = useState<SipProfileSetting[]>([]);
    const [activeTab, setActiveTab] = useState('general');
    const [formError, setFormError] = useState<string | null>(null);
    const [hasAttemptedSubmit, setHasAttemptedSubmit] = useState(false);
    const initialSnapshotRef = useRef<string>('');

    const form = useForm<SipProfileFormValues>({
        resolver: zodResolver(sipProfileSchema),
        defaultValues: {
            name: '',
            hostname: '',
            description: '',
            is_active: true,
        },
    });

    const { data: profile, isLoading: isFetching } = useQuery({
        queryKey: ['admin-sip-profile', id],
        queryFn: async () => {
            const response = await api.get(`admin/sip-profiles/${id}`);
            return normalizeProfile(response.data);
        },
        enabled: isEdit,
    });

    useEffect(() => {
        if (!profile) {
            if (!isEdit) {
                initialSnapshotRef.current = JSON.stringify({
                    values: form.getValues(),
                    settings: serializeSettings([]),
                });
            }
            return;
        }

        form.reset({
            name: profile.name,
            hostname: profile.hostname ?? '',
            description: profile.description ?? '',
            is_active: profile.is_active,
        });
        setSettings(profile.settings.map((setting) => ({ ...setting })));
        setFormError(null);
    }, [profile, form, isEdit]);

    useEffect(() => {
        initialSnapshotRef.current = JSON.stringify({
            values: form.getValues(),
            settings: serializeSettings(profile?.settings ?? []),
        });
    }, [form, profile]);

    const watchedValues = form.watch();
    const currentName = watchedValues.name ?? '';
    const isInternalProfile = currentName.trim().toLowerCase() === 'internal';

    const visibleSettings = useMemo(
        () => settings.filter((setting) => !setting.is_deleted && (!isInternalProfile || !WEBRTC_HIDDEN_SETTINGS.has(setting.name))),
        [settings, isInternalProfile],
    );

    const currentSnapshot = useMemo(
        () => JSON.stringify({ values: watchedValues, settings: serializeSettings(settings) }),
        [watchedValues, settings],
    );

    const hasUnsavedChanges = currentSnapshot !== initialSnapshotRef.current;

    const getSetting = (name: string) => settings.find((setting) => setting.name === name && !setting.is_deleted);

    const isWsEnabled = Boolean(getSetting('ws-binding')?.is_enabled);
    const isWssEnabled = Boolean(getSetting('wss-binding')?.is_enabled);
    const isAnyTransportEnabled = isWsEnabled || isWssEnabled;

    const updateSetting = (index: number, field: keyof SipProfileSetting, value: string | boolean | null) => {
        setSettings((current) => {
            const next = [...current];
            next[index] = { ...next[index], [field]: value };
            return next;
        });
    };

    const addSetting = () => {
        setSettings((current) => [
            ...current,
            { name: '', value: '', is_enabled: true, description: '' },
        ]);
    };

    const removeSetting = (index: number) => {
        setSettings((current) => {
            const next = [...current];
            if (next[index].id) {
                next[index] = { ...next[index], is_deleted: true };
            } else {
                next.splice(index, 1);
            }
            return next;
        });
    };

    const upsertSettingByName = (name: string, updater: (current?: SipProfileSetting) => SipProfileSetting) => {
        setSettings((current) => {
            const next = [...current];
            const index = next.findIndex((setting) => setting.name === name && !setting.is_deleted);
            const existing = index >= 0 ? next[index] : undefined;
            const updated = updater(existing);

            if (index >= 0) {
                next[index] = { ...next[index], ...updated, is_deleted: false };
            } else {
                next.push(updated);
            }

            return next;
        });
    };

    const setTransportEnabled = (transport: 'ws' | 'wss', enabled: boolean) => {
        const managedSettings = transport === 'ws'
            ? ['ws-binding']
            : [
                'wss-binding',
                'tls',
                'tls-bind-params',
                'tls-sip-port',
                'tls-cert-dir',
                'tls-version',
                'tls-verify-date',
                'tls-verify-policy',
                'tls-verify-depth',
                'dtls-srtp',
                'dtls-verify-policy',
            ];

        managedSettings.forEach((name) => {
            upsertSettingByName(name, (current) => ({
                id: current?.id,
                name,
                value: current?.value ?? getDefaultWebRtcValue(name),
                is_enabled: enabled,
                description: current?.description ?? null,
            }));
        });

        // Handle shared ICE setting: it should be enabled if ANY transport is enabled after this update
        const willWsBeEnabled = transport === 'ws' ? enabled : isWsEnabled;
        const willWssBeEnabled = transport === 'wss' ? enabled : isWssEnabled;
        const willAnyBeEnabled = willWsBeEnabled || willWssBeEnabled;

        upsertSettingByName('enable-ice', (current) => ({
            id: current?.id,
            name: 'enable-ice',
            value: current?.value ?? getDefaultWebRtcValue('enable-ice'),
            is_enabled: willAnyBeEnabled,
            description: current?.description ?? null,
        }));
    };

    const setWebRtcTextSetting = (name: string, value: string) => {
        upsertSettingByName(name, (current) => {
            const defaultEnabled = WS_SETTING_FIELDS.some(f => f.name === name)
                ? isWsEnabled
                : WSS_SETTING_FIELDS.some(f => f.name === name)
                    ? isWssEnabled
                    : isAnyTransportEnabled;

            return {
                id: current?.id,
                name,
                value,
                is_enabled: current?.is_enabled ?? defaultEnabled,
                description: current?.description ?? null,
            };
        });
    };

    const setWebRtcBooleanSetting = (name: string, checked: boolean) => {
        upsertSettingByName(name, (current) => {
            const defaultEnabled = WSS_BOOLEAN_FIELDS.some(f => f.name === name)
                ? isWssEnabled
                : isAnyTransportEnabled;

            return {
                id: current?.id,
                name,
                value: checked ? 'true' : 'false',
                is_enabled: current?.is_enabled ?? defaultEnabled,
                description: current?.description ?? null,
            };
        });
    };

    const mutation = useApiMutation({
        mutationFn: async ({ values }: { values: SipProfileFormValues }) => {
            const activeSettings = settings.filter((setting) => !setting.is_deleted);
            const deletedSettingsIds = settings
                .filter((setting) => setting.is_deleted && setting.id)
                .map((setting) => setting.id as string);

            const invalidSettingIndex = activeSettings.findIndex(
                (setting) => !setting.name.trim() || !setting.value.trim(),
            );

            if (invalidSettingIndex >= 0) {
                const invalidSetting = activeSettings[invalidSettingIndex];
                if (!invalidSetting.name.trim() && !invalidSetting.value.trim()) {
                    throw new Error(`Setting #${invalidSettingIndex + 1} is missing both name and value.`);
                }
                if (!invalidSetting.name.trim()) {
                    throw new Error(`Setting #${invalidSettingIndex + 1} is missing a name.`);
                }
                throw new Error(`Setting #${invalidSettingIndex + 1} is missing a value.`);
            }

            // Transport-specific required-field validation
            const wsBinding = activeSettings.find((s) => s.name === 'ws-binding' && s.is_enabled);
            if (wsBinding && !wsBinding.value.trim()) {
                throw new Error('WS binding is required when WS transport is enabled.');
            }
            const wssBinding = activeSettings.find((s) => s.name === 'wss-binding' && s.is_enabled);
            if (wssBinding && !wssBinding.value.trim()) {
                throw new Error('WSS binding is required when WSS transport is enabled.');
            }

            const payload = {
                name: values.name.trim(),
                hostname: values.hostname.trim() || undefined,
                description: values.description.trim() || undefined,
                is_active: values.is_active,
                settings: activeSettings,
                settings_to_delete: deletedSettingsIds,
            };

            if (isEdit) {
                const response = await api.put(`admin/sip-profiles/${id}`, payload);
                return normalizeProfile(response.data);
            }

            const response = await api.post('admin/sip-profiles', payload);
            return normalizeProfile(response.data);
        },
        successMessage: isEdit ? 'SIP profile updated.' : 'SIP profile created.',
        invalidateQueries: [['admin-sip-profiles-security']],
        onSuccess: (savedProfile) => {
            const nextValues = {
                name: savedProfile.name,
                hostname: savedProfile.hostname ?? '',
                description: savedProfile.description ?? '',
                is_active: savedProfile.is_active,
            };
            form.reset(nextValues);
            setSettings(savedProfile.settings.map((setting) => ({ ...setting })));
            initialSnapshotRef.current = JSON.stringify({
                values: nextValues,
                settings: serializeSettings(savedProfile.settings),
            });
            setFormError(null);
            setHasAttemptedSubmit(false);
        },
        onError: (error) => {
            setFormError(getErrorMessage(error));
        },
    });

    const guardedNavigate = (to: string) => {
        if (mutation.isPending) return;
        if (hasUnsavedChanges && !window.confirm('You have unsaved changes. Leave this page?')) {
            return;
        }
        navigate(to);
    };

    const handleSubmit = form.handleSubmit(async (values) => {
        setHasAttemptedSubmit(true);
        setFormError(null);
        await mutation.mutateAsync({ values });
    });

    const handleSaveAndClose = form.handleSubmit(async (values) => {
        setHasAttemptedSubmit(true);
        setFormError(null);
        const savedProfile = await mutation.mutateAsync({ values });
        initialSnapshotRef.current = JSON.stringify({
            values: {
                name: savedProfile.name,
                hostname: savedProfile.hostname ?? '',
                description: savedProfile.description ?? '',
                is_active: savedProfile.is_active,
            },
            settings: serializeSettings(savedProfile.settings),
        });
        navigate('/admin/sip-profiles');
    });

    const navigationBlocker = useBlocker(hasUnsavedChanges && !mutation.isPending);

    useEffect(() => {
        const onBeforeUnload = (event: BeforeUnloadEvent) => {
            if (!hasUnsavedChanges || mutation.isPending) return;
            event.preventDefault();
            event.returnValue = '';
        };

        window.addEventListener('beforeunload', onBeforeUnload);
        return () => window.removeEventListener('beforeunload', onBeforeUnload);
    }, [hasUnsavedChanges, mutation.isPending]);

    useEffect(() => {
        if (navigationBlocker.state !== 'blocked') {
            return;
        }

        if (window.confirm('You have unsaved changes. Leave this page?')) {
            navigationBlocker.proceed();
        } else {
            navigationBlocker.reset();
        }
    }, [navigationBlocker]);

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (mutation.isPending) return;

            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
                event.preventDefault();
                void handleSubmit();
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                guardedNavigate('/admin/sip-profiles');
                return;
            }

            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'n' && activeTab === 'parameters') {
                event.preventDefault();
                addSetting();
            }
        };

        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, [activeTab, handleSubmit, mutation.isPending, hasUnsavedChanges, settings]);

    return (
        <div className="space-y-6 p-6 lg:p-8 pb-32">
            <PageHeader
                title={isEdit ? 'Edit SIP Profile' : 'Create SIP Profile'}
                description="Use a focused editor for SIP profile metadata, internal WebRTC settings, and low-level parameters."
                breadcrumbs="Platform Admin › Telephony"
                actionLabel="Back to SIP Profiles"
                onAction={() => guardedNavigate('/admin/sip-profiles')}
                actionIcon={null}
            />

            {isFetching ? (
                <div className="flex h-32 items-center justify-center">
                    <div className="size-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                </div>
            ) : (
                <Form {...form}>
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <Tabs value={activeTab} onValueChange={setActiveTab}>
                            <TabsList>
                                <TabsTrigger value="general">General</TabsTrigger>
                                <TabsTrigger value="parameters">
                                    Parameters
                                    <Badge variant="secondary" className="ml-2">{visibleSettings.length}</Badge>
                                </TabsTrigger>
                            </TabsList>

                            <TabsContent value="general" className="space-y-6">
                                <Card className="max-w-5xl">
                                    <CardHeader>
                                        <CardTitle>Profile details</CardTitle>
                                        <CardDescription>
                                            Core profile identity and routing options.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-6">
                                        <div className="grid gap-6 md:grid-cols-2">
                                            <FormField
                                                control={form.control}
                                                name="name"
                                                render={({ field }) => (
                                                    <FormItem>
                                                        <FormLabel required>Name</FormLabel>
                                                        <FormControl>
                                                            <Input placeholder="e.g. internal" {...field} />
                                                        </FormControl>
                                                        <FormMessage />
                                                    </FormItem>
                                                )}
                                            />
                                            <FormField
                                                control={form.control}
                                                name="hostname"
                                                render={({ field }) => (
                                                    <FormItem>
                                                        <FormLabel>Hostname</FormLabel>
                                                        <FormControl>
                                                            <Input placeholder="Specific routing host" {...field} />
                                                        </FormControl>
                                                        <FormMessage />
                                                    </FormItem>
                                                )}
                                            />
                                            <FormField
                                                control={form.control}
                                                name="description"
                                                render={({ field }) => (
                                                    <FormItem className="md:col-span-2">
                                                        <FormLabel>Description</FormLabel>
                                                        <FormControl>
                                                            <Input placeholder="Short profile purpose" {...field} />
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
                                                        <FormLabel>Enable profile</FormLabel>
                                                        <p className="text-sm text-muted-foreground">
                                                            Disabled profiles stay in the database but are inactive.
                                                        </p>
                                                    </div>
                                                </FormItem>
                                            )}
                                        />
                                    </CardContent>
                                </Card>

                                {isInternalProfile && (
                                    <Card className="max-w-5xl">
                                        <CardHeader>
                                            <CardTitle>WebRTC Transport</CardTitle>
                                            <CardDescription>
                                                Enable WS and/or WSS transports independently. Use WS only when proxying through NGINX.
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent className="space-y-6">
                                            {/* WS Transport */}
                                            <div className="space-y-4">
                                                <div className="flex items-start justify-between gap-4 rounded-lg border p-4">
                                                    <div className="space-y-1">
                                                        <FormLabel>Enable WS transport</FormLabel>
                                                        <p className="text-sm text-muted-foreground">
                                                            Unencrypted WebSocket binding. Useful when TLS is terminated at a reverse proxy (e.g. NGINX).
                                                        </p>
                                                    </div>
                                                    <Checkbox
                                                        checked={isWsEnabled}
                                                        onCheckedChange={(checked) => setTransportEnabled('ws', checked === true)}
                                                    />
                                                </div>

                                                <div className="grid gap-4 md:grid-cols-2">
                                                    {WS_SETTING_FIELDS.map((field) => (
                                                        <div key={field.name} className="space-y-2">
                                                            <FormLabel required={isWsEnabled}>{field.label}</FormLabel>
                                                            <Input
                                                                value={getSetting(field.name)?.value ?? ''}
                                                                placeholder={field.placeholder}
                                                                disabled={!isWsEnabled}
                                                                onChange={(event) => setWebRtcTextSetting(field.name, event.target.value)}
                                                            />
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>

                                            {/* WSS Transport */}
                                            <div className="space-y-4">
                                                <div className="flex items-start justify-between gap-4 rounded-lg border p-4">
                                                    <div className="space-y-1">
                                                        <FormLabel>Enable WSS transport</FormLabel>
                                                        <p className="text-sm text-muted-foreground">
                                                            Secure WebSocket with TLS, DTLS-SRTP, and related certificate settings.
                                                        </p>
                                                    </div>
                                                    <Checkbox
                                                        checked={isWssEnabled}
                                                        onCheckedChange={(checked) => setTransportEnabled('wss', checked === true)}
                                                    />
                                                </div>

                                                <div className="grid gap-4 md:grid-cols-2">
                                                    {WSS_SETTING_FIELDS.map((field) => (
                                                        <div key={field.name} className="space-y-2">
                                                            <FormLabel required={isWssEnabled && field.name === 'wss-binding'}>{field.label}</FormLabel>
                                                            <Input
                                                                value={getSetting(field.name)?.value ?? ''}
                                                                placeholder={field.placeholder}
                                                                disabled={!isWssEnabled}
                                                                onChange={(event) => setWebRtcTextSetting(field.name, event.target.value)}
                                                            />
                                                        </div>
                                                    ))}
                                                </div>

                                                <div className="grid gap-4 md:grid-cols-2">
                                                    {WSS_BOOLEAN_FIELDS.map((field) => (
                                                        <div key={field.name} className="flex items-center justify-between rounded-lg border p-4">
                                                            <FormLabel>{field.label}</FormLabel>
                                                            <Checkbox
                                                                checked={(getSetting(field.name)?.value ?? 'false') === 'true'}
                                                                disabled={!isWssEnabled}
                                                                onCheckedChange={(checked) => setWebRtcBooleanSetting(field.name, checked === true)}
                                                            />
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>

                                            {/* Shared settings */}
                                            <div className="grid gap-4 md:grid-cols-2">
                                                {SHARED_BOOLEAN_FIELDS.map((field) => (
                                                    <div key={field.name} className="flex items-center justify-between rounded-lg border p-4">
                                                        <FormLabel>{field.label}</FormLabel>
                                                        <Checkbox
                                                            checked={(getSetting(field.name)?.value ?? 'false') === 'true'}
                                                            disabled={!isAnyTransportEnabled}
                                                            onCheckedChange={(checked) => setWebRtcBooleanSetting(field.name, checked === true)}
                                                        />
                                                    </div>
                                                ))}
                                            </div>
                                        </CardContent>
                                    </Card>
                                )}
                            </TabsContent>

                            <TabsContent value="parameters" className="space-y-6">
                                <Card className="max-w-6xl">
                                    <CardHeader>
                                        <CardTitle>Profile parameters</CardTitle>
                                        <CardDescription>
                                            Edit raw FreeSWITCH parameters. Use Ctrl/Cmd+N here to add a row quickly.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="rounded-md border">
                                            <Table>
                                                <TableHeader className="bg-muted/50">
                                                    <TableRow>
                                                        <TableHead className="w-[220px]">Name <span className="text-destructive">*</span></TableHead>
                                                        <TableHead>Value <span className="text-destructive">*</span></TableHead>
                                                        <TableHead className="w-[100px] text-center">Enabled</TableHead>
                                                        <TableHead>Description</TableHead>
                                                        <TableHead className="w-[56px]" />
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {visibleSettings.map((setting) => {
                                                        const index = settings.findIndex((candidate) => candidate === setting);
                                                        const isNameMissing = hasAttemptedSubmit && !setting.name.trim();
                                                        const isValueMissing = hasAttemptedSubmit && !setting.value.trim();

                                                        return (
                                                            <TableRow key={setting.id ?? `${setting.name}-${index}`}>
                                                                <TableCell className="p-2">
                                                                    <Input
                                                                        value={setting.name}
                                                                        className={`h-8 font-mono text-sm ${isNameMissing ? 'border-destructive focus-visible:ring-destructive' : ''}`}
                                                                        placeholder="e.g. sip-port"
                                                                        onChange={(event) => updateSetting(index, 'name', event.target.value)}
                                                                    />
                                                                </TableCell>
                                                                <TableCell className="p-2">
                                                                    <Input
                                                                        value={setting.value}
                                                                        className={`h-8 font-mono text-sm ${isValueMissing ? 'border-destructive focus-visible:ring-destructive' : ''}`}
                                                                        placeholder="5060"
                                                                        onChange={(event) => updateSetting(index, 'value', event.target.value)}
                                                                    />
                                                                </TableCell>
                                                                <TableCell className="p-2 text-center align-middle">
                                                                    <Checkbox
                                                                        checked={setting.is_enabled}
                                                                        onCheckedChange={(checked) => updateSetting(index, 'is_enabled', checked === true)}
                                                                    />
                                                                </TableCell>
                                                                <TableCell className="p-2">
                                                                    <Input
                                                                        value={setting.description ?? ''}
                                                                        className="h-8 text-sm"
                                                                        placeholder="Optional doc"
                                                                        onChange={(event) => updateSetting(index, 'description', event.target.value)}
                                                                    />
                                                                </TableCell>
                                                                <TableCell className="p-2 text-right">
                                                                    <Button type="button" size="icon-sm" variant="ghost" onClick={() => removeSetting(index)}>
                                                                        <Trash2 className="size-4 text-destructive" />
                                                                    </Button>
                                                                </TableCell>
                                                            </TableRow>
                                                        );
                                                    })}
                                                    {visibleSettings.length === 0 && (
                                                        <TableRow>
                                                            <TableCell colSpan={5} className="h-16 text-center text-muted-foreground text-sm">
                                                                No settings added yet.
                                                            </TableCell>
                                                        </TableRow>
                                                    )}
                                                </TableBody>
                                            </Table>
                                            <div className="flex justify-end border-t bg-muted/20 p-2">
                                                <Button type="button" variant="outline" size="sm" onClick={addSetting}>
                                                    <Plus className="mr-1 size-4" /> Add Setting
                                                </Button>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </TabsContent>
                        </Tabs>

                        {formError && (
                            <div className="flex items-start gap-2 rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive max-w-5xl">
                                <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                                <div className="whitespace-pre-wrap">{formError}</div>
                            </div>
                        )}

                        <div className="fixed inset-x-0 bottom-0 z-40 border-t bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80">
                            <div className="mx-auto flex max-w-[calc(100%-2rem)] items-center justify-between gap-4 px-6 py-4 lg:max-w-[calc(100%-18rem)] lg:px-8">
                                <div className="text-sm text-muted-foreground">
                                    {hasUnsavedChanges ? 'Unsaved changes' : 'All changes saved'}
                                    <span className="ml-3 hidden md:inline">Ctrl/Cmd+S to save • Esc to go back</span>
                                </div>
                                <div className="flex flex-wrap justify-end gap-2">
                                    <Button type="button" variant="outline" onClick={() => guardedNavigate('/admin/sip-profiles')}>
                                        Cancel
                                    </Button>
                                    <Button type="button" variant="outline" onClick={() => void handleSaveAndClose()} disabled={mutation.isPending}>
                                        {mutation.isPending ? 'Saving…' : 'Save & Close'}
                                    </Button>
                                    <Button type="submit" disabled={mutation.isPending}>
                                        <Save className="mr-2 size-4" />
                                        {mutation.isPending ? 'Saving…' : isEdit ? 'Save Profile' : 'Create Profile'}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </form>
                </Form>
            )}
        </div>
    );
}
