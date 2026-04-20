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
import { getStoredValue, removeStoredValue, setStoredValue } from '@/lib/branding';
import api from '@/lib/api';
import type { Organization } from '@/types/models';
import { useAuth } from './AuthContext';

interface OrganizationContextValue {
    organizations: Organization[];
    activeOrganization: Organization | null;
    switchOrganization: (organization: Organization | null) => void;
    isLoading: boolean;
    organizationApiPrefix: string;
}

const OrganizationContext = createContext<OrganizationContextValue | undefined>(undefined);

export function OrganizationProvider({ children }: { children: ReactNode }) {
    const [activeOrganization, setActiveOrganization] = useState<Organization | null>(null);

    const { user } = useAuth();

    const { data: organizations = [], isLoading } = useQuery({
        queryKey: ['organizations'],
        queryFn: async () => {
            const res = await api.get<{ data: Organization[] }>('organizations');
            return res.data.data;
        },
    });

    useEffect(() => {
        if (!activeOrganization && organizations.length > 0) {
            const storedId = getStoredValue('activeOrganization');
            const restored = storedId
                ? organizations.find((organization) => String(organization.id) === storedId)
                : null;

            if (restored) {
                setActiveOrganization(restored);
            } else if (user?.organization_id) {
                setActiveOrganization(organizations[0]);
            }
        }
    }, [organizations, activeOrganization, user]);

    const switchOrganization = useCallback((organization: Organization | null) => {
        setActiveOrganization(organization);
        if (organization) {
            setStoredValue('activeOrganization', String(organization.id));
        } else {
            removeStoredValue('activeOrganization');
        }
    }, []);

    const organizationApiPrefix = activeOrganization
        ? `organizations/${activeOrganization.id}`
        : '';

    const value = useMemo<OrganizationContextValue>(
        () => ({
            organizations,
            activeOrganization,
            switchOrganization,
            isLoading,
            organizationApiPrefix,
        }),
        [organizations, activeOrganization, switchOrganization, isLoading, organizationApiPrefix],
    );

    return (
        <OrganizationContext.Provider value={value}>
            {children}
        </OrganizationContext.Provider>
    );
}

export function useOrganization(): OrganizationContextValue {
    const context = useContext(OrganizationContext);
    if (!context) {
        throw new Error('useOrganization must be used within a OrganizationProvider');
    }
    return context;
}
