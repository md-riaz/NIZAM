import { z } from 'zod';

const idSchema = z.union([z.string(), z.number()]).transform((value) => String(value));

// ─── Tenant ──────────────────────────────────────────────────

export const TenantSchema = z.object({
    id: idSchema,
    name: z.string(),
    domain: z.string(),
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
    call_uuid: z.string(),
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
