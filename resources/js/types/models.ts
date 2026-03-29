import { z } from 'zod';

// ─── Tenant ──────────────────────────────────────────────────

export const TenantSchema = z.object({
    id: z.number(),
    name: z.string(),
    domain: z.string(),
    settings: z.record(z.string(), z.unknown()).nullable().optional(),
    created_at: z.string(),
    updated_at: z.string(),
});
export type Tenant = z.infer<typeof TenantSchema>;

// ─── Extension ───────────────────────────────────────────────

export const ExtensionSchema = z.object({
    id: z.number(),
    tenant_id: z.number(),
    extension: z.string(),
    password: z.string().optional(),
    voicemail_pin: z.string().nullable().optional(),
    effective_caller_id_name: z.string().nullable().optional(),
    effective_caller_id_number: z.string().nullable().optional(),
    outbound_caller_id_name: z.string().nullable().optional(),
    outbound_caller_id_number: z.string().nullable().optional(),
    enabled: z.boolean().optional(),
    created_at: z.string(),
    updated_at: z.string(),
});
export type Extension = z.infer<typeof ExtensionSchema>;

// ─── Gateway ─────────────────────────────────────────────────

export const GatewaySchema = z.object({
    id: z.string(),
    tenant_id: z.string(),
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
    id: z.string(),
    tenant_id: z.string(),
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
    id: z.number(),
    tenant_id: z.number(),
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
    id: z.number(),
    user_id: z.number().nullable().optional(),
    tenant_id: z.number().nullable().optional(),
    auditable_type: z.string(),
    auditable_id: z.number(),
    event: z.string(),
    old_values: z.record(z.string(), z.unknown()).nullable().optional(),
    new_values: z.record(z.string(), z.unknown()).nullable().optional(),
    ip_address: z.string().nullable().optional(),
    user_agent: z.string().nullable().optional(),
    created_at: z.string(),
});
export type AuditLog = z.infer<typeof AuditLogSchema>;

// ─── User ────────────────────────────────────────────────────

export const UserSchema = z.object({
    id: z.number(),
    name: z.string(),
    email: z.string().email(),
    tenant_id: z.number().nullable(),
    role: z.string(),
    email_verified_at: z.string().nullable(),
    created_at: z.string(),
    updated_at: z.string(),
});
export type User = z.infer<typeof UserSchema>;
