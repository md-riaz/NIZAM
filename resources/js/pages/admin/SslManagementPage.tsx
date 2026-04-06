import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { RefreshCw, ShieldCheck, ShieldX } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useMemo, useRef, useState } from 'react';

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

type SslStatus = 'pending' | 'active' | 'failed' | 'expired';

type SslSettings = {
    email: string;
    is_enabled: boolean;
    domains: string[];
    status: SslStatus;
    last_error: string | null;
    last_renewed_at: string | null;
    expires_at: string | null;
};

type SettingsResponse = {
    status: string;
    message?: string;
    data: SslSettings;
    error?: string;
};

const DEFAULT_SETTINGS: SslSettings = {
    email: '',
    is_enabled: false,
    domains: [],
    status: 'pending',
    last_error: null,
    last_renewed_at: null,
    expires_at: null,
};

function parseDomainInput(value: string): string[] {
    return value
        .split(/[\n,]/g)
        .map((domain) => domain.trim())
        .filter((domain) => domain.length > 0)
        .filter((domain, index, all) => all.indexOf(domain) === index);
}

export default function SslManagementPage() {
    const queryClient = useQueryClient();
    const [formState, setFormState] = useState<SslSettings>(DEFAULT_SETTINGS);
    const [domainInput, setDomainInput] = useState('');
    const [statusMessage, setStatusMessage] = useState<string | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
    const firstErrorRef = useRef<HTMLInputElement>(null);

    const { data, isLoading } = useQuery({
        queryKey: ['admin-ssl'],
        queryFn: async () => {
            const res = await api.get<SettingsResponse>('admin/ssl');
            return res.data.data;
        },
    });

    useEffect(() => {
        if (!data) {
            return;
        }

        setFormState({
            email: data.email ?? '',
            is_enabled: Boolean(data.is_enabled),
            domains: Array.isArray(data.domains) ? data.domains : [],
            status: data.status ?? 'pending',
            last_error: data.last_error ?? null,
            last_renewed_at: data.last_renewed_at ?? null,
            expires_at: data.expires_at ?? null,
        });
        setDomainInput((Array.isArray(data.domains) ? data.domains : []).join('\n'));
    }, [data]);

    const saveMutation = useMutation({
        mutationFn: async () => {
            const payload = {
                email: formState.email.trim(),
                is_enabled: formState.is_enabled,
                domains: parseDomainInput(domainInput),
            };
            const res = await api.put<SettingsResponse>('admin/ssl', payload);
            return res.data;
        },
        onSuccess: async (response) => {
            setStatusMessage(response.message ?? 'SSL settings saved.');
            setErrorMessage(null);
            setFieldErrors({});
            await queryClient.invalidateQueries({ queryKey: ['admin-ssl'] });
        },
        onError: (error: any) => {
            const message = error?.response?.data?.message ?? 'Unable to save SSL settings.';
            setStatusMessage(null);
            setErrorMessage(message);
            const validation = error?.response?.data?.errors;
            setFieldErrors(validation && typeof validation === 'object' ? validation : {});
            window.setTimeout(() => firstErrorRef.current?.focus(), 0);
        },
    });

    const requestCertMutation = useMutation({
        mutationFn: async () => {
            const res = await api.post<SettingsResponse>('admin/ssl/request');
            return res.data;
        },
        onSuccess: async (response) => {
            setStatusMessage(response.message ?? 'Certificate request initiated.');
            setErrorMessage(null);
            await queryClient.invalidateQueries({ queryKey: ['admin-ssl'] });
        },
        onError: (error: any) => {
            const message =
                error?.response?.data?.message ??
                error?.response?.data?.error ??
                'Failed to request certificate.';
            setStatusMessage(null);
            setErrorMessage(message);
        },
    });

    const statusBadge = useMemo(() => {
        switch (formState.status) {
            case 'active':
                return <Badge variant="success">Active</Badge>;
            case 'failed':
                return <Badge variant="destructive">Failed</Badge>;
            case 'expired':
                return <Badge variant="warning">Expired</Badge>;
            default:
                return <Badge variant="secondary">Pending</Badge>;
        }
    }, [formState.status]);

    const domains = parseDomainInput(domainInput);
    const emailError = fieldErrors.email?.[0] ?? null;
    const domainsError = fieldErrors.domains?.[0] ?? fieldErrors['domains.0']?.[0] ?? null;

    const onSubmit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        await saveMutation.mutateAsync();
    };

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div>
                <p className="text-sm text-muted-foreground">Platform Admin &rsaquo; Security</p>
                <h1 className="text-2xl font-bold tracking-tight">SSL Management</h1>
                <p className="text-muted-foreground">
                    Configure certificate contact details, managed domains, and renewal controls.
                </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">Current status</CardTitle>
                    </CardHeader>
                    <CardContent className="flex items-center gap-2">
                        {formState.status === 'active' ? (
                            <ShieldCheck className="size-4 text-emerald-600" />
                        ) : (
                            <ShieldX className="size-4 text-amber-600" />
                        )}
                        {statusBadge}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">Last renewed</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm">
                            {formState.last_renewed_at
                                ? new Date(formState.last_renewed_at).toLocaleString()
                                : 'Never'}
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">Expires</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm">
                            {formState.expires_at
                                ? new Date(formState.expires_at).toLocaleString()
                                : 'Unknown'}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Certificate configuration</CardTitle>
                    <CardDescription>
                        Provide ACME account email and all domains to cover in the certificate request.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {isLoading ? (
                        <div className="flex h-24 items-center justify-center">
                            <div className="size-6 motion-safe:animate-spin rounded-full border-2 border-primary border-t-transparent" />
                        </div>
                    ) : (
                        <form className="space-y-6" onSubmit={onSubmit}>
                            <div className="space-y-2">
                                <Label htmlFor="ssl-email">Notification email *</Label>
                                <Input
                                    ref={emailError ? firstErrorRef : undefined}
                                    id="ssl-email"
                                    type="email"
                                    value={formState.email}
                                    onChange={(event) => setFormState((current) => ({
                                        ...current,
                                        email: event.target.value,
                                    }))}
                                    aria-required={true}
                                    aria-invalid={emailError ? true : undefined}
                                    aria-describedby={emailError ? 'ssl-email-error' : 'ssl-email-help'}
                                />
                                <p id="ssl-email-help" className="text-sm text-muted-foreground">
                                    Certificate expiration alerts and registration notices are sent here.
                                </p>
                                {emailError && (
                                    <p id="ssl-email-error" className="text-sm text-destructive">
                                        {emailError}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="ssl-domains">Domains *</Label>
                                <textarea
                                    id="ssl-domains"
                                    value={domainInput}
                                    onChange={(event) => setDomainInput(event.target.value)}
                                    className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 flex min-h-28 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                                    placeholder="example.com\nwww.example.com"
                                    aria-required={true}
                                    aria-invalid={domainsError ? true : undefined}
                                    aria-describedby={domainsError ? 'ssl-domains-error' : 'ssl-domains-help'}
                                />
                                <p id="ssl-domains-help" className="text-sm text-muted-foreground">
                                    Enter one domain per line or comma-separated. Unique valid domains are used.
                                </p>
                                {domainsError && (
                                    <p id="ssl-domains-error" className="text-sm text-destructive">
                                        {domainsError}
                                    </p>
                                )}
                                <div className="flex flex-wrap gap-1">
                                    {domains.map((domain) => (
                                        <Badge key={domain} variant="secondary">
                                            {domain}
                                        </Badge>
                                    ))}
                                </div>
                            </div>

                            <div className="rounded-md border p-4">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <Label htmlFor="ssl-enabled">Enable automatic SSL management</Label>
                                        <p className="text-sm text-muted-foreground">
                                            Turn on certificate management and renewal scheduling for configured domains.
                                        </p>
                                    </div>
                                    <Checkbox
                                        id="ssl-enabled"
                                        checked={formState.is_enabled}
                                        onCheckedChange={(checked) => setFormState((current) => ({
                                            ...current,
                                            is_enabled: checked === true,
                                        }))}
                                        aria-label="Enable automatic SSL management"
                                    />
                                </div>
                            </div>

                            <div className="flex flex-wrap justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => requestCertMutation.mutate()}
                                    disabled={requestCertMutation.isPending || !domains.length || !formState.email.trim()}
                                >
                                    <RefreshCw className={`size-4 ${requestCertMutation.isPending ? 'motion-safe:animate-spin' : ''}`} />
                                    {requestCertMutation.isPending ? 'Requesting…' : 'Request certificate'}
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={saveMutation.isPending}
                                >
                                    {saveMutation.isPending ? 'Saving…' : 'Save SSL settings'}
                                </Button>
                            </div>
                        </form>
                    )}

                    {statusMessage && (
                        <div className="mt-4 rounded-md border border-emerald-500/40 bg-emerald-500/10 p-3 text-sm text-emerald-900 dark:text-emerald-200">
                            {statusMessage}
                        </div>
                    )}

                    {errorMessage && (
                        <div className="mt-4 rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive">
                            {errorMessage}
                        </div>
                    )}

                    {formState.last_error && (
                        <div className="mt-4 rounded-md border border-amber-500/40 bg-amber-500/10 p-3 text-sm text-amber-900 dark:text-amber-200">
                            <p className="font-semibold">Last certificate error</p>
                            <p>{formState.last_error}</p>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
