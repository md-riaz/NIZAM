import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    AlertCircle,
    CheckCircle,
    Phone,
    Play,
    Radio,
    RefreshCw,
    RotateCw,
    Square,
    Trash2,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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

// ─── Types ───────────────────────────────────────────────────

interface SipProfile {
    name: string;
    type: string;
    uri: string | null;
    status: string;
    calls: number;
}

interface SipGateway {
    name: string;
    profile: string | null;
    status: string;
}

interface SipRegistration {
    reg_user: string;
    realm: string;
    token: string;
    url: string;
    expires: number;
    network_ip: string;
    network_port: string;
    agent: string;
}

// ─── SIP Status Page ─────────────────────────────────────────

export default function SipStatusPage() {
    const queryClient = useQueryClient();
    const [confirmAction, setConfirmAction] = useState<{
        type: string;
        data: any;
    } | null>(null);

    // Fetch profiles
    const {
        data: profiles,
        isLoading: profilesLoading,
        refetch: refetchProfiles,
    } = useQuery({
        queryKey: ['admin-sip-profiles'],
        queryFn: async () => {
            const res = await api.get<{ data: SipProfile[] }>(
                'admin/sip-status/profiles',
            );
            return res.data.data;
        },
        refetchInterval: 10000, // Auto-refresh every 10s
    });

    // Fetch gateways
    const {
        data: gateways,
        isLoading: gatewaysLoading,
        refetch: refetchGateways,
    } = useQuery({
        queryKey: ['admin-sip-gateways'],
        queryFn: async () => {
            const res = await api.get<{ data: SipGateway[] }>(
                'admin/sip-status/gateways',
            );
            return res.data.data;
        },
        refetchInterval: 10000,
    });

    // Fetch registrations
    const {
        data: registrations,
        isLoading: registrationsLoading,
        refetch: refetchRegistrations,
    } = useQuery({
        queryKey: ['admin-sip-registrations'],
        queryFn: async () => {
            const res = await api.get<{ data: SipRegistration[] }>(
                'admin/sip-status/registrations',
            );
            return res.data.data;
        },
        refetchInterval: 10000,
    });

    // Mutations
    const reloadProfileMutation = useMutation({
        mutationFn: async (profile: string) => {
            await api.post('admin/sip-status/profiles/reload', { profile });
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-sip-profiles'] });
        },
    });

    const startProfileMutation = useMutation({
        mutationFn: async (profile: string) => {
            await api.post('admin/sip-status/profiles/start', { profile });
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-sip-profiles'] });
        },
    });

    const stopProfileMutation = useMutation({
        mutationFn: async (profile: string) => {
            await api.post('admin/sip-status/profiles/stop', { profile });
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-sip-profiles'] });
        },
    });

    const killRegistrationMutation = useMutation({
        mutationFn: async ({ user, realm }: { user: string; realm: string }) => {
            await api.post('admin/sip-status/registrations/kill', { user, realm });
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-sip-registrations'] });
        },
    });

    const killGatewayMutation = useMutation({
        mutationFn: async (gateway: string) => {
            await api.post('admin/sip-status/gateways/kill', { gateway });
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['admin-sip-gateways'] });
        },
    });

    const handleAction = (type: string, data: any) => {
        setConfirmAction({ type, data });
    };

    const executeAction = async () => {
        if (!confirmAction) return;

        const { type, data } = confirmAction;

        try {
            switch (type) {
                case 'reload-profile':
                    await reloadProfileMutation.mutateAsync(data);
                    break;
                case 'start-profile':
                    await startProfileMutation.mutateAsync(data);
                    break;
                case 'stop-profile':
                    await stopProfileMutation.mutateAsync(data);
                    break;
                case 'kill-registration':
                    await killRegistrationMutation.mutateAsync(data);
                    break;
                case 'kill-gateway':
                    await killGatewayMutation.mutateAsync(data);
                    break;
            }
        } finally {
            setConfirmAction(null);
        }
    };

    const getStatusBadge = (status: string) => {
        const statusLower = status.toLowerCase();
        
        if (statusLower === 'running' || statusLower.includes('reged')) {
            return <Badge variant="success">{status}</Badge>;
        }
        if (statusLower === 'noreg') {
            return <Badge variant="secondary">{status}</Badge>;
        }
        if (statusLower.includes('fail') || statusLower.includes('error')) {
            return <Badge variant="destructive">{status}</Badge>;
        }
        return <Badge variant="default">{status}</Badge>;
    };

    const getStatusIcon = (status: string) => {
        const statusLower = status.toLowerCase();
        
        if (statusLower === 'running' || statusLower.includes('reged')) {
            return <CheckCircle className="size-4 text-green-600" />;
        }
        if (statusLower.includes('fail') || statusLower.includes('error')) {
            return <XCircle className="size-4 text-red-600" />;
        }
        return <AlertCircle className="size-4 text-yellow-600" />;
    };

    return (
        <div className="space-y-6 p-6 lg:p-8">
            {/* Page Header */}
            <div>
                <p className="text-sm text-muted-foreground">
                    Platform Admin &rsaquo; System
                </p>
                <h1 className="text-2xl font-bold tracking-tight">SIP Status Monitor</h1>
                <p className="text-muted-foreground leading-relaxed">
                    Real-time monitoring of SIP profiles, gateways, and registrations.
                </p>
            </div>

            {/* Tabs */}
            <Tabs defaultValue="profiles" className="space-y-4">
                <TabsList>
                    <TabsTrigger value="profiles">
                        <Radio className="mr-2 size-4" />
                        SIP Profiles
                    </TabsTrigger>
                    <TabsTrigger value="gateways">
                        <Radio className="mr-2 size-4" />
                        Gateways
                    </TabsTrigger>
                    <TabsTrigger value="registrations">
                        <Phone className="mr-2 size-4" />
                        Registrations
                    </TabsTrigger>
                </TabsList>

                {/* SIP Profiles */}
                <TabsContent value="profiles">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>SIP Profiles</CardTitle>
                                    <CardDescription>
                                        FreeSWITCH SIP profiles and their status
                                    </CardDescription>
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => refetchProfiles()}
                                    disabled={profilesLoading}
                                    aria-label="Refresh profiles"
                                    className="cursor-pointer"
                                >
                                    <RefreshCw
                                        className={`size-4 ${profilesLoading ? 'motion-safe:animate-spin' : ''}`}
                                    />
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {profilesLoading ? (
                                <div className="flex h-32 items-center justify-center">
                                    <div className="size-6 motion-safe:animate-spin rounded-full border-2 border-primary border-t-transparent" aria-label="Loading profiles" />
                                </div>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Name</TableHead>
                                            <TableHead>Type</TableHead>
                                            <TableHead>URI</TableHead>
                                            <TableHead>Calls</TableHead>
                                            <TableHead>Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {profiles?.map((profile) => (
                                            <TableRow key={profile.name}>
                                                <TableCell>
                                                    <div className="flex items-center gap-2">
                                                        {getStatusIcon(profile.status)}
                                                        {getStatusBadge(profile.status)}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="font-medium">
                                                    {profile.name}
                                                </TableCell>
                                                <TableCell>{profile.type}</TableCell>
                                                <TableCell className="text-xs text-muted-foreground">
                                                    {profile.uri || '—'}
                                                </TableCell>
                                                <TableCell>{profile.calls}</TableCell>
                                                <TableCell>
                                                    {profile.type === 'profile' && (
                                                        <div className="flex gap-1">
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() =>
                                                                    handleAction(
                                                                        'reload-profile',
                                                                        profile.name,
                                                                    )
                                                                }
                                                                aria-label={`Reload profile ${profile.name}`}
                                                                className="cursor-pointer"
                                                            >
                                                                <RotateCw className="size-4" />
                                                            </Button>
                                                            {profile.status === 'RUNNING' ? (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        handleAction(
                                                                            'stop-profile',
                                                                            profile.name,
                                                                        )
                                                                    }
                                                                    aria-label={`Stop profile ${profile.name}`}
                                                                    className="cursor-pointer"
                                                                >
                                                                    <Square className="size-4" />
                                                                </Button>
                                                            ) : (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        handleAction(
                                                                            'start-profile',
                                                                            profile.name,
                                                                        )
                                                                    }
                                                                    aria-label={`Start profile ${profile.name}`}
                                                                    className="cursor-pointer"
                                                                >
                                                                    <Play className="size-4" />
                                                                </Button>
                                                            )}
                                                        </div>
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                        {profiles?.length === 0 && (
                                            <TableRow>
                                                <TableCell
                                                    colSpan={6}
                                                    className="h-24 text-center text-muted-foreground"
                                                >
                                                    No profiles found
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>

                {/* Gateways */}
                <TabsContent value="gateways">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>SIP Gateways</CardTitle>
                                    <CardDescription>
                                        Outbound SIP trunk gateways
                                    </CardDescription>
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => refetchGateways()}
                                    disabled={gatewaysLoading}
                                    aria-label="Refresh gateways"
                                    className="cursor-pointer"
                                >
                                    <RefreshCw
                                        className={`size-4 ${gatewaysLoading ? 'motion-safe:animate-spin' : ''}`}
                                    />
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {gatewaysLoading ? (
                                <div className="flex h-32 items-center justify-center">
                                    <div className="size-6 motion-safe:animate-spin rounded-full border-2 border-primary border-t-transparent" aria-label="Loading gateways" />
                                </div>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Name</TableHead>
                                            <TableHead>Profile</TableHead>
                                            <TableHead>Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {gateways?.map((gateway) => (
                                            <TableRow key={gateway.name}>
                                                <TableCell>
                                                    <div className="flex items-center gap-2">
                                                        {getStatusIcon(gateway.status)}
                                                        {getStatusBadge(gateway.status)}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="font-medium">
                                                    {gateway.name}
                                                </TableCell>
                                                <TableCell>{gateway.profile || '—'}</TableCell>
                                                <TableCell>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            handleAction(
                                                                'kill-gateway',
                                                                gateway.name,
                                                            )
                                                        }
                                                        aria-label={`Kill gateway ${gateway.name}`}
                                                        className="cursor-pointer"
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                        {gateways?.length === 0 && (
                                            <TableRow>
                                                <TableCell
                                                    colSpan={4}
                                                    className="h-24 text-center text-muted-foreground"
                                                >
                                                    No gateways found
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>

                {/* Registrations */}
                <TabsContent value="registrations">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Active Registrations</CardTitle>
                                    <CardDescription>
                                        Currently registered SIP endpoints
                                    </CardDescription>
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => refetchRegistrations()}
                                    disabled={registrationsLoading}
                                    aria-label="Refresh registrations"
                                    className="cursor-pointer"
                                >
                                    <RefreshCw
                                        className={`size-4 ${registrationsLoading ? 'motion-safe:animate-spin' : ''}`}
                                    />
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {registrationsLoading ? (
                                <div className="flex h-32 items-center justify-center">
                                    <div className="size-6 motion-safe:animate-spin rounded-full border-2 border-primary border-t-transparent" aria-label="Loading registrations" />
                                </div>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>User</TableHead>
                                            <TableHead>Realm</TableHead>
                                            <TableHead>Network</TableHead>
                                            <TableHead>User Agent</TableHead>
                                            <TableHead>Expires</TableHead>
                                            <TableHead>Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {registrations?.map((reg, idx) => (
                                            <TableRow key={idx}>
                                                <TableCell className="font-medium">
                                                    {reg.reg_user}
                                                </TableCell>
                                                <TableCell>{reg.realm}</TableCell>
                                                <TableCell className="text-xs">
                                                    {reg.network_ip}:{reg.network_port}
                                                </TableCell>
                                                <TableCell className="text-xs text-muted-foreground">
                                                    {reg.agent}
                                                </TableCell>
                                                <TableCell>{reg.expires}s</TableCell>
                                                <TableCell>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            handleAction('kill-registration', {
                                                                user: reg.reg_user,
                                                                realm: reg.realm,
                                                            })
                                                        }
                                                        aria-label={`Kill registration for ${reg.reg_user}@${reg.realm}`}
                                                        className="cursor-pointer"
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                        {registrations?.length === 0 && (
                                            <TableRow>
                                                <TableCell
                                                    colSpan={6}
                                                    className="h-24 text-center text-muted-foreground"
                                                >
                                                    No active registrations
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>

            {/* Confirmation Dialog */}
            <AlertDialog
                open={!!confirmAction}
                onOpenChange={() => setConfirmAction(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Confirm Action</AlertDialogTitle>
                        <AlertDialogDescription>
                            {confirmAction?.type === 'reload-profile' &&
                                `Reload profile "${confirmAction.data}"?`}
                            {confirmAction?.type === 'start-profile' &&
                                `Start profile "${confirmAction.data}"?`}
                            {confirmAction?.type === 'stop-profile' &&
                                `Stop profile "${confirmAction.data}"? This will disconnect all calls on this profile.`}
                            {confirmAction?.type === 'kill-registration' &&
                                `Kill registration for ${confirmAction.data.user}@${confirmAction.data.realm}?`}
                            {confirmAction?.type === 'kill-gateway' &&
                                `Kill gateway "${confirmAction.data}"?`}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={executeAction}>
                            Confirm
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
