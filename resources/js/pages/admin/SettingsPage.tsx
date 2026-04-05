import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { FormEvent } from 'react';
import { useEffect, useMemo, useState } from 'react';

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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import api from '@/lib/api';

interface WebRtcTlsMode {
    key: 'trusted_ca' | 'self_signed';
    label: string;
    enabled: boolean;
    cert_dir: string;
    production_ready: boolean;
    summary: string;
    details: string;
    expected_files: string[];
}

interface WebRtcTlsSettings {
    webrtc_enabled: boolean;
    active_mode: 'trusted_ca' | 'self_signed';
    modes: Record<'trusted_ca' | 'self_signed', WebRtcTlsMode>;
}

interface SettingsResponse {
    status: string;
    data: WebRtcTlsSettings;
}

interface FormState {
    webrtc_enabled: boolean;
    active_mode: 'trusted_ca' | 'self_signed';
    trusted_ca_enabled: boolean;
    trusted_ca_cert_dir: string;
    self_signed_enabled: boolean;
    self_signed_cert_dir: string;
}

export default function SettingsPage() {
    const queryClient = useQueryClient();
    const [formState, setFormState] = useState<FormState | null>(null);
    const [statusMessage, setStatusMessage] = useState<string | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    const { data, isLoading } = useQuery({
        queryKey: ['admin-webrtc-tls-settings'],
        queryFn: async () => {
            const response = await api.get<SettingsResponse>('admin/webrtc-tls');
            return response.data.data;
        },
    });

    useEffect(() => {
        if (!data) {
            return;
        }

        setFormState({
            webrtc_enabled: data.webrtc_enabled,
            active_mode: data.active_mode,
            trusted_ca_enabled: data.modes.trusted_ca.enabled,
            trusted_ca_cert_dir: data.modes.trusted_ca.cert_dir,
            self_signed_enabled: data.modes.self_signed.enabled,
            self_signed_cert_dir: data.modes.self_signed.cert_dir,
        });
    }, [data]);

    const saveMutation = useMutation({
        mutationFn: async (payload: FormState) => {
            const response = await api.put<SettingsResponse & { message: string }>('admin/webrtc-tls', payload);
            return response.data;
        },
        onSuccess: (response) => {
            setStatusMessage(response.message);
            setErrorMessage(null);
            queryClient.invalidateQueries({ queryKey: ['admin-webrtc-tls-settings'] });
        },
        onError: (error: any) => {
            const message = error?.response?.data?.message ?? 'Unable to save WebRTC TLS settings.';
            setErrorMessage(message);
            setStatusMessage(null);
        },
    });

    const modeCards = useMemo(() => {
        if (!data || !formState) {
            return [];
        }

        return [data.modes.trusted_ca, data.modes.self_signed].map((mode) => ({
            ...mode,
            enabled: mode.key === 'trusted_ca' ? formState.trusted_ca_enabled : formState.self_signed_enabled,
            cert_dir: mode.key === 'trusted_ca' ? formState.trusted_ca_cert_dir : formState.self_signed_cert_dir,
            isActive: formState.active_mode === mode.key,
        }));
    }, [data, formState]);

    const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!formState) {
            return;
        }

        await saveMutation.mutateAsync(formState);
    };

    const setModeEnabled = (mode: 'trusted_ca' | 'self_signed', checked: boolean) => {
        setFormState((current) => {
            if (!current) {
                return current;
            }

            const next = {
                ...current,
                trusted_ca_enabled: mode === 'trusted_ca' ? checked : current.trusted_ca_enabled,
                self_signed_enabled: mode === 'self_signed' ? checked : current.self_signed_enabled,
            };

            if (!next.trusted_ca_enabled && next.active_mode === 'trusted_ca' && next.self_signed_enabled) {
                next.active_mode = 'self_signed';
            }

            if (!next.self_signed_enabled && next.active_mode === 'self_signed' && next.trusted_ca_enabled) {
                next.active_mode = 'trusted_ca';
            }

            return next;
        });
    };

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div>
                <p className="text-sm text-muted-foreground">
                    Platform Admin &rsaquo; System
                </p>
                <h1 className="text-2xl font-bold tracking-tight">Platform Settings</h1>
                <p className="text-muted-foreground leading-relaxed">
                    Control WebRTC TLS modes for the shared platform, keep both certificate options visible, and choose which certificate directory FreeSWITCH should use for secure WebSocket traffic on the internal SIP profile.
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>WebRTC and WSS availability</CardTitle>
                    <CardDescription>
                        Browsers require secure WebSocket and HTTPS trust for production WebRTC. Keep both TLS modes available so operators can switch between trusted production certificates and controlled self-signed testing.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {isLoading || !formState ? (
                        <div className="flex h-20 items-center justify-center">
                            <div className="size-6 motion-safe:animate-spin rounded-full border-2 border-primary border-t-transparent" aria-label="Loading platform settings" />
                        </div>
                    ) : (
                        <form className="space-y-6" onSubmit={handleSubmit}>
                            <div className="rounded-lg border p-4">
                                <div className="flex items-start justify-between gap-4">
                                    <div className="space-y-1">
                                        <Label htmlFor="webrtc-enabled">Enable WebRTC platform access</Label>
                                        <p className="text-sm text-muted-foreground">
                                            Turn this off to disable tenant WebRTC config responses and WebSocket transport on the internal SIP profile.
                                        </p>
                                    </div>
                                    <Checkbox
                                        id="webrtc-enabled"
                                        checked={formState.webrtc_enabled}
                                        onCheckedChange={(checked) => setFormState((current) => current ? {
                                            ...current,
                                            webrtc_enabled: checked === true,
                                        } : current)}
                                        aria-label="Enable WebRTC platform access"
                                    />
                                </div>
                            </div>

                            {modeCards.map((mode) => {
                                const modeMarker = mode.key === 'trusted_ca' ? 'CA' : 'DEV';
                                const enableFieldId = `${mode.key}-enabled`;
                                const certFieldId = `${mode.key}-cert-dir`;
                                const radioFieldId = `${mode.key}-active`;

                                return (
                                    <Card key={mode.key} className={mode.isActive ? 'border-primary' : undefined}>
                                        <CardHeader>
                                            <div className="flex flex-wrap items-start justify-between gap-3">
                                                <div className="space-y-2">
                                                    <CardTitle className="flex items-center gap-2 text-lg">
                                                        <span
                                                            aria-hidden="true"
                                                            className="inline-flex size-6 items-center justify-center rounded-full bg-muted text-xs font-semibold text-muted-foreground"
                                                        >
                                                            {modeMarker}
                                                        </span>
                                                        {mode.label}
                                                    </CardTitle>
                                                    <CardDescription>{mode.summary}</CardDescription>
                                                </div>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    {mode.isActive ? <Badge variant="success">Active</Badge> : <Badge variant="secondary">Available</Badge>}
                                                    {mode.production_ready ? <Badge>Production</Badge> : <Badge variant="outline">Testing</Badge>}
                                                </div>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="space-y-4">
                                            <p className="text-sm leading-6 text-muted-foreground">{mode.details}</p>

                                            <div className="grid gap-4 lg:grid-cols-2">
                                                <div className="rounded-lg border p-4">
                                                    <div className="flex items-start justify-between gap-3">
                                                        <div className="space-y-1">
                                                            <Label htmlFor={enableFieldId}>Enable this mode</Label>
                                                            <p className="text-sm text-muted-foreground">
                                                                Keep this mode available to platform administrators.
                                                            </p>
                                                        </div>
                                                        <Checkbox
                                                            id={enableFieldId}
                                                            checked={mode.enabled}
                                                            onCheckedChange={(checked) => setModeEnabled(mode.key, checked === true)}
                                                            aria-label={`Enable ${mode.label}`}
                                                        />
                                                    </div>
                                                </div>

                                                <div className="rounded-lg border p-4">
                                                    <div className="flex items-start gap-3">
                                                        <input
                                                            id={radioFieldId}
                                                            type="radio"
                                                            name="active-mode"
                                                            className="mt-1 size-4 shrink-0 accent-primary"
                                                            checked={mode.isActive}
                                                            disabled={!mode.enabled}
                                                            onChange={() => setFormState((current) => current ? {
                                                                ...current,
                                                                active_mode: mode.key,
                                                            } : current)}
                                                            aria-describedby={`${radioFieldId}-description`}
                                                        />
                                                        <div className="space-y-1">
                                                            <Label htmlFor={radioFieldId}>Make this the active WebRTC TLS mode</Label>
                                                            <p id={`${radioFieldId}-description`} className="text-sm text-muted-foreground">
                                                                Only one mode can be active at a time. The active mode controls `tls-cert-dir` for the XML-curl generated `internal` profile WebSocket transport.
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor={certFieldId}>SSL certificate directory</Label>
                                                <Input
                                                    id={certFieldId}
                                                    value={mode.cert_dir}
                                                    onChange={(event) => setFormState((current) => current ? {
                                                        ...current,
                                                        trusted_ca_cert_dir: mode.key === 'trusted_ca' ? event.target.value : current.trusted_ca_cert_dir,
                                                        self_signed_cert_dir: mode.key === 'self_signed' ? event.target.value : current.self_signed_cert_dir,
                                                    } : current)}
                                                    aria-describedby={`${certFieldId}-description`}
                                                />
                                                <p id={`${certFieldId}-description`} className="text-sm text-muted-foreground">
                                                    Expected files: {mode.expected_files.join(', ')}. This follows the same FreeSWITCH-style certificate directory approach used by FusionPBX profiles.
                                                </p>
                                            </div>

                                            {!mode.production_ready && (
                                                <div className="flex items-start gap-3 rounded-lg border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-900 dark:text-amber-200">
                                                    <span aria-hidden="true" className="mt-0.5 shrink-0 font-semibold">
                                                        Warning
                                                    </span>
                                                    <p>
                                                        Self-signed certificates are not browser-trusted for production. Users may see warnings or blocked media unless every client device explicitly trusts the certificate chain.
                                                    </p>
                                                </div>
                                            )}
                                        </CardContent>
                                    </Card>
                                );
                            })}

                            {statusMessage && (
                                <div className="flex items-start gap-3 rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-900 dark:text-emerald-200">
                                    <span aria-hidden="true" className="mt-0.5 shrink-0 font-semibold">
                                        Saved
                                    </span>
                                    <p>{statusMessage}</p>
                                </div>
                            )}

                            {errorMessage && (
                                <div className="flex items-start gap-3 rounded-lg border border-destructive/40 bg-destructive/10 p-4 text-sm text-destructive">
                                    <span aria-hidden="true" className="mt-0.5 shrink-0 font-semibold">
                                        Error
                                    </span>
                                    <p>{errorMessage}</p>
                                </div>
                            )}

                            <div className="flex justify-end">
                                <Button type="submit" disabled={saveMutation.isPending}>
                                    {saveMutation.isPending ? 'Saving…' : 'Save platform settings'}
                                </Button>
                            </div>
                        </form>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
