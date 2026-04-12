import './assets/app.css';
import { QueryClientProvider } from '@tanstack/react-query';
import { lazy, StrictMode, Suspense } from 'react';
import { createRoot } from 'react-dom/client';
import { Navigate, Route, RouterProvider, createBrowserRouter, createRoutesFromElements } from 'react-router-dom';

import ErrorBoundary from '@/components/ErrorBoundary';
import { AuthProvider, useAuth } from '@/context/AuthContext';
import { TenantProvider } from '@/context/TenantContext';
import { queryClient } from '@/lib/query-client';
import { Toaster } from '@/components/ui/sonner';

// ─── Lazy-loaded pages ───────────────────────────────────────
const LoginPage = lazy(() => import('@/pages/auth/LoginPage'));
const SuperadminLayout = lazy(() => import('@/layouts/SuperadminLayout'));
const DashboardPage = lazy(() => import('@/pages/admin/DashboardPage'));
const TenantsPage = lazy(() => import('@/pages/admin/TenantsPage'));
const TenantFormPage = lazy(() => import('@/pages/admin/TenantFormPage'));
const TenantSettingsPage = lazy(() => import('@/pages/admin/TenantSettingsPage'));
const UsersPage = lazy(() => import('@/pages/admin/UsersPage'));
const UserFormPage = lazy(() => import('@/pages/admin/UserFormPage'));
const UserPermissionsPage = lazy(() => import('@/pages/admin/UserPermissionsPage'));
const ExtensionsPage = lazy(() => import('@/pages/admin/ExtensionsPage'));
const ExtensionFormPage = lazy(() => import('@/pages/admin/ExtensionFormPage'));
const ExtensionDetailPage = lazy(() => import('@/pages/admin/ExtensionDetailPage'));
const GatewaysPage = lazy(() => import('@/pages/admin/GatewaysPage'));
const GatewayFormPage = lazy(() => import('@/pages/admin/GatewayFormPage'));
const DidsPage = lazy(() => import('@/pages/admin/DidsPage'));
const DidFormPage = lazy(() => import('@/pages/admin/DidFormPage'));
const RingGroupsPage = lazy(() => import('@/pages/admin/RingGroupsPage'));
const RingGroupFormPage = lazy(() => import('@/pages/admin/RingGroupFormPage'));
const CdrsPage = lazy(() => import('@/pages/admin/CdrsPage'));
const AuditLogsPage = lazy(() => import('@/pages/admin/AuditLogsPage'));
const LogViewerPage = lazy(() => import('@/pages/admin/LogViewerPage'));
const SipStatusPage = lazy(() => import('@/pages/admin/SipStatusPage'));
const AuthTokensPage = lazy(() => import('@/pages/admin/AuthTokensPage'));
const SipProfilesPage = lazy(() => import('@/pages/admin/SipProfilesPage'));
const SipProfileFormPage = lazy(() => import('@/pages/admin/SipProfileFormPage'));
const BlockedDestinationsPage = lazy(() => import('@/pages/admin/BlockedDestinationsPage'));
const CapabilitiesPage = lazy(() => import('@/pages/admin/CapabilitiesPage'));

// ─── Phase 2 (Contact Center) ────────────────────────────────
const TeamsPage = lazy(() => import('@/pages/admin/TeamsPage'));
const TeamFormPage = lazy(() => import('@/pages/admin/TeamFormPage'));
const AgentsPage = lazy(() => import('@/pages/admin/AgentsPage'));
const AgentFormPage = lazy(() => import('@/pages/admin/AgentFormPage'));
const QueuesPage = lazy(() => import('@/pages/admin/QueuesPage'));
const QueueFormPage = lazy(() => import('@/pages/admin/QueueFormPage'));
const QueueDetailPage = lazy(() => import('@/pages/admin/QueueDetailPage'));

// ─── Route Guards ────────────────────────────────────────────

function ProtectedRoute({ children }: { children: React.ReactNode }) {
    const { isAuthenticated, isLoading } = useAuth();

    if (isLoading) {
        return (
            <div className="flex h-dvh items-center justify-center bg-background">
                <div className="flex flex-col items-center gap-4">
                    <div className="size-8 motion-safe:animate-spin rounded-full border-2 border-primary border-t-transparent" />
                    <p className="text-sm text-muted-foreground">Loading…</p>
                </div>
            </div>
        );
    }

    if (!isAuthenticated) {
        return <Navigate to="/login" replace />;
    }

    return <>{children}</>;
}

function GuestRoute({ children }: { children: React.ReactNode }) {
    const { isAuthenticated, isLoading } = useAuth();

    if (isLoading) return null;
    if (isAuthenticated) return <Navigate to="/admin" replace />;

    return <>{children}</>;
}

// ─── Loading Fallback ────────────────────────────────────────

function PageLoader() {
    return (
        <div className="flex h-dvh items-center justify-center bg-background">
            <div
                className="size-8 motion-safe:animate-spin rounded-full border-2 border-primary border-t-transparent"
                aria-label="Loading page"
            />
        </div>
    );
}

// ─── Router Configuration ────────────────────────────────────────

const router = createBrowserRouter(
    createRoutesFromElements(
        <>
            {/* Guest */}
            <Route
                path="/login"
                element={
                    <GuestRoute>
                        <LoginPage />
                    </GuestRoute>
                }
            />

            {/* Protected Admin */}
            <Route
                path="/admin"
                element={
                    <ProtectedRoute>
                        <ErrorBoundary>
                            <TenantProvider>
                                <SuperadminLayout />
                            </TenantProvider>
                        </ErrorBoundary>
                    </ProtectedRoute>
                }
            >
                {/* General */}
                <Route index element={<DashboardPage />} />
                <Route path="tenants" element={<TenantsPage />} />
                <Route path="tenants/create" element={<TenantFormPage />} />
                <Route path="tenants/:id/edit" element={<TenantFormPage />} />
                <Route path="tenants/:id/settings" element={<TenantSettingsPage />} />
                <Route path="users" element={<UsersPage />} />
                <Route path="users/create" element={<UserFormPage />} />
                <Route path="users/:id/edit" element={<UserFormPage />} />
                <Route path="users/:id/permissions" element={<UserPermissionsPage />} />

                {/* Phone System (tenant-scoped) */}
                <Route path="extensions" element={<ExtensionsPage />} />
                <Route path="extensions/create" element={<ExtensionFormPage />} />
                <Route path="extensions/:id" element={<ExtensionDetailPage />} />
                <Route path="extensions/:id/edit" element={<ExtensionFormPage />} />
                <Route path="ring-groups" element={<RingGroupsPage />} />
                <Route path="ring-groups/create" element={<RingGroupFormPage />} />
                <Route path="ring-groups/:id/edit" element={<RingGroupFormPage />} />
                <Route path="dids" element={<DidsPage />} />
                <Route path="dids/create" element={<DidFormPage />} />
                <Route path="dids/:id/edit" element={<DidFormPage />} />

                {/* Contact Center (tenant-scoped) */}
                <Route path="teams" element={<TeamsPage />} />
                <Route path="teams/create" element={<TeamFormPage />} />
                <Route path="teams/:id/edit" element={<TeamFormPage />} />
                <Route path="agents" element={<AgentsPage />} />
                <Route path="agents/create" element={<AgentFormPage />} />
                <Route path="agents/:id/edit" element={<AgentFormPage />} />
                <Route path="queues" element={<QueuesPage />} />
                <Route path="queues/create" element={<QueueFormPage />} />
                <Route path="queues/:id/edit" element={<QueueFormPage />} />
                <Route path="queues/:id" element={<QueueDetailPage />} />

                {/* Connectivity (tenant-scoped) */}
                <Route path="gateways" element={<GatewaysPage />} />
                <Route path="gateways/create" element={<GatewayFormPage />} />
                <Route path="gateways/:id/edit" element={<GatewayFormPage />} />

                {/* Calls (tenant-scoped) */}
                <Route path="cdrs" element={<CdrsPage />} />

                {/* System */}
                <Route path="capabilities" element={<CapabilitiesPage />} />
                <Route path="logs" element={<AuditLogsPage />} />
                <Route path="system-logs" element={<LogViewerPage />} />
                <Route path="auth-tokens" element={<AuthTokensPage />} />
                <Route path="sip-status" element={<SipStatusPage />} />
                <Route path="sip-profiles" element={<SipProfilesPage />} />
                <Route path="sip-profiles/create" element={<SipProfileFormPage />} />
                <Route path="sip-profiles/:id/edit" element={<SipProfileFormPage />} />
                <Route path="blocked-destinations" element={<BlockedDestinationsPage />} />
            </Route>

            {/* Catch-all */}
            <Route path="*" element={<Navigate to="/login" replace />} />
        </>
    )
);

// ─── App ─────────────────────────────────────────────────────

function App() {
    return (
        <Suspense fallback={<PageLoader />}>
            <RouterProvider router={router} />
        </Suspense>
    );
}

// ─── Mount ───────────────────────────────────────────────────

const rootEl = document.getElementById('app');
if (rootEl) {
    const themeKey = 'platform-theme';
    const storedTheme = localStorage.getItem(themeKey);
    if (storedTheme === 'dark') {
        document.documentElement.classList.add('dark');
    }

    createRoot(rootEl).render(
        <StrictMode>
            <ErrorBoundary>
                <QueryClientProvider client={queryClient}>
                    <AuthProvider>
                        <App />
                    </AuthProvider>
                    <Toaster position="top-right" richColors />
                </QueryClientProvider>
            </ErrorBoundary>
        </StrictMode>,
    );
}
