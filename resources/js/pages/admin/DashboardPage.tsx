import { useQuery } from '@tanstack/react-query';
import {
    Building2,
    Phone,
    PhoneCall,
    Radio,
    TrendingUp,
    ArrowUpRight,
} from 'lucide-react';

import api from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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

interface TenantSummary {
    id: number;
    name: string;
    domain: string;
    extension_count: number;
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

    const { data: tenants } = useQuery({
        queryKey: ['tenants'],
        queryFn: async () => {
            const res = await api.get<{ data: TenantSummary[] }>('tenants');
            return res.data.data;
        },
    });

    const { data: health } = useQuery({
        queryKey: ['health'],
        queryFn: async () => {
            const res = await api.get('/health');
            return res.data as Record<string, unknown>;
        },
        refetchInterval: 30_000, // Poll every 30s
    });

    const tenantCount = tenants?.length ?? 0;
    const eslStatus = (health?.checks as Record<string, { status: string }> | undefined)?.esl?.status;

    return (
        <div className="space-y-8 p-6 lg:p-8">
            {/* Page Header */}
            <div>
                <h1 className="text-2xl font-bold tracking-tight">Dashboard</h1>
                <p className="text-muted-foreground">
                    Welcome back, {user?.name}. Here's what's happening.
                </p>
            </div>

            {/* Stats Grid */}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    title="Total Tenants"
                    value={tenantCount}
                    description="Active organizations"
                    icon={Building2}
                />
                <StatCard
                    title="Extensions"
                    value="—"
                    description="Across all tenants"
                    icon={Phone}
                />
                <StatCard
                    title="Active Calls"
                    value="—"
                    description="Real-time"
                    icon={PhoneCall}
                />
                <StatCard
                    title="ESL Status"
                    value={eslStatus === 'ok' ? 'Connected' : 'Checking…'}
                    description="FreeSWITCH link"
                    icon={Radio}
                />
            </div>

            {/* Recent Tenants */}
            <Card>
                <CardHeader>
                    <CardTitle>Recent Tenants</CardTitle>
                    <CardDescription>
                        Organizations provisioned on this platform
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {tenants && tenants.length > 0 ? (
                        <div className="space-y-3">
                            {tenants.slice(0, 5).map((tenant) => (
                                <div
                                    key={tenant.id}
                                    className="group flex items-center justify-between rounded-lg border px-4 py-3 transition-colors hover:bg-accent/50"
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="font-medium">{tenant.name}</p>
                                        <p className="text-sm text-muted-foreground">
                                            {tenant.domain}
                                        </p>
                                    </div>
                                    <ArrowUpRight className="size-4 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="py-8 text-center text-sm text-muted-foreground">
                            No tenants provisioned yet.
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
