import { useQuery } from '@tanstack/react-query';
import {
    Activity,
    AlertTriangle,
    ArrowUpRight,
    Building2,
    HardDrive,
    Phone,
    PhoneCall,
    Radio,
    TrendingUp,
} from 'lucide-react';

import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useAuth } from '@/context/AuthContext';
import { useOrganization } from '@/context/OrganizationContext';
import api from '@/lib/api';
import { cn } from '@/lib/utils';

// ─── Types ───────────────────────────────────────────────────

interface StatCardProps {
    title: string;
    value: string | number;
    description?: string;
    icon: React.ComponentType<{ className?: string }>;
    trend?: number;
    className?: string;
}

interface DashboardOrganizationSummary {
    id: number;
    name: string;
    domain: string;
    status: 'trial' | 'active' | 'suspended' | 'terminated' | string;
    extensions_count: number;
    active_extensions_count: number;
    dids_count: number;
    ring_groups_count: number;
    recordings_total_size: number;
    cdrs_today: number;
    webhooks_count: number;
}

interface AdminDashboardResponse {
    data: {
        total_organizations: number;
        organizations_by_status: {
            trial: number;
            active: number;
            suspended: number;
            terminated: number;
        };
        total_extensions: number;
        total_active_extensions: number;
        total_dids: number;
        total_recordings_size: number;
        organizations: DashboardOrganizationSummary[];
    };
}

interface OrganizationDashboardStatsResponse {
    data: {
        extensions_count: number;
        active_extensions_count: number;
        dids_count: number;
        ring_groups_count: number;
        ivrs_count: number;
        cdrs_total: number;
        cdrs_today: number;
        recordings_count: number;
        recordings_total_size: number;
        device_profiles_count: number;
        webhooks_count: number;
        call_routing_policies_count: number;
        flows_count: number;
    };
}

interface HealthCheckStatus {
    status: string;
}

interface SipRuntimeHealth extends HealthCheckStatus {
    message?: string;
    fatal_reason?: string | null;
    expected_profiles?: string[];
    loaded_profiles?: string[];
    missing_profiles?: string[];
    recommended_action?: string | null;
}

interface HealthResponse {
    status: string;
    checks?: {
        esl?: HealthCheckStatus & { connected?: boolean };
        sip_runtime?: SipRuntimeHealth;
    };
}

// ─── Stat Card Component ─────────────────────────────────────

function StatCard({
    title,
    value,
    description,
    icon: Icon,
    trend,
    className,
}: StatCardProps) {
    return (
        <Card className={cn('relative overflow-hidden', className)}>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">
                    {title}
                </CardTitle>
                <div className="rounded-lg bg-primary/10 p-2">
                    <Icon className="size-4 text-primary" />
                </div>
            </CardHeader>
            <CardContent>
                <div className="text-3xl font-bold tracking-tight">{value}</div>
                {(description || trend !== undefined) && (
                    <div className="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
                        {trend !== undefined && trend > 0 && (
                            <span className="flex items-center text-emerald-600">
                                <TrendingUp className="mr-0.5 size-3" />
                                +{trend}%
                            </span>
                        )}
                        {description && <span>{description}</span>}
                    </div>
                )}
            </CardContent>
            {/* Ambient corner glow */}
            <div className="pointer-events-none absolute -right-4 -top-4 size-24 rounded-full bg-primary/5 blur-2xl" />
        </Card>
    );
}

// ─── Dashboard Page ──────────────────────────────────────────

export default function DashboardPage() {
    const { user } = useAuth();
    const { activeOrganization, organizationApiPrefix } = useOrganization();
    const isSuperadmin = user?.role === 'superadmin';

    const { data: dashboard, isLoading: dashboardLoading } = useQuery({
        queryKey: ['admin-dashboard', user?.role, activeOrganization?.id],
        queryFn: async () => {
            if (isSuperadmin || !activeOrganization) {
                const res = await api.get<AdminDashboardResponse>('admin/dashboard');
                return res.data.data;
            }

            const res = await api.get<OrganizationDashboardStatsResponse>(`${organizationApiPrefix}/stats`);
            const stats = res.data.data;

            return {
                total_organizations: 1,
                organizations_by_status: {
                    trial: 0,
                    active: 1,
                    suspended: 0,
                    terminated: 0,
                },
                total_extensions: stats.extensions_count,
                total_active_extensions: stats.active_extensions_count,
                total_dids: stats.dids_count,
                total_recordings_size: stats.recordings_total_size,
                organizations: activeOrganization ? [{
                    id: Number(activeOrganization.id),
                    name: activeOrganization.name,
                    domain: activeOrganization.domain,
                    status: 'active',
                    extensions_count: stats.extensions_count,
                    active_extensions_count: stats.active_extensions_count,
                    dids_count: stats.dids_count,
                    ring_groups_count: stats.ring_groups_count,
                    recordings_total_size: stats.recordings_total_size,
                    cdrs_today: stats.cdrs_today,
                    webhooks_count: stats.webhooks_count,
                }] : [],
            };
        },
        enabled: isSuperadmin || !!activeOrganization,
    });

    const { data: health } = useQuery({
        queryKey: ['health'],
        queryFn: async () => {
            const res = await api.get<HealthResponse>('/health');
            return res.data;
        },
        refetchInterval: 30_000,
        enabled: isSuperadmin,
    });

    const eslStatus = health?.checks?.esl?.status;
    const sipRuntime = health?.checks?.sip_runtime;
    const telephonyCardValue =
        sipRuntime?.status === 'fatal'
            ? 'Fatal'
            : sipRuntime?.status === 'degraded'
              ? 'Degraded'
              : eslStatus === 'ok'
                ? 'Healthy'
                : 'Checking…';
    const telephonyCardDescription = sipRuntime?.message ?? 'FreeSWITCH runtime health';
    const telephonyCardClassName =
        sipRuntime?.status === 'fatal'
            ? 'border-red-500/50'
            : sipRuntime?.status === 'degraded'
              ? 'border-amber-300/40'
              : '';

    const formatBytes = (bytes: number) => {
        if (!bytes) return '0 B';

        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        let size = bytes;
        let unitIndex = 0;

        while (size >= 1024 && unitIndex < units.length - 1) {
            size /= 1024;
            unitIndex += 1;
        }

        return `${size.toFixed(size >= 10 || unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
    };

    const organizationStatus = dashboard?.organizations_by_status;
    const organizations = dashboard?.organizations ?? [];

    return (
        <div className="space-y-8 p-6 lg:p-8">
            <div>
                <h1 className="text-2xl font-bold tracking-tight">Dashboard</h1>
                <p className="text-muted-foreground">
                    {isSuperadmin
                        ? `Welcome back, ${user?.name}. Here's what is happening across the platform.`
                        : `Welcome back, ${user?.name}. Here's what is happening in ${activeOrganization?.name ?? 'your organization'}.`}
                </p>
            </div>

            {isSuperadmin && sipRuntime?.status && sipRuntime.status !== 'healthy' && (
                <Card className={cn(
                    'border-l-4',
                    sipRuntime.status === 'fatal' ? 'border-l-red-600 border-red-500/50' : 'border-l-amber-500 border-amber-300/40',
                )}>
                    <CardHeader className="pb-3">
                        <div className="flex items-start gap-3">
                            <div className={cn(
                                'rounded-lg p-2',
                                sipRuntime.status === 'fatal' ? 'bg-red-500/10 text-red-600' : 'bg-amber-500/10 text-amber-600',
                            )}>
                                <AlertTriangle className="size-5" />
                            </div>
                            <div className="space-y-1">
                                <CardTitle>Telephony runtime {sipRuntime.status}</CardTitle>
                                <CardDescription>
                                    {sipRuntime.message}
                                </CardDescription>
                                {sipRuntime.recommended_action && (
                                    <p className="text-sm text-muted-foreground">
                                        Recommended action: {sipRuntime.recommended_action}
                                    </p>
                                )}
                                {sipRuntime.expected_profiles && sipRuntime.loaded_profiles && (
                                    <p className="text-xs text-muted-foreground">
                                        Expected: {sipRuntime.expected_profiles.join(', ') || 'none'} · Loaded: {sipRuntime.loaded_profiles.join(', ') || 'none'}
                                    </p>
                                )}
                            </div>
                        </div>
                    </CardHeader>
                </Card>
            )}

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    title={isSuperadmin ? 'Total Organizations' : 'Organization'}
                    value={dashboardLoading ? '…' : (isSuperadmin ? (dashboard?.total_organizations ?? 0) : (activeOrganization?.name ?? '—'))}
                    description={
                        isSuperadmin
                            ? (organizationStatus
                                ? `${organizationStatus.active} active · ${organizationStatus.suspended} suspended`
                                : 'Platform organizations')
                            : (activeOrganization?.domain ?? 'Current organization')
                    }
                    icon={Building2}
                />
                <StatCard
                    title="Extensions"
                    value={dashboardLoading ? '…' : (dashboard?.total_extensions ?? 0)}
                    description={
                        dashboard
                            ? `${dashboard.total_active_extensions} active registrations`
                            : 'Across all organizations'
                    }
                    icon={Phone}
                />
                <StatCard
                    title="Numbers"
                    value={dashboardLoading ? '…' : (dashboard?.total_dids ?? 0)}
                    description="Inbound numbers configured"
                    icon={PhoneCall}
                />
                <StatCard
                    title={isSuperadmin ? 'Telephony Health' : 'Routing Flows'}
                    value={isSuperadmin ? telephonyCardValue : (dashboardLoading ? '…' : organizations[0]?.ring_groups_count ?? 0)}
                    description={isSuperadmin ? telephonyCardDescription : 'Ring groups available in this organization'}
                    icon={isSuperadmin ? Radio : Radio}
                    className={isSuperadmin ? telephonyCardClassName : ''}
                />
            </div>

            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    title="Recordings Storage"
                    value={dashboardLoading ? '…' : formatBytes(dashboard?.total_recordings_size ?? 0)}
                    description="Total media footprint"
                    icon={HardDrive}
                />
                <StatCard
                    title={isSuperadmin ? 'Active Organizations' : 'Today\'s Calls'}
                    value={dashboardLoading ? '…' : (isSuperadmin ? (organizationStatus?.active ?? 0) : (organizations[0]?.cdrs_today ?? 0))}
                    description={isSuperadmin ? 'Currently serving traffic' : 'Calls started today'}
                    icon={Activity}
                />
                <StatCard
                    title={isSuperadmin ? 'Trial Organizations' : 'Webhooks'}
                    value={dashboardLoading ? '…' : (isSuperadmin ? (organizationStatus?.trial ?? 0) : (organizations[0]?.webhooks_count ?? 0))}
                    description={isSuperadmin ? 'Evaluation environments' : 'Configured integrations'}
                    icon={TrendingUp}
                />
                <StatCard
                    title={isSuperadmin ? 'Terminated Organizations' : 'Active Extensions'}
                    value={dashboardLoading ? '…' : (isSuperadmin ? (organizationStatus?.terminated ?? 0) : (organizations[0]?.active_extensions_count ?? 0))}
                    description={isSuperadmin ? 'Archived subscriptions' : 'Registered or enabled extensions'}
                    icon={Building2}
                />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>{isSuperadmin ? 'Organization Activity Snapshot' : 'Organization Activity Snapshot'}</CardTitle>
                    <CardDescription>
                        {isSuperadmin
                            ? 'Operational summary for recently active organizations.'
                            : 'Operational summary for your organization.'}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {dashboardLoading ? (
                        <div className="flex h-32 items-center justify-center">
                            <div
                                className="size-6 motion-safe:animate-spin rounded-full border-2 border-primary border-t-transparent"
                                aria-label="Loading dashboard snapshot"
                            />
                        </div>
                    ) : organizations.length > 0 ? (
                        <div className="space-y-3">
                            {organizations.slice(0, 8).map((organization) => (
                                <div
                                    key={organization.id}
                                    className="group grid gap-3 rounded-lg border px-4 py-3 transition-colors hover:bg-accent/50 sm:grid-cols-[minmax(0,1.4fr)_repeat(4,minmax(0,1fr))_auto] sm:items-center"
                                >
                                    <div className="min-w-0">
                                        <p className="font-medium">{organization.name}</p>
                                        <p className="text-sm text-muted-foreground">{organization.domain}</p>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        Ext: <span className="font-semibold text-foreground">{organization.extensions_count}</span>
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Numbers: <span className="font-semibold text-foreground">{organization.dids_count}</span>
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Ring: <span className="font-semibold text-foreground">{organization.ring_groups_count}</span>
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Today CDR: <span className="font-semibold text-foreground">{organization.cdrs_today}</span>
                                    </p>
                                    <div className="flex items-center justify-between gap-3">
                                        <span className="text-xs text-muted-foreground">{organization.status}</span>
                                        <ArrowUpRight className="size-4 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="py-8 text-center text-sm text-muted-foreground">
                            No organization telemetry available yet.
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
