import './assets/app.css';
import { QueryClientProvider } from '@tanstack/react-query';
import { lazy, StrictMode, Suspense } from 'react';
import { createRoot } from 'react-dom/client';
import { Navigate, Route, RouterProvider, createBrowserRouter, createRoutesFromElements } from 'react-router-dom';

import ErrorBoundary from '@/components/ErrorBoundary';
import { AuthProvider, useAuth } from '@/context/AuthContext';
import { OrganizationProvider } from '@/context/OrganizationContext';
import { branding, getStoredValue } from '@/lib/branding';
import { queryClient } from '@/lib/query-client';
import { Toaster } from '@/components/ui/sonner';

// ─── Lazy-loaded pages ───────────────────────────────────────
const LoginPage = lazy(() => import('@/pages/auth/LoginPage'));
const SuperadminLayout = lazy(() => import('@/layouts/SuperadminLayout'));
const DashboardPage = lazy(() => import('@/pages/admin/DashboardPage'));
const OrganizationsPage = lazy(() => import('@/pages/admin/OrganizationsPage'));
const OrganizationFormPage = lazy(() => import('@/pages/admin/OrganizationFormPage'));
const OrganizationSettingsPage = lazy(() => import('@/pages/admin/OrganizationSettingsPage'));
const UsersPage = lazy(() => import('@/pages/admin/UsersPage'));
const UserFormPage = lazy(() => import('@/pages/admin/UserFormPage'));
const UserPermissionsPage = lazy(() => import('@/pages/admin/UserPermissionsPage'));
const ExtensionsPage = lazy(() => import('@/pages/admin/ExtensionsPage'));
const ExtensionFormPage = lazy(() => import('@/pages/admin/ExtensionFormPage'));
const ExtensionDetailPage = lazy(() => import('@/pages/admin/ExtensionDetailPage'));
const DeviceProfilesPage = lazy(() => import('@/pages/admin/DeviceProfilesPage'));
const DeviceProfileFormPage = lazy(() => import('@/pages/admin/DeviceProfileFormPage'));
const DirectoryPage = lazy(() => import('@/pages/admin/DirectoryPage'));
const OfficeFeaturesPage = lazy(() => import('@/pages/admin/OfficeFeaturesPage'));
const DidsPage = lazy(() => import('@/pages/admin/DidsPage'));
const DidFormPage = lazy(() => import('@/pages/admin/DidFormPage'));
const FlowsPage = lazy(() => import('@/pages/admin/FlowsPage'));
const FlowEditorPage = lazy(() => import('@/pages/admin/FlowEditorPage'));
const SystemMediaPage = lazy(() => import('@/pages/admin/SystemMediaPage'));
const CallHistoryPage = lazy(() => import('@/pages/admin/CallHistoryPage'));
const InteractionDetailPage = lazy(() => import('@/pages/admin/InteractionDetailPage'));
const AuditLogsPage = lazy(() => import('@/pages/admin/AuditLogsPage'));
const LogViewerPage = lazy(() => import('@/pages/admin/LogViewerPage'));
const SipStatusPage = lazy(() => import('@/pages/admin/SipStatusPage'));
const FreeSwitchModulesPage = lazy(() => import('@/pages/admin/FreeSwitchModulesPage'));
const AuthTokensPage = lazy(() => import('@/pages/admin/AuthTokensPage'));
const CallBlocksPage = lazy(() => import('@/pages/admin/CallBlocksPage'));
const SipProfilesPage = lazy(() => import('@/pages/admin/SipProfilesPage'));
const SipProfileFormPage = lazy(() => import('@/pages/admin/SipProfileFormPage'));
const CapabilitiesPage = lazy(() => import('@/pages/admin/CapabilitiesPage'));
const SystemSettingsPage = lazy(() => import('@/pages/admin/SystemSettingsPage'));

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

function SuperadminOnlyRoute({ children }: { children: React.ReactNode }) {
    const { user, isLoading } = useAuth();

    if (isLoading) return null;

    const isSuperadmin = user?.role === 'superadmin' && !user?.organization_id;

    if (!isSuperadmin) {
        return <Navigate to="/admin" replace />;
    }

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
                            <OrganizationProvider>
                                <SuperadminLayout />
                            </OrganizationProvider>
                        </ErrorBoundary>
                    </ProtectedRoute>
                }
            >
                {/* General */}
                <Route index element={<DashboardPage />} />
                <Route path="organizations" element={<OrganizationsPage />} />
                <Route path="organizations/create" element={<SuperadminOnlyRoute><OrganizationFormPage /></SuperadminOnlyRoute>} />
                <Route path="organizations/:id/edit" element={<SuperadminOnlyRoute><OrganizationFormPage /></SuperadminOnlyRoute>} />
                <Route path="organizations/:id/settings" element={<OrganizationSettingsPage />} />
                <Route path="users" element={<UsersPage />} />
                <Route path="users/create" element={<UserFormPage />} />
                <Route path="users/:id/edit" element={<UserFormPage />} />
                <Route path="users/:id/permissions" element={<UserPermissionsPage />} />

                {/* Phone System (organization-scoped) */}
                <Route path="extensions" element={<ExtensionsPage />} />
                <Route path="extensions/create" element={<ExtensionFormPage />} />
                <Route path="extensions/:id" element={<ExtensionDetailPage />} />
                <Route path="extensions/:id/edit" element={<ExtensionFormPage />} />
                <Route path="device-profiles" element={<DeviceProfilesPage />} />
                <Route path="device-profiles/create" element={<DeviceProfileFormPage />} />
                <Route path="device-profiles/:id/edit" element={<DeviceProfileFormPage />} />
                <Route path="directory" element={<DirectoryPage />} />
                <Route path="office-features" element={<OfficeFeaturesPage />} />
                <Route path="numbers" element={<DidsPage />} />
                <Route path="numbers/create" element={<DidFormPage />} />
                <Route path="numbers/:id/edit" element={<DidFormPage />} />
                <Route path="flows" element={<FlowsPage />} />
                <Route path="flows/create" element={<FlowEditorPage />} />
                <Route path="flows/:id/edit" element={<FlowEditorPage />} />
                <Route path="system-media" element={<SystemMediaPage />} />

                {/* Contact Center (organization-scoped) */}
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

                {/* Call History (organization-scoped) */}
                <Route path="call-history" element={<CallHistoryPage />} />
                <Route path="call-blocks" element={<CallBlocksPage />} />
                <Route path="interactions/:id" element={<InteractionDetailPage />} />

                {/* System */}
                <Route path="capabilities" element={<SuperadminOnlyRoute><CapabilitiesPage /></SuperadminOnlyRoute>} />
                <Route path="system-settings" element={<SuperadminOnlyRoute><SystemSettingsPage /></SuperadminOnlyRoute>} />
                <Route path="logs" element={<AuditLogsPage />} />
                <Route path="system-logs" element={<LogViewerPage />} />
                <Route path="auth-tokens" element={<AuthTokensPage />} />
                <Route path="sip-status" element={<SipStatusPage />} />
                <Route path="freeswitch/modules" element={<SuperadminOnlyRoute><FreeSwitchModulesPage /></SuperadminOnlyRoute>} />
                <Route path="sip-profiles" element={<SipProfilesPage />} />
                <Route path="sip-profiles/create" element={<SipProfileFormPage />} />
                <Route path="sip-profiles/:id/edit" element={<SipProfileFormPage />} />
            </Route>

            {/* Catch-all */}
            <Route path="*" element={<Navigate to="/login" replace />} />
        </>
    )
);

// ─── App ─────────────────────────────────────────────────────

function App() {
    document.title = `${branding.appName} — ${branding.appTagline}`;

    return (
        <Suspense fallback={<PageLoader />}>
            <RouterProvider router={router} />
        </Suspense>
    );
}

// ─── Mount ───────────────────────────────────────────────────

const rootEl = document.getElementById('app');
if (rootEl) {
    const storedTheme = getStoredValue('theme');
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
