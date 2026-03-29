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

// ─── Context Shape ───────────────────────────────────────────

interface TenantContextValue {
    /** All tenants available to the superadmin */
    tenants: Tenant[];
    /** Currently selected/active tenant */
    activeTenant: Tenant | null;
    /** Switch to a different tenant */
    switchTenant: (tenant: Tenant) => void;
    /** Whether tenants are still loading */
    isLoading: boolean;
    /** Convenience: returns the base API prefix for tenant-scoped endpoints */
    tenantApiPrefix: string;
}

const TenantContext = createContext<TenantContextValue | undefined>(undefined);

// ─── Provider ────────────────────────────────────────────────

export function TenantProvider({ children }: { children: ReactNode }) {
    const [activeTenant, setActiveTenant] = useState<Tenant | null>(null);

    const { data: tenants = [], isLoading } = useQuery({
        queryKey: ['tenants'],
        queryFn: async () => {
            const res = await api.get<{ data: Tenant[] }>('tenants');
            return res.data.data;
        },
    });

    // Auto-select first tenant if none selected
    useEffect(() => {
        if (!activeTenant && tenants.length > 0) {
            // Try to restore from localStorage
            const storedId = localStorage.getItem('nizam_active_tenant');
            const restored = storedId
                ? tenants.find((t) => t.id === Number(storedId))
                : null;
            setActiveTenant(restored ?? tenants[0]);
        }
    }, [tenants, activeTenant]);

    const switchTenant = useCallback((tenant: Tenant) => {
        setActiveTenant(tenant);
        localStorage.setItem('nizam_active_tenant', String(tenant.id));
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
