import { z } from 'zod';

const idSchema = z.union([z.string(), z.number()]).transform((value) => String(value));

// ─── Tenant ──────────────────────────────────────────────────

export const TenantSchema = z.object({
    id: idSchema,
    name: z.string(),
    domain: z.string(),
    default_schedule_id: idSchema.nullable().optional(),
    default_holiday_calendar_id: idSchema.nullable().optional(),
    slug: z.string().nullable().optional(),
    settings: z.record(z.string(), z.unknown()).nullable().optional(),
    status: z.string().nullable().optional(),
    max_extensions: z.number().nullable().optional(),
    max_concurrent_calls: z.number().nullable().optional(),
    max_dids: z.number().nullable().optional(),
    max_ring_groups: z.number().nullable().optional(),
    is_active: z.boolean().optional(),
    created_at: z.string(),
    updated_at: z.string(),
});
export type Tenant = z.infer<typeof TenantSchema>;

// ─── Extension ───────────────────────────────────────────────

export const ExtensionSchema = z.object({
    id: idSchema,
    tenant_id: idSchema,
    extension: z.string(),
    password: z.string().optional(),
    directory_first_name: z.string().nullable().optional(),
    directory_last_name: z.string().nullable().optional(),
    voicemail_pin: z.string().nullable().optional(),
    effective_caller_id_name: z.string().nullable().optional(),
    effective_caller_id_number: z.string().nullable().optional(),
    outbound_caller_id_name: z.string().nullable().optional(),
    outbound_caller_id_number: z.string().nullable().optional(),
    voicemail_enabled: z.boolean().optional(),
    is_active: z.boolean().optional(),
    created_at: z.string(),
    updated_at: z.string(),
});
export type Extension = z.infer<typeof ExtensionSchema>;

// ─── Ring Group ──────────────────────────────────────────────

export const RingGroupMemberSchema = z.object({
    extension: z.string(),
    timeout: z.number().optional(),
    delay: z.number().optional(),
    active: z.boolean().optional(),
});
export type RingGroupMember = z.infer<typeof RingGroupMemberSchema>;

export const RingGroupSchema = z.object({
    id: idSchema,
    tenant_id: idSchema,
    name: z.string(),
    strategy: z.string(),
    ring_timeout: z.number().optional(),
    members: z.array(RingGroupMemberSchema).default([]),
    fallback_destination_type: z.string().nullable().optional(),
    fallback_destination_id: z.string().nullable().optional(),
    is_active: z.boolean().optional(),
    created_at: z.string(),
    updated_at: z.string(),
});
export type RingGroup = z.infer<typeof RingGroupSchema>;

// ─── Gateway ─────────────────────────────────────────────────

export const GatewaySchema = z.object({
    id: idSchema,
    tenant_id: idSchema,
    name: z.string(),
    host: z.string().nullable().optional(),
    gateway_name: z.string().optional(),
    username: z.string().nullable().optional(),
    realm: z.string().nullable().optional(),
    proxy: z.string().nullable().optional(),
    register: z.boolean().optional(),
    enabled: z.boolean().optional(),
    tenant: TenantSchema.nullable().optional(),
    created_at: z.string(),
    updated_at: z.string(),
});
export type Gateway = z.infer<typeof GatewaySchema>;

// ─── DID ─────────────────────────────────────────────────────

export const DidSchema = z.object({
    id: idSchema,
    tenant_id: idSchema,
    number: z.string(),
    description: z.string().nullable().optional(),
    destination_type: z.string().nullable().optional(),
    destination_id: z.string().nullable().optional(),
    enabled: z.boolean().optional(),
    created_at: z.string(),
    updated_at: z.string(),
});
export type Did = z.infer<typeof DidSchema>;

// ─── CDR ─────────────────────────────────────────────────────

export const CdrSchema = z.object({
    id: idSchema,
    tenant_id: idSchema,
    uuid: z.string().nullable().optional(),
    call_uuid: z.string().nullable().optional(),
    caller_id_name: z.string().nullable().optional(),
    caller_id_number: z.string().nullable().optional(),
    destination_number: z.string().nullable().optional(),
    direction: z.string().nullable().optional(),
    duration: z.number().nullable().optional(),
    billsec: z.number().nullable().optional(),
    hangup_cause: z.string().nullable().optional(),
    start_stamp: z.string().nullable().optional(),
    answer_stamp: z.string().nullable().optional(),
    end_stamp: z.string().nullable().optional(),
    created_at: z.string(),
    updated_at: z.string(),
});
export type Cdr = z.infer<typeof CdrSchema>;

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
    tenant_id: idSchema,
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
    tenant_id: z.number().nullable().optional(),
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
    tenant_id: idSchema.nullable().optional(),
    role: z.string(),
    email_verified_at: z.string().nullable().optional(),
    tenant: TenantSchema.nullable().optional(),
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
