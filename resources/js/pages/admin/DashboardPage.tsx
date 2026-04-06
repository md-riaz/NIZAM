import { useQuery } from '@tanstack/react-query';
import {
    Activity,
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

interface DashboardTenantSummary {
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
        total_tenants: number;
        tenants_by_status: {
            trial: number;
            active: number;
            suspended: number;
            terminated: number;
        };
        total_extensions: number;
        total_active_extensions: number;
        total_dids: number;
        total_recordings_size: number;
        tenants: DashboardTenantSummary[];
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

    const { data: dashboard, isLoading: dashboardLoading } = useQuery({
        queryKey: ['admin-dashboard'],
        queryFn: async () => {
            const res = await api.get<AdminDashboardResponse>('admin/dashboard');
            return res.data.data;
        },
    });

    const { data: health } = useQuery({
        queryKey: ['health'],
        queryFn: async () => {
            const res = await api.get('/health');
            return res.data as Record<string, unknown>;
        },
        refetchInterval: 30_000,
    });

    const eslStatus = (health?.checks as Record<string, { status: string }> | undefined)?.esl?.status;

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

    const tenantStatus = dashboard?.tenants_by_status;
    const tenants = dashboard?.tenants ?? [];

    return (
        <div className="space-y-8 p-6 lg:p-8">
            <div>
                <h1 className="text-2xl font-bold tracking-tight">Dashboard</h1>
                <p className="text-muted-foreground">
                    Welcome back, {user?.name}. Here's what is happening across the platform.
                </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    title="Total Tenants"
                    value={dashboardLoading ? '…' : (dashboard?.total_tenants ?? 0)}
                    description={
                        tenantStatus
                            ? `${tenantStatus.active} active · ${tenantStatus.suspended} suspended`
                            : 'Platform organizations'
                    }
                    icon={Building2}
                />
                <StatCard
                    title="Extensions"
                    value={dashboardLoading ? '…' : (dashboard?.total_extensions ?? 0)}
                    description={
                        dashboard
                            ? `${dashboard.total_active_extensions} active registrations`
                            : 'Across all tenants'
                    }
                    icon={Phone}
                />
                <StatCard
                    title="DIDs"
                    value={dashboardLoading ? '…' : (dashboard?.total_dids ?? 0)}
                    description="Inbound numbers configured"
                    icon={PhoneCall}
                />
                <StatCard
                    title="ESL Status"
                    value={eslStatus === 'ok' ? 'Connected' : 'Checking…'}
                    description="FreeSWITCH link"
                    icon={Radio}
                    className={eslStatus === 'ok' ? '' : 'border-amber-300/40'}
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
                    title="Active Tenants"
                    value={dashboardLoading ? '…' : (tenantStatus?.active ?? 0)}
                    description="Currently serving traffic"
                    icon={Activity}
                />
                <StatCard
                    title="Trial Tenants"
                    value={dashboardLoading ? '…' : (tenantStatus?.trial ?? 0)}
                    description="Evaluation environments"
                    icon={TrendingUp}
                />
                <StatCard
                    title="Terminated Tenants"
                    value={dashboardLoading ? '…' : (tenantStatus?.terminated ?? 0)}
                    description="Archived subscriptions"
                    icon={Building2}
                />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Tenant Activity Snapshot</CardTitle>
                    <CardDescription>
                        Operational summary for recently active tenants.
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
                    ) : tenants.length > 0 ? (
                        <div className="space-y-3">
                            {tenants.slice(0, 8).map((tenant) => (
                                <div
                                    key={tenant.id}
                                    className="group grid gap-3 rounded-lg border px-4 py-3 transition-colors hover:bg-accent/50 sm:grid-cols-[minmax(0,1.4fr)_repeat(4,minmax(0,1fr))_auto] sm:items-center"
                                >
                                    <div className="min-w-0">
                                        <p className="font-medium">{tenant.name}</p>
                                        <p className="text-sm text-muted-foreground">{tenant.domain}</p>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        Ext: <span className="font-semibold text-foreground">{tenant.extensions_count}</span>
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        DIDs: <span className="font-semibold text-foreground">{tenant.dids_count}</span>
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Ring: <span className="font-semibold text-foreground">{tenant.ring_groups_count}</span>
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Today CDR: <span className="font-semibold text-foreground">{tenant.cdrs_today}</span>
                                    </p>
                                    <div className="flex items-center justify-between gap-3">
                                        <span className="text-xs text-muted-foreground">{tenant.status}</span>
                                        <ArrowUpRight className="size-4 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="py-8 text-center text-sm text-muted-foreground">
                            No tenant telemetry available yet.
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
