import { z } from 'zod';

// ─── User & Auth ──────────────────────────────────────────────

export const TenantSchema = z.object({
    id: z.number(),
    name: z.string(),
    domain: z.string(),
    created_at: z.string(),
    updated_at: z.string(),
});
export type Tenant = z.infer<typeof TenantSchema>;

export const UserSchema = z.object({
    id: z.number(),
    name: z.string(),
    email: z.string().email(),
    tenant_id: z.number().nullable(),
    role: z.string(),
    email_verified_at: z.string().nullable(),
    tenant: TenantSchema.nullable().optional(),
    created_at: z.string(),
    updated_at: z.string(),
});
export type User = z.infer<typeof UserSchema>;

export const LoginRequestSchema = z.object({
    email: z.string().email('Please enter a valid email address'),
    password: z.string().min(1, 'Password is required'),
});
export type LoginRequest = z.infer<typeof LoginRequestSchema>;

export const AuthResponseSchema = z.object({
    user: UserSchema,
    token: z.string(),
});
export type AuthResponse = z.infer<typeof AuthResponseSchema>;

// ─── API Error ────────────────────────────────────────────────

export interface ApiError {
    message: string;
    errors?: Record<string, string[]>;
}

// ─── Dashboard Stats ──────────────────────────────────────────

export interface DashboardStats {
    tenants: number;
    extensions: number;
    active_calls: number;
    gateways: number;
}
