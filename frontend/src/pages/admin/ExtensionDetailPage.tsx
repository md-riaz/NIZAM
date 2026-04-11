import { useQuery } from '@tanstack/react-query';
import { ArrowLeft, Copy, Phone, ShieldCheck } from 'lucide-react';
import { useNavigate, useParams } from 'react-router-dom';
import { toast } from 'sonner';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTenant } from '@/context/TenantContext';
import api from '@/lib/api';

export default function ExtensionDetailPage() {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const { activeTenant, tenantApiPrefix } = useTenant();

    const { data: extension, isLoading } = useQuery({
        queryKey: ['extension', activeTenant?.id, id],
        queryFn: async () => {
            const response = await api.get(`${tenantApiPrefix}/extensions/${id}`);
            return response.data.data;
        },
        enabled: Boolean(id) && Boolean(activeTenant),
    });

    const { data: sipConfig, isLoading: isSipConfigLoading } = useQuery({
        queryKey: ['extension-sip-config', activeTenant?.id, id],
        queryFn: async () => {
            const response = await api.get(`${tenantApiPrefix}/extensions/${id}/sip-config`);
            return response.data.data;
        },
        enabled: Boolean(id) && Boolean(activeTenant),
    });

    if (!activeTenant) {
        return (
            <div className="flex h-64 items-center justify-center text-muted-foreground">
                Select a tenant to view extension details.
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
                    <p className="text-sm text-muted-foreground">{activeTenant.name} › Phone System</p>
                    <h1 className="text-2xl font-bold tracking-tight">Extension Details</h1>
                </div>
            </div>

            {isLoading ? (
                <div className="flex h-32 items-center justify-center">
                    <div className="size-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                </div>
            ) : extension ? (
                <div className="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Phone className="size-5 text-primary" />
                                {extension.extension}
                            </CardTitle>
                            <CardDescription>
                                {extension.directory_first_name || extension.directory_last_name
                                    ? `${extension.directory_first_name ?? ''} ${extension.directory_last_name ?? ''}`.trim()
                                    : 'No directory name configured.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <div>
                                <p className="text-sm text-muted-foreground">Status</p>
                                <div className="mt-1">
                                    <Badge variant={extension.is_active ? 'success' : 'secondary'}>
                                        {extension.is_active ? 'Active' : 'Inactive'}
                                    </Badge>
                                </div>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Voicemail</p>
                                <p className="mt-1 text-sm font-medium">
                                    {extension.voicemail_enabled ? 'Enabled' : 'Disabled'}
                                </p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Caller ID name</p>
                                <p className="mt-1 text-sm font-medium">{extension.effective_caller_id_name ?? '—'}</p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Caller ID number</p>
                                <p className="mt-1 text-sm font-medium">{extension.effective_caller_id_number ?? '—'}</p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Outbound name</p>
                                <p className="mt-1 text-sm font-medium">{extension.outbound_caller_id_name ?? '—'}</p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Outbound number</p>
                                <p className="mt-1 text-sm font-medium">{extension.outbound_caller_id_number ?? '—'}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <ShieldCheck className="size-5 text-primary" />
                                SIP Credentials
                            </CardTitle>
                            <CardDescription>
                                Connection details for registering SIP clients (softphones, IP phones, etc.)
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4 text-sm">
                            {isSipConfigLoading ? (
                                <div className="flex justify-center p-4">
                                    <div className="size-5 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                                </div>
                            ) : sipConfig ? (
                                <>
                                    <div>
                                        <p className="text-muted-foreground">SIP Server</p>
                                        <p className="mt-1 break-all font-mono">{sipConfig.sip_server}</p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground">Domain / Realm</p>
                                        <p className="mt-1 break-all font-mono">{sipConfig.sip_domain}</p>
                                    </div>
                                    {sipConfig.sip_tls_server && (
                                        <div>
                                            <p className="text-muted-foreground">TLS Server</p>
                                            <p className="mt-1 break-all font-mono">{sipConfig.sip_tls_server}</p>
                                        </div>
                                    )}
                                    <div>
                                        <p className="text-muted-foreground">Transport</p>
                                        <p className="mt-1 break-all font-mono">{sipConfig.sip_transport}</p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground">Username</p>
                                        <p className="mt-1 break-all font-mono">{sipConfig.sip_username}</p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground">Password</p>
                                        <p className="mt-1 break-all font-mono">{sipConfig.sip_password || 'Hidden'}</p>
                                    </div>
                                    <div className="flex items-center gap-2 mt-4 pt-4 border-t">
                                        <p className="text-muted-foreground">WebRTC support:</p>
                                        <Badge variant={sipConfig.enabled ? 'success' : 'secondary'}>
                                            {sipConfig.enabled ? 'Enabled' : 'Disabled'}
                                        </Badge>
                                        {sipConfig.enabled && sipConfig.websocket_url && (
                                            <span className="break-all font-mono text-xs text-muted-foreground ml-2">{sipConfig.websocket_url}</span>
                                        )}
                                    </div>
                                    <div className="flex justify-end pt-2">
                                        <Button
                                            variant="outline"
                                            onClick={() => {
                                                const lines = [
                                                    `SIP Server: ${sipConfig.sip_server || ''}`,
                                                    `Domain / Realm: ${sipConfig.sip_domain || ''}`,
                                                    `Transport: ${sipConfig.sip_transport || 'UDP/TCP'}`,
                                                    `Username: ${sipConfig.sip_username || extension.extension}`,
                                                    `Password: ${sipConfig.sip_password || 'Hidden'}`,
                                                ];
                                                if (sipConfig.sip_tls_server) {
                                                    lines.push(`TLS Server: ${sipConfig.sip_tls_server}`);
                                                }
                                                if (sipConfig.enabled && sipConfig.websocket_url) {
                                                    lines.push(`WebSocket URL: ${sipConfig.websocket_url}`);
                                                }
                                                navigator.clipboard.writeText(lines.join('\n'));
                                                toast.success('SIP credentials copied to clipboard');
                                            }}
                                        >
                                            <Copy className="mr-2 size-4" />
                                            Copy credentials
                                        </Button>
                                    </div>
                                </>
                            ) : (
                                <div className="rounded-md border p-4 text-center">
                                    <p className="text-muted-foreground text-sm">
                                        Unable to load SIP credentials.
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            ) : null}
        </div>
    );
}
