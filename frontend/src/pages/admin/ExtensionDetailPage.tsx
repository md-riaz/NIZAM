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

    const { data: webRtcConfig, isLoading: isWebRtcConfigLoading, isError: isWebRtcConfigError } = useQuery({
        queryKey: ['extension-webrtc', activeTenant?.id, id],
        queryFn: async () => {
            const response = await api.get(`${tenantApiPrefix}/extensions/${id}/webrtc-config`);
            return response.data.data;
        },
        enabled: Boolean(id) && Boolean(activeTenant),
        retry: false,
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
                                WebRTC configuration
                            </CardTitle>
                            <CardDescription>
                                Read-only connection details derived from the internal SIP profile WebRTC transport settings.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4 text-sm">
                            {isWebRtcConfigLoading ? (
                                <div className="flex justify-center p-4">
                                    <div className="size-5 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                                </div>
                            ) : isWebRtcConfigError || !webRtcConfig ? (
                                <div className="rounded-md border p-4 text-center">
                                    <p className="text-muted-foreground text-sm">
                                        WebRTC is currently disabled on this system.
                                    </p>
                                    <Button variant="link" className="mt-2 h-auto p-0" onClick={() => navigate('/admin/sip-profiles')}>
                                        Enable in SIP Profiles (internal)
                                    </Button>
                                </div>
                            ) : (
                                <>
                                    <div>
                                        <p className="text-muted-foreground">WebSocket URL</p>
                                        <p className="mt-1 break-all font-mono">{webRtcConfig.websocket_url}</p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground">SIP URI</p>
                                        <p className="mt-1 break-all font-mono">{webRtcConfig.sip_uri}</p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground">Username</p>
                                        <p className="mt-1 break-all font-mono">{webRtcConfig.sip_username}</p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground">Password</p>
                                        <p className="mt-1 break-all font-mono">{webRtcConfig.sip_password || 'Hidden'}</p>
                                    </div>
                                    <div className="flex justify-end">
                                        <Button 
                                            variant="outline" 
                                            onClick={() => {
                                                const textToCopy = `WebSocket URL: ${webRtcConfig.websocket_url || 'Unavailable'}\nSIP URI: ${webRtcConfig.sip_uri || 'Unavailable'}\nUsername: ${webRtcConfig.sip_username || extension.extension}\nPassword: ${webRtcConfig.sip_password || 'Hidden'}`;
                                                navigator.clipboard.writeText(textToCopy);
                                                toast.success('Configuration copied to clipboard');
                                            }}
                                        >
                                            <Copy className="mr-2 size-4" />
                                            Copy configuration
                                        </Button>
                                    </div>
                                </>
                            )}
                        </CardContent>
                    </Card>
                </div>
            ) : null}
        </div>
    );
}
