import { z } from 'zod';

const idSchema = z.union([z.string(), z.number()]).transform((value) => String(value));

const DidSummarySchema = z.object({
    id: idSchema,
    number: z.string(),
    description: z.string().nullable().optional(),
}).passthrough();

const DidAssignmentUserSchema = z.object({
    id: idSchema,
    name: z.string().optional(),
    email: z.string().email().optional(),
    organization_id: idSchema.nullable().optional(),
    role: z.string().optional(),
}).passthrough();

const DidAssignmentTeamSchema = z.object({
    id: idSchema,
    organization_id: idSchema.optional(),
    name: z.string().optional(),
    strategy: z.string().optional(),
    timeout: z.number().optional(),
    is_active: z.boolean().optional(),
}).passthrough();

const DidAssignmentDeviceProfileSchema = z.object({
    id: idSchema,
    organization_id: idSchema.optional(),
    user_id: idSchema.nullable().optional(),
    name: z.string().optional(),
    extension_id: idSchema.nullable().optional(),
    default_outbound_did_id: idSchema.nullable().optional(),
    is_active: z.boolean().optional(),
}).passthrough();

// ─── Organization ──────────────────────────────────────────────────

export const OrganizationSchema = z.object({
    id: idSchema,
    name: z.string(),
    domain: z.string(),
    domain_prefix: z.string().optional(),
    domain_suffix: z.string().optional(),
    domain_matches_configured_suffix: z.boolean().optional(),
    default_schedule_id: idSchema.nullable().optional(),
    default_holiday_calendar_id: idSchema.nullable().optional(),
    slug: z.string().nullable().optional(),
    settings: z.record(z.string(), z.unknown()).nullable().optional(),
    status: z.string().nullable().optional(),
    recording_policy: z.enum(['inherit', 'off', 'all', 'incoming', 'outgoing']).nullable().optional(),
    recording_retention_days: z.number().nullable().optional(),
    max_extensions: z.number().nullable().optional(),
    max_concurrent_calls: z.number().nullable().optional(),
    max_dids: z.number().nullable().optional(),
    max_teams: z.number().nullable().optional(),
    is_active: z.boolean().optional(),
    created_at: z.string(),
    updated_at: z.string(),
});
export type Organization = z.infer<typeof OrganizationSchema>;

// ─── Extension ───────────────────────────────────────────────

export const ExtensionSchema = z.object({
    id: idSchema,
    organization_id: idSchema,
    user_id: idSchema.nullable().optional(),
    device_profile_id: idSchema.nullable().optional(),
    default_outbound_did_id: idSchema.nullable().optional(),
    default_outbound_gateway_id: idSchema.nullable().optional(),
    allowed_outbound_did_ids: z.array(idSchema).default([]),
    allowed_outbound_gateway_ids: z.array(idSchema).default([]),
    owner_type: z.enum(['user', 'device', 'unassigned']).optional(),
    owner_label: z.string().optional(),
    extension: z.string(),
    password: z.string().optional(),
    first_name: z.string().nullable().optional(),
    last_name: z.string().nullable().optional(),
    voicemail_pin: z.string().nullable().optional(),
    effective_caller_id_name: z.string().nullable().optional(),
    recording_policy: z.enum(['inherit', 'off', 'all', 'incoming', 'outgoing']).nullable().optional(),
    follow_me_enabled: z.boolean().optional(),
    follow_me_destination: z.string().nullable().optional(),
    dnd_enabled: z.boolean().optional(),
    voicemail_enabled: z.boolean().optional(),
    is_active: z.boolean().optional(),
    created_at: z.string(),
    updated_at: z.string(),
});
export type Extension = z.infer<typeof ExtensionSchema>;

export interface ExtensionFeatures {
    follow_me_enabled: boolean;
    follow_me_destination?: string | null;
    dnd_enabled: boolean;
}

export interface OfficeFeatures {
    parking_enabled: boolean;
    pickup_enabled: boolean;
    paging_enabled: boolean;
    intercom_enabled: boolean;
    directory_enabled: boolean;
}

// ─── Gateway ─────────────────────────────────────────────────

export const DeviceProfileSchema = z.object({
    id: idSchema,
    organization_id: idSchema,
    user_id: idSchema.nullable().optional(),
    name: z.string(),
    vendor: z.string().nullable().optional(),
    mac_address: z.string().nullable().optional(),
    template: z.string().nullable().optional(),
    extension_id: idSchema.nullable().optional(),
    owned_extension_ids: z.array(idSchema).default([]),
    is_active: z.boolean().optional(),
    created_at: z.string(),
    updated_at: z.string(),
});
export type DeviceProfile = z.infer<typeof DeviceProfileSchema>;

export const TeamSchema = z.object({
    id: idSchema,
    organization_id: idSchema,
    schedule_id: idSchema.nullable().optional(),
    holiday_calendar_id: idSchema.nullable().optional(),
    name: z.string(),
    strategy: z.string(),
    timeout: z.number().optional(),
    is_active: z.boolean().optional(),
    members: z.array(z.unknown()).default([]),
    created_at: z.string(),
    updated_at: z.string().optional(),
});
export type Team = z.infer<typeof TeamSchema>;

export const SystemMediaSchema = z.object({
    id: idSchema,
    uuid: z.string().nullable().optional(),
    name: z.string(),
    file_name: z.string(),
    mime_type: z.string().nullable().optional(),
    size: z.number().nullable().optional(),
    custom_properties: z.record(z.string(), z.unknown()).default({}),
    collection_name: z.string().nullable().optional(),
    created_at: z.string().nullable().optional(),
    url: z.string().nullable().optional(),
    path: z.string().nullable().optional(),
});
export type SystemMedia = z.infer<typeof SystemMediaSchema>;

export const GatewaySchema = z.object({
    id: idSchema,
    organization_id: idSchema,
    name: z.string(),
    host: z.string().nullable().optional(),
    gateway_name: z.string().optional(),
    username: z.string().nullable().optional(),
    password: z.string().nullable().optional(),
    realm: z.string().nullable().optional(),
    proxy: z.string().nullable().optional(),
    register: z.boolean().optional(),
    enabled: z.boolean().optional(),
    is_active: z.boolean().optional(),
    organization: OrganizationSchema.nullable().optional(),
    created_at: z.string(),
    updated_at: z.string(),
});
export type Gateway = z.infer<typeof GatewaySchema>;

// ─── DID ─────────────────────────────────────────────────────

export const DidSchema = z.object({
    id: idSchema,
    organization_id: idSchema,
    gateway_id: idSchema.nullable().optional(),
    number: z.string(),
    normalized_number: z.string().nullable().optional(),
    description: z.string().nullable().optional(),
    recording_policy: z.enum(['inherit', 'off', 'all', 'incoming', 'outgoing']).nullable().optional(),
    destination_type: z.enum(['extension', 'flow']).nullable().optional(),
    destination_id: z.string().nullable().optional(),
    enabled: z.boolean().optional(),
    is_active: z.boolean().optional(),
    gateway: GatewaySchema.nullable().optional(),
    created_at: z.string(),
    updated_at: z.string(),
});
export type Did = z.infer<typeof DidSchema>;

export const CallBlockSchema = z.object({
    id: idSchema,
    organization_id: idSchema,
    name: z.string(),
    description: z.string().nullable().optional(),
    number: z.string(),
    action: z.literal('reject'),
    is_active: z.boolean(),
    created_at: z.string(),
    updated_at: z.string(),
});
export type CallBlock = z.infer<typeof CallBlockSchema>;

// ─── Flow Builder ────────────────────────────────────────────

export const FlowNodePositionSchema = z.object({
    x: z.number(),
    y: z.number(),
});
export type FlowNodePosition = z.infer<typeof FlowNodePositionSchema>;

export const FlowMenuOptionSchema = z.object({
    digit: z.string(),
    label: z.string().optional().default(''),
});
export type FlowMenuOption = z.infer<typeof FlowMenuOptionSchema>;

export const FlowNodeConfigSchema = z.record(z.string(), z.unknown());
export type FlowNodeConfig = z.infer<typeof FlowNodeConfigSchema>;

export const FlowNodeSchema = z.object({
    id: idSchema,
    type: z.string(),
    name: z.string().nullable().optional(),
    config: FlowNodeConfigSchema.nullable().optional(),
    position_x: z.number().nullable().optional(),
    position_y: z.number().nullable().optional(),
});
export type FlowNode = z.infer<typeof FlowNodeSchema>;

export const FlowEdgeSchema = z.object({
    id: idSchema,
    source_node_id: idSchema,
    target_node_id: idSchema,
    condition: z.string().nullable().optional(),
});
export type FlowEdge = z.infer<typeof FlowEdgeSchema>;

export const FlowVersionSchema = z.object({
    id: idSchema,
    version_number: z.number().nullable().optional(),
    status: z.string().nullable().optional(),
    is_published: z.boolean().optional(),
    definition_checksum: z.string().nullable().optional(),
    nodes: z.array(FlowNodeSchema).default([]),
    edges: z.array(FlowEdgeSchema).default([]),
    created_at: z.string().nullable().optional(),
    updated_at: z.string().nullable().optional(),
});
export type FlowVersion = z.infer<typeof FlowVersionSchema>;

export const FlowSchema = z.object({
    id: idSchema,
    organization_id: idSchema,
    name: z.string(),
    description: z.string().nullable().optional(),
    active_version_id: idSchema.nullable().optional(),
    active_version: FlowVersionSchema.nullable().optional(),
    latest_version: FlowVersionSchema.nullable().optional(),
    versions: z.array(FlowVersionSchema).default([]),
    created_at: z.string(),
    updated_at: z.string(),
});
export type Flow = z.infer<typeof FlowSchema>;

export const FlowDefinitionSchema = z.object({
    nodes: z.array(FlowNodeSchema),
    edges: z.array(FlowEdgeSchema),
});
export type FlowDefinition = z.infer<typeof FlowDefinitionSchema>;

// ─── Recordings ──────────────────────────────────────────────

export const RecordingSchema = z.object({
    id: idSchema,
    organization_id: idSchema,
    call_uuid: z.string().nullable().optional(),
    file_name: z.string().nullable().optional(),
    file_size: z.number().nullable().optional(),
    format: z.string().nullable().optional(),
    duration: z.number().nullable().optional(),
    direction: z.string().nullable().optional(),
    caller_id_number: z.string().nullable().optional(),
    destination_number: z.string().nullable().optional(),
    created_at: z.string(),
    updated_at: z.string(),
});
export type Recording = z.infer<typeof RecordingSchema>;

// ─── Recording policy resolution ──────────────────────────────

export const RecordingPolicyResolutionSchema = z.object({
    resolved_mode: z.enum(['inherit', 'off', 'all', 'incoming', 'outgoing']),
    should_record: z.boolean(),
    winning_scope: z.enum(['organization', 'did', 'extension']).nullable(),
    resolution_chain: z.array(z.string()),
    reason: z.string(),
});
export type RecordingPolicyResolution = z.infer<typeof RecordingPolicyResolutionSchema>;

export const InboundDidOverrideSchema = z.object({
    id: idSchema,
    number: z.string(),
    recording_policy: z.enum(['inherit', 'off', 'all', 'incoming', 'outgoing']),
});
export type InboundDidOverride = z.infer<typeof InboundDidOverrideSchema>;

export const EffectiveRecordingPolicySchema = z.object({
    scope: z.enum(['organization', 'did', 'extension']),
    inbound: RecordingPolicyResolutionSchema,
    outbound: RecordingPolicyResolutionSchema,
    /**
     * Numbers routed straight to this extension whose own policy overrides the
     * extension's for calls arriving on them. Extension scope only.
     */
    inbound_did_overrides: z.array(InboundDidOverrideSchema).optional(),
});
export type EffectiveRecordingPolicy = z.infer<typeof EffectiveRecordingPolicySchema>;

// ─── CDR ─────────────────────────────────────────────────────

export const CdrSchema = z.object({
    id: idSchema,
    organization_id: idSchema,
    uuid: z.string().nullable().optional(),
    call_uuid: z.string().nullable().optional(),
    caller_id_name: z.string().nullable().optional(),
    caller_id_number: z.string().nullable().optional(),
    destination_number: z.string().nullable().optional(),
    direction: z.string().nullable().optional(),
    call_type: z.string().nullable().optional(),
    duration: z.number().nullable().optional(),
    billsec: z.number().nullable().optional(),
    hangup_cause: z.string().nullable().optional(),
    start_stamp: z.string().nullable().optional(),
    answer_stamp: z.string().nullable().optional(),
    end_stamp: z.string().nullable().optional(),
    recording_path: z.string().nullable().optional(),
    has_recording: z.boolean().optional(),
    recordings: z.array(RecordingSchema).optional(),
    /** Present only when the call was traced through the delivery pipeline. */
    call_session_id: idSchema.nullable().optional(),
    created_at: z.string(),
    updated_at: z.string(),
});
export type Cdr = z.infer<typeof CdrSchema>;

/**
 * Aggregate counters for a set of call records.
 *
 * `GET .../cdrs` returns this in `meta.summary`, computed from the same filters
 * as the rows themselves, so KPI tiles above a filtered table describe that
 * table. The analytics endpoint returns the same fields for a date range only.
 */
export interface CdrSummary {
    total_calls: number;
    answered_calls: number;
    missed_calls: number;
    failed_calls: number;
    total_duration_seconds: number;
    total_billsec_seconds: number;
    asr: number;
    acd_seconds: number;
}

/** Laravel paginator metadata, as returned alongside `data`. */
export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

/** Paginator metadata for `GET .../cdrs`, which carries the filtered summary. */
export interface CdrPaginationMeta extends PaginationMeta {
    summary?: CdrSummary;
}

// ─── Interaction Overview ────────────────────────────────────

const interactionEndpointSchema = z.object({
    id: idSchema.optional(),
    type: z.string().nullable().optional(),
    platform: z.string().nullable().optional(),
}).passthrough();

export const InteractionTimelineEventSchema = z.object({
    type: z.string(),
    occurred_at: z.string(),
    details: z.object({
        label: z.string().nullable().optional(),
        source: z.string().nullable().optional(),
        node_id: z.string().nullable().optional(),
        node_type: z.string().nullable().optional(),
    }).passthrough(),
});
export type InteractionTimelineEvent = z.infer<typeof InteractionTimelineEventSchema>;

export const InteractionDeliveryAttemptSchema = z.object({
    id: idSchema,
    attempt_type: z.string(),
    status: z.string(),
    failure_reason: z.string().nullable().optional(),
    started_at: z.string().nullable().optional(),
    answered_at: z.string().nullable().optional(),
    ended_at: z.string().nullable().optional(),
    won_at: z.string().nullable().optional(),
    endpoint: interactionEndpointSchema.nullable().optional(),
}).passthrough();
export type InteractionDeliveryAttempt = z.infer<typeof InteractionDeliveryAttemptSchema>;

export const InteractionPushNotificationLogSchema = z.object({
    id: idSchema,
    push_type: z.string(),
    status: z.string(),
    sent_at: z.string().nullable().optional(),
    endpoint: interactionEndpointSchema.nullable().optional(),
    response_payload: z.record(z.string(), z.unknown()).nullable().optional(),
}).passthrough();
export type InteractionPushNotificationLog = z.infer<typeof InteractionPushNotificationLogSchema>;

export const CallSessionSummarySchema = z.object({
    id: idSchema,
    organization_id: idSchema,
    call_uuid: z.string(),
    state: z.string().nullable().optional(),
    started_at: z.string().nullable().optional(),
    ended_at: z.string().nullable().optional(),
    winner: z.object({
        attempt_id: idSchema.nullable().optional(),
        leg_uuid: z.string().nullable().optional(),
        committed_at: z.string().nullable().optional(),
        attempt: InteractionDeliveryAttemptSchema.nullable().optional(),
    }).nullable().optional(),
    delivery_attempts: z.array(InteractionDeliveryAttemptSchema).default([]),
    push_notification_logs: z.array(InteractionPushNotificationLogSchema).default([]),
    created_at: z.string(),
    updated_at: z.string(),
});
export type CallSessionSummary = z.infer<typeof CallSessionSummarySchema>;

export const InteractionOverviewSchema = z.object({
    id: idSchema,
    call_uuid: z.string(),
    state: z.string().nullable().optional(),
    started_at: z.string().nullable().optional(),
    ended_at: z.string().nullable().optional(),
    summary: z.object({
        status_label: z.string(),
        outcome_label: z.string(),
        delivery_attempt_count: z.number(),
        push_notification_count: z.number(),
        call_event_count: z.number(),
        trace_event_count: z.number(),
        timeline_event_count: z.number(),
        has_errors: z.boolean(),
        total_trace_duration_ms: z.number(),
    }),
    timeline: z.array(InteractionTimelineEventSchema),
    delivery_attempts: z.array(InteractionDeliveryAttemptSchema),
    push_notification_logs: z.array(InteractionPushNotificationLogSchema),
    winning_attempt: z.object({
        attempt_id: idSchema.nullable().optional(),
        leg_uuid: z.string().nullable().optional(),
        committed_at: z.string().nullable().optional(),
        attempt: InteractionDeliveryAttemptSchema.nullable().optional(),
    }).nullable().optional(),
    trace_analysis: z.object({
        errors: z.array(z.record(z.string(), z.unknown())),
        node_metrics: z.array(z.record(z.string(), z.unknown())),
    }),
});
export type InteractionOverview = z.infer<typeof InteractionOverviewSchema>;

// ─── Audit Log ───────────────────────────────────────────────

export const AuditLogSchema = z.object({
    id: idSchema,
    user_id: z.number().nullable().optional(),
    organization_id: z.number().nullable().optional(),
    auditable_type: z.string(),
    auditable_id: idSchema,
    action: z.string().nullable().optional(),
    old_values: z.record(z.string(), z.unknown()).nullable().optional(),
    new_values: z.record(z.string(), z.unknown()).nullable().optional(),
    ip_address: z.string().nullable().optional(),
    user_agent: z.string().nullable().optional(),
    created_at: z.string(),
});
export type AuditLog = z.infer<typeof AuditLogSchema>;

// ─── User ────────────────────────────────────────────────────

export const PermissionSchema = z.object({
    slug: z.string(),
    description: z.string().nullable().optional(),
    module: z.string().nullable().optional(),
});
export type Permission = z.infer<typeof PermissionSchema>;

export const UserSchema = z.object({
    id: idSchema,
    name: z.string(),
    email: z.string().email(),
    organization_id: idSchema.nullable().optional(),
    primary_extension_id: idSchema.nullable().optional(),
    extension_ids: z.array(idSchema).default([]),
    role: z.string(),
    email_verified_at: z.string().nullable().optional(),
    organization: OrganizationSchema.nullable().optional(),
    created_at: z.string(),
    updated_at: z.string(),
});
export type User = z.infer<typeof UserSchema>;

// ─── FreeSWITCH Module Status ────────────────────────────────

export const FreeSwitchModuleStatusSchema = z.object({
    name: z.string(),
    type: z.string(),
    status: z.string(),
    supports_start: z.boolean().optional(),
    supports_stop: z.boolean().optional(),
});
export type FreeSwitchModuleStatus = z.infer<typeof FreeSwitchModuleStatusSchema>;

// ─── Reports ─────────────────────────────────────────────────
//
// Report endpoints are read-only aggregates assembled by service classes rather
// than API resources, so they are typed as plain interfaces (like `CdrSummary`
// above) instead of zod schemas — nothing here is ever submitted back.
//
// Every report endpoint wraps its payload in `data`.

/** ISO-8601 bounds echoed back by every report endpoint. */
export interface ReportPeriod {
    from: string;
    to: string;
}

/** `GET .../cdrs/analytics/summary` — accepts `date_from`, `date_to`. */
export interface CdrAnalyticsSummary {
    period: ReportPeriod;
    total_calls: number;
    answered_calls: number;
    missed_calls: number;
    failed_calls: number;
    total_duration_seconds: number;
    total_billsec_seconds: number;
    average_duration_seconds: number;
    /** Answer-seizure ratio, already a percentage rounded to 2 decimals. */
    asr: number;
    /** Average call duration over answered calls, in seconds. */
    acd_seconds: number;
    quality: {
        average_score: number | null;
        average_mos: number | null;
    };
    /** Keyed by direction ("inbound"/"outbound"); absent directions are omitted. */
    by_direction: Record<string, number>;
    /** Keyed by call type; calls with a null `call_type` are excluded entirely. */
    by_call_type: Record<string, number>;
}

/** One bucket from `GET .../cdrs/analytics/volume`. */
export interface CdrVolumePoint {
    /** `YYYY-MM-DD` for daily granularity. */
    period: string;
    total_calls: number;
    answered_calls: number;
    total_duration_seconds: number;
    total_billsec_seconds: number;
    asr: number;
}

/** One bucket from `GET .../cdrs/analytics/quality`. Only calls with a quality score are counted. */
export interface CdrQualityPoint {
    period: string;
    avg_quality_score: number;
    avg_mos: number | null;
    avg_packet_loss: number | null;
    avg_jitter_ms: number | null;
    avg_latency_ms: number | null;
    sample_count: number;
}

/** One row from `GET .../cdrs/analytics/destinations` — accepts `limit` (capped at 100). */
export interface CdrTopDestination {
    destination_number: string | null;
    total_calls: number;
    answered_calls: number;
    total_duration_seconds: number;
    total_billsec_seconds: number;
    asr: number;
    avg_quality_score: number | null;
}

/** `GET .../supervisor-reports/call-summary`. */
export interface SupervisorCallSummaryReport {
    period: ReportPeriod;
    totals: {
        calls: number;
        answered_calls: number;
        /** Inbound calls that were never answered. */
        missed_calls: number;
        voicemail_calls: number;
        total_duration_seconds: number;
        total_billsec_seconds: number;
        /** Percentage rounded to 2 decimals. */
        answer_rate: number;
    };
    by_direction: Record<string, number>;
}

/** The outbound call that closed the loop on a missed call or voicemail. */
export interface ReportReturnedCall {
    /** UUID: call_detail_records.id is a uuid primary key. */
    cdr_id: string;
    call_uuid: string | null;
    started_at: string | null;
    destination_number: string | null;
}

export interface MissedReturnedCallItem {
    /** UUID: call_detail_records.id is a uuid primary key. */
    cdr_id: string;
    call_uuid: string | null;
    caller_id_number: string | null;
    normalized_caller_number: string;
    destination_number: string | null;
    missed_at: string | null;
    returned: boolean;
    returned_call: ReportReturnedCall | null;
}

/**
 * `GET .../supervisor-reports/missed-returned-calls` — accepts `window_days`.
 * Not paginated: every missed call in the range is returned in `items`.
 */
export interface MissedReturnedCallsReport {
    period: ReportPeriod;
    returned_call_window_days: number;
    summary: {
        missed_calls: number;
        returned_calls: number;
        open_missed_calls: number;
    };
    items: MissedReturnedCallItem[];
}

export interface VoicemailFollowUpItem {
    /** UUID: call_events.id is a uuid primary key. */
    event_id: string;
    call_uuid: string | null;
    caller_id_number: string;
    normalized_caller_number: string;
    mailbox: string | null;
    received_at: string | null;
    follow_up_status: 'pending' | 'returned';
    needs_attention: boolean;
    recording: {
        /** UUID: recordings.id is a uuid primary key. */
        id: string;
        call_uuid: string | null;
        needs_review: boolean;
        review_reasons: string[] | null;
        file_name: string | null;
    } | null;
    returned_call: ReportReturnedCall | null;
}

/**
 * `GET .../supervisor-reports/voicemails-needing-follow-up` — accepts `window_days`.
 * Authorized against `Recording`, not `CallDetailRecord`, so this one section can
 * 403 while the rest of the supervisor report loads fine.
 */
export interface VoicemailsNeedingFollowUpReport {
    period: ReportPeriod;
    returned_call_window_days: number;
    summary: {
        voicemails: number;
        pending_follow_up: number;
        needs_review: number;
        needs_attention: number;
    };
    items: VoicemailFollowUpItem[];
}

/** Aggregates for one metered metric over the requested range. */
export interface UsageMetricSummary {
    total: number;
    peak: number;
    average: number;
    /** Number of daily usage records that contributed. */
    count: number;
}

/** `GET .../usage/summary` — accepts `from`/`to` (not `date_from`/`date_to`). */
export interface UsageSummaryReport {
    /** UUID, like every id here — `idSchema` normalises ids to string. */
    organization_id: string;
    /** `YYYY-MM-DD`. */
    from: string;
    to: string;
    /** Keyed by metric slug; metrics with no records in range are absent. */
    usage: Record<string, UsageMetricSummary>;
}

/** `GET .../usage/reconcile` — accepts `from`/`to`. */
export interface UsageReconciliation {
    cdr_total_seconds: number;
    cdr_total_minutes: number;
    metered_minutes: number;
    difference_minutes: number;
    matched: boolean;
}
