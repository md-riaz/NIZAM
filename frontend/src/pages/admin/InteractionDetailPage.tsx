import { useQuery } from '@tanstack/react-query';
import {
    AlertTriangle,
    ArrowLeft,
    Bell,
    CheckCircle2,
    Clock3,
    Phone,
    Route,
    Smartphone,
    Timer,
    Waypoints,
} from 'lucide-react';
import { useNavigate, useParams } from 'react-router-dom';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { useTenant } from '@/context/TenantContext';
import api from '@/lib/api';
import type {
    InteractionDeliveryAttempt,
    InteractionOverview,
    InteractionPushNotificationLog,
    InteractionTimelineEvent,
} from '@/types/models';

function formatDateTime(value: string | null | undefined): string {
    if (!value) return '—';

    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}

function formatTime(value: string | null | undefined): string {
    if (!value) return '—';

    const date = new Date(value);
    return Number.isNaN(date.getTime())
        ? value
        : date.toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });
}

function formatDurationMs(value: number | null | undefined): string {
    if (!value || value <= 0) return '0 ms';
    if (value < 1000) return `${value} ms`;

    const totalSeconds = Math.floor(value / 1000);
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;

    if (minutes === 0) return `${seconds}s`;

    return `${minutes}m ${String(seconds).padStart(2, '0')}s`;
}

function formatSeconds(value: number | null | undefined): string {
    if (!value || value <= 0) return '0:00';

    const minutes = Math.floor(value / 60);
    const seconds = value % 60;

    return `${minutes}:${String(seconds).padStart(2, '0')}`;
}

function labelFromValue(value: string | null | undefined): string {
    if (!value) return 'Unknown';

    return value
        .replace(/[._]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

function statusBadgeVariant(value: string | null | undefined): 'success' | 'warning' | 'destructive' | 'secondary' {
    const normalized = value?.toLowerCase();

    if (!normalized) return 'secondary';
    if (['bridged', 'completed', 'won', 'sent', 'answered', 'success'].includes(normalized)) return 'success';
    if (['failed', 'error', 'missed', 'hangup'].includes(normalized)) return 'destructive';
    if (['pending', 'ringing', 'queued', 'processing'].includes(normalized)) return 'warning';

    return 'secondary';
}

function endpointLabel(endpoint: { type?: string | null; platform?: string | null } | null | undefined): string {
    if (!endpoint) return 'Unknown endpoint';

    return [endpoint.platform, endpoint.type]
        .filter(Boolean)
        .map((part) => labelFromValue(part ?? undefined))
        .join(' ')
        || 'Unknown endpoint';
}

function timelineTone(type: string): {
    icon: typeof Phone;
    iconClassName: string;
    badgeVariant: 'success' | 'warning' | 'destructive' | 'secondary' | 'outline';
} {
    if (type.startsWith('delivery.') && type.endsWith('.won')) {
        return {
            icon: CheckCircle2,
            iconClassName: 'text-emerald-600',
            badgeVariant: 'success',
        };
    }

    if (type.startsWith('push.')) {
        return {
            icon: Bell,
            iconClassName: 'text-blue-600',
            badgeVariant: 'outline',
        };
    }

    if (type.includes('error') || type.includes('failed')) {
        return {
            icon: AlertTriangle,
            iconClassName: 'text-destructive',
            badgeVariant: 'destructive',
        };
    }

    if (type.startsWith('flow.') || type.startsWith('delivery.')) {
        return {
            icon: Route,
            iconClassName: 'text-amber-600',
            badgeVariant: 'warning',
        };
    }

    return {
        icon: Phone,
        iconClassName: 'text-primary',
        badgeVariant: 'secondary',
    };
}

function SummaryMetric({
    title,
    value,
    description,
}: {
    title: string;
    value: string | number;
    description: string;
}) {
    return (
        <div className="rounded-lg border bg-card p-4">
            <p className="text-sm text-muted-foreground">{title}</p>
            <p className="mt-2 text-2xl font-semibold tracking-tight">{value}</p>
            <p className="mt-1 text-xs text-muted-foreground">{description}</p>
        </div>
    );
}

function AttemptCard({ attempt }: { attempt: InteractionDeliveryAttempt }) {
    return (
        <div className="rounded-lg border p-4">
            <div className="flex flex-wrap items-center gap-2">
                <Badge variant="outline">{labelFromValue(attempt.attempt_type)}</Badge>
                <Badge variant={statusBadgeVariant(attempt.status)}>{labelFromValue(attempt.status)}</Badge>
                {attempt.won_at && <Badge variant="success">Winner</Badge>}
            </div>

            <dl className="mt-4 grid gap-3 text-sm md:grid-cols-2">
                <div>
                    <dt className="text-muted-foreground">Endpoint</dt>
                    <dd className="font-medium">{endpointLabel(attempt.endpoint)}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Attempt ID</dt>
                    <dd className="font-mono text-xs">{attempt.id}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Started</dt>
                    <dd>{formatDateTime(attempt.started_at)}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Answered</dt>
                    <dd>{formatDateTime(attempt.answered_at)}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Ended</dt>
                    <dd>{formatDateTime(attempt.ended_at)}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Failure reason</dt>
                    <dd>{attempt.failure_reason ?? '—'}</dd>
                </div>
            </dl>
        </div>
    );
}

function PushLogCard({ log }: { log: InteractionPushNotificationLog }) {
    return (
        <div className="rounded-lg border p-4">
            <div className="flex flex-wrap items-center gap-2">
                <Badge variant="outline">{labelFromValue(log.push_type)}</Badge>
                <Badge variant={statusBadgeVariant(log.status)}>{labelFromValue(log.status)}</Badge>
            </div>

            <dl className="mt-4 grid gap-3 text-sm md:grid-cols-2">
                <div>
                    <dt className="text-muted-foreground">Sent at</dt>
                    <dd>{formatDateTime(log.sent_at)}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Endpoint</dt>
                    <dd className="font-medium">{endpointLabel(log.endpoint)}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Log ID</dt>
                    <dd className="font-mono text-xs">{log.id}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Provider metadata</dt>
                    <dd className="truncate">{Object.keys(log.response_payload ?? {}).length} fields</dd>
                </div>
            </dl>
        </div>
    );
}

function TimelineRow({ event }: { event: InteractionTimelineEvent }) {
    const tone = timelineTone(event.type);
    const Icon = tone.icon;

    return (
        <div className="relative flex gap-4 pl-1">
            <div className="flex flex-col items-center">
                <div className="flex size-9 items-center justify-center rounded-full border bg-background shadow-sm">
                    <Icon className={`size-4 ${tone.iconClassName}`} />
                </div>
                <div className="mt-2 h-full w-px bg-border" />
            </div>

            <div className="flex-1 rounded-lg border p-4">
                <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="font-medium">{event.details.label ?? labelFromValue(event.type)}</p>
                            <Badge variant={tone.badgeVariant}>{labelFromValue(event.type)}</Badge>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {event.details.source && `Source: ${event.details.source}`}
                            {event.details.source && event.details.node_type && ' • '}
                            {event.details.node_type && `Node: ${labelFromValue(event.details.node_type)}`}
                            {!event.details.source && !event.details.node_type && 'Timeline event'}
                        </p>
                    </div>
                    <div className="text-sm text-muted-foreground">
                        {formatTime(event.occurred_at)}
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function InteractionDetailPage() {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const { activeTenant, tenantApiPrefix } = useTenant();

    const {
        data: interaction,
        isLoading,
        isError,
        error,
    } = useQuery({
        queryKey: ['interaction', activeTenant?.id, id],
        queryFn: async () => {
            const response = await api.get<{ data: InteractionOverview }>(
                `${tenantApiPrefix}/interactions/${id}`,
            );
            return response.data.data;
        },
        enabled: Boolean(activeTenant) && Boolean(id),
    });

    if (!activeTenant) {
        return (
            <div className="flex h-64 items-center justify-center text-muted-foreground">
                Select a tenant to view interaction details.
            </div>
        );
    }

    if (isLoading) {
        return (
            <div className="flex h-64 items-center justify-center">
                <div className="size-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
            </div>
        );
    }

    if (isError || !interaction) {
        return (
            <div className="space-y-6 p-6 lg:p-8">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" onClick={() => navigate('/admin/calls')}>
                        <ArrowLeft className="size-4" />
                        <span className="sr-only">Back to calls</span>
                    </Button>
                    <div>
                        <p className="text-sm text-muted-foreground">{activeTenant.name} › Call Sessions</p>
                        <h1 className="text-2xl font-bold tracking-tight">Interaction Details</h1>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Unable to load interaction</CardTitle>
                        <CardDescription>
                            {(error as Error | undefined)?.message ?? 'The interaction overview request did not return data.'}
                        </CardDescription>
                    </CardHeader>
                </Card>
            </div>
        );
    }

    const winningAttempt = interaction.winning_attempt?.attempt;
    const traceErrors = interaction.trace_analysis.errors ?? [];
    const nodeMetrics = interaction.trace_analysis.node_metrics ?? [];

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div className="flex items-start gap-4">
                    <Button variant="ghost" size="icon" onClick={() => navigate('/admin/calls')}>
                        <ArrowLeft className="size-4" />
                        <span className="sr-only">Back to calls</span>
                    </Button>
                    <div>
                        <p className="text-sm text-muted-foreground">{activeTenant.name} › Call Sessions</p>
                        <h1 className="text-2xl font-bold tracking-tight">Interaction Details</h1>
                        <p className="text-muted-foreground">
                            Session-level analytics and delivery timeline for call session {interaction.id}.
                        </p>
                    </div>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Badge variant={statusBadgeVariant(interaction.state)}>
                        {interaction.summary.status_label}
                    </Badge>
                    {interaction.summary.has_errors && (
                        <Badge variant="destructive">Trace issues present</Badge>
                    )}
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Phone className="size-5 text-primary" />
                        Session Header
                    </CardTitle>
                    <CardDescription>
                        High-level business summary for the selected interaction.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <p className="text-sm text-muted-foreground">Outcome</p>
                            <p className="mt-1 text-base font-semibold">{interaction.summary.outcome_label}</p>
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">Started</p>
                            <p className="mt-1 font-medium">{formatDateTime(interaction.started_at)}</p>
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">Ended</p>
                            <p className="mt-1 font-medium">{formatDateTime(interaction.ended_at)}</p>
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">Trace duration</p>
                            <p className="mt-1 font-medium">{formatDurationMs(interaction.summary.total_trace_duration_ms)}</p>
                        </div>
                    </div>

                    <Separator />

                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <p className="text-sm text-muted-foreground">Call session ID</p>
                            <p className="mt-1 font-mono text-xs">{interaction.id}</p>
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">Call UUID</p>
                            <p className="mt-1 break-all font-mono text-xs">{interaction.call_uuid}</p>
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">Winning attempt</p>
                            <p className="mt-1 font-medium">
                                {winningAttempt
                                    ? `${labelFromValue(winningAttempt.attempt_type)} to ${endpointLabel(winningAttempt.endpoint)}`
                                    : 'No winning attempt recorded'}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">Committed at</p>
                            <p className="mt-1 font-medium">
                                {formatDateTime(interaction.winning_attempt?.committed_at)}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <SummaryMetric
                    title="Timeline Events"
                    value={interaction.summary.timeline_event_count}
                    description="Merged events visible to operations and analytics."
                />
                <SummaryMetric
                    title="Delivery Attempts"
                    value={interaction.summary.delivery_attempt_count}
                    description="Total ring, push, or delivery attempts captured."
                />
                <SummaryMetric
                    title="Push Notifications"
                    value={interaction.summary.push_notification_count}
                    description="Wake and delivery notifications issued for this call."
                />
                <SummaryMetric
                    title="Trace Signals"
                    value={interaction.summary.trace_event_count}
                    description="Workflow and node execution signals attached to the session."
                />
            </section>

            <div className="grid gap-6 xl:grid-cols-[1.45fr_0.95fr]">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Waypoints className="size-5 text-primary" />
                            Timeline
                        </CardTitle>
                        <CardDescription>
                            Ordered event sequence across call creation, routing, push, and delivery outcomes.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {interaction.timeline.length > 0 ? (
                            interaction.timeline.map((event, index) => (
                                <div key={`${event.type}-${event.occurred_at}-${index}`}>
                                    <TimelineRow event={event} />
                                </div>
                            ))
                        ) : (
                            <div className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                                No timeline events were returned for this interaction.
                            </div>
                        )}
                    </CardContent>
                </Card>

                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <CheckCircle2 className="size-5 text-primary" />
                                Winning Attempt
                            </CardTitle>
                            <CardDescription>
                                The delivery path that ultimately answered or won the interaction.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {winningAttempt ? (
                                <dl className="grid gap-3 text-sm">
                                    <div>
                                        <dt className="text-muted-foreground">Endpoint</dt>
                                        <dd className="font-medium">{endpointLabel(winningAttempt.endpoint)}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">Attempt type</dt>
                                        <dd>{labelFromValue(winningAttempt.attempt_type)}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">Status</dt>
                                        <dd>
                                            <Badge variant={statusBadgeVariant(winningAttempt.status)}>
                                                {labelFromValue(winningAttempt.status)}
                                            </Badge>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">Answered at</dt>
                                        <dd>{formatDateTime(winningAttempt.answered_at)}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">Leg UUID</dt>
                                        <dd className="break-all font-mono text-xs">
                                            {interaction.winning_attempt?.leg_uuid ?? '—'}
                                        </dd>
                                    </div>
                                </dl>
                            ) : (
                                <div className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                                    No winning attempt has been identified for this interaction.
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Timer className="size-5 text-primary" />
                                Trace Analysis
                            </CardTitle>
                            <CardDescription>
                                Flow-level quality indicators and error counts from the overview endpoint.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4 text-sm">
                            <div className="flex items-center justify-between rounded-lg border p-3">
                                <span className="text-muted-foreground">Errors</span>
                                <Badge variant={traceErrors.length > 0 ? 'destructive' : 'success'}>
                                    {traceErrors.length}
                                </Badge>
                            </div>
                            <div className="flex items-center justify-between rounded-lg border p-3">
                                <span className="text-muted-foreground">Node metrics</span>
                                <Badge variant="secondary">{nodeMetrics.length}</Badge>
                            </div>
                            <div className="flex items-center justify-between rounded-lg border p-3">
                                <span className="text-muted-foreground">Call events</span>
                                <Badge variant="secondary">{interaction.summary.call_event_count}</Badge>
                            </div>
                            <div className="flex items-center justify-between rounded-lg border p-3">
                                <span className="text-muted-foreground">Trace duration</span>
                                <Badge variant="outline">{formatDurationMs(interaction.summary.total_trace_duration_ms)}</Badge>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <div className="grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Smartphone className="size-5 text-primary" />
                            Delivery Attempts
                        </CardTitle>
                        <CardDescription>
                            Attempt-by-attempt breakdown for device and routing outcomes.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {interaction.delivery_attempts.length > 0 ? (
                            interaction.delivery_attempts.map((attempt) => (
                                <AttemptCard key={attempt.id} attempt={attempt} />
                            ))
                        ) : (
                            <div className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                                No delivery attempts were returned.
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Bell className="size-5 text-primary" />
                            Push Notifications
                        </CardTitle>
                        <CardDescription>
                            Notification activity that supported app wake-up and ringing behavior.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {interaction.push_notification_logs.length > 0 ? (
                            interaction.push_notification_logs.map((log) => (
                                <PushLogCard key={log.id} log={log} />
                            ))
                        ) : (
                            <div className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                                No push notification logs were returned.
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <div className="grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <AlertTriangle className="size-5 text-primary" />
                            Trace Errors
                        </CardTitle>
                        <CardDescription>
                            Only surfaced when the backend trace analysis reports workflow issues.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {traceErrors.length > 0 ? (
                            traceErrors.map((traceError, index) => (
                                <div key={`${traceError.message ?? 'error'}-${index}`} className="rounded-lg border p-4">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="font-medium">{traceError.message ?? 'Trace error'}</p>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {traceError.code ?? traceError.type ?? 'Unclassified issue'}
                                            </p>
                                        </div>
                                        <Badge variant="destructive">Issue</Badge>
                                    </div>
                                    <p className="mt-3 text-xs text-muted-foreground">
                                        {formatDateTime(traceError.occurred_at)}
                                    </p>
                                </div>
                            ))
                        ) : (
                            <div className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                                No trace errors were reported for this session.
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Clock3 className="size-5 text-primary" />
                            Node Metrics
                        </CardTitle>
                        <CardDescription>
                            Per-node timing metrics emitted by the trace analysis pipeline.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {nodeMetrics.length > 0 ? (
                            nodeMetrics.map((metric, index) => {
                                const key = metric.node_id ?? metric.node_type ?? `metric-${index}`;
                                const duration = typeof metric.duration_ms === 'number'
                                    ? formatDurationMs(metric.duration_ms)
                                    : '—';

                                return (
                                    <div key={`${key}-${index}`} className="flex items-center justify-between rounded-lg border p-3 text-sm">
                                        <div>
                                            <p className="font-medium">
                                                {metric.node_label ?? labelFromValue(metric.node_type ?? metric.node_id ?? 'Unknown node')}
                                            </p>
                                            <p className="text-muted-foreground">
                                                {metric.node_id ?? 'No node id'}
                                            </p>
                                        </div>
                                        <Badge variant="outline">{duration}</Badge>
                                    </div>
                                );
                            })
                        ) : (
                            <div className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                                No node metrics were returned for this interaction.
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
