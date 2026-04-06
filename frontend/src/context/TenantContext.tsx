import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useState,
    type ReactNode,
} from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import type { Tenant } from '@/types/models';
import { useAuth } from './AuthContext';

// ─── Context Shape ───────────────────────────────────────────

interface TenantContextValue {
    /** All tenants available to the superadmin */
    tenants: Tenant[];
    /** Currently selected/active tenant */
    activeTenant: Tenant | null;
    /** Switch to a different tenant */
    switchTenant: (tenant: Tenant | null) => void;
    /** Whether tenants are still loading */
    isLoading: boolean;
    /** Convenience: returns the base API prefix for tenant-scoped endpoints */
    tenantApiPrefix: string;
}

const TenantContext = createContext<TenantContextValue | undefined>(undefined);

// ─── Provider ────────────────────────────────────────────────

export function TenantProvider({ children }: { children: ReactNode }) {
    const [activeTenant, setActiveTenant] = useState<Tenant | null>(null);

    const { user } = useAuth();

    const { data: tenants = [], isLoading } = useQuery({
        queryKey: ['tenants'],
        queryFn: async () => {
            const res = await api.get<{ data: Tenant[] }>('tenants');
            return res.data.data;
        },
    });

    // Auto-select tenant only if explicitly restored or if user is locked to a specific tenant
    useEffect(() => {
        if (!activeTenant && tenants.length > 0) {
            // Try to restore from localStorage
            const storedId = localStorage.getItem('nizam_active_tenant');
            const restored = storedId
                ? tenants.find((t) => String(t.id) === storedId)
                : null;
            
            if (restored) {
                setActiveTenant(restored);
            } else if (user?.tenant_id) {
                // Tenant admin: bound to a single tenant, assign it
                setActiveTenant(tenants[0]);
            }
        }
    }, [tenants, activeTenant, user]);

    const switchTenant = useCallback((tenant: Tenant | null) => {
        setActiveTenant(tenant);
        if (tenant) {
            localStorage.setItem('nizam_active_tenant', String(tenant.id));
        } else {
            localStorage.removeItem('nizam_active_tenant');
        }
    }, []);

    const tenantApiPrefix = activeTenant
        ? `tenants/${activeTenant.id}`
        : '';

    const value = useMemo<TenantContextValue>(
        () => ({
            tenants,
            activeTenant,
            switchTenant,
            isLoading,
            tenantApiPrefix,
        }),
        [tenants, activeTenant, switchTenant, isLoading, tenantApiPrefix],
    );

    return (
        <TenantContext.Provider value={value}>
            {children}
        </TenantContext.Provider>
    );
}

// ─── Hook ────────────────────────────────────────────────────

export function useTenant(): TenantContextValue {
    const context = useContext(TenantContext);
    if (!context) {
        throw new Error('useTenant must be used within a TenantProvider');
    }
    return context;
}
