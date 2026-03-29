import { QueryClientProvider } from '@tanstack/react-query';
import { lazy, StrictMode, Suspense } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';

import { AuthProvider, useAuth } from '@/context/AuthContext';
import { TenantProvider } from '@/context/TenantContext';
import { queryClient } from '@/lib/query-client';

// ─── Lazy-loaded pages ───────────────────────────────────────
const LoginPage = lazy(() => import('@/pages/auth/LoginPage'));
const SuperadminLayout = lazy(() => import('@/layouts/SuperadminLayout'));
const DashboardPage = lazy(() => import('@/pages/admin/DashboardPage'));
const TenantsPage = lazy(() => import('@/pages/admin/TenantsPage'));
const UsersPage = lazy(() => import('@/pages/admin/UsersPage'));
const ExtensionsPage = lazy(() => import('@/pages/admin/ExtensionsPage'));
const GatewaysPage = lazy(() => import('@/pages/admin/GatewaysPage'));
const GatewayFormPage = lazy(() => import('@/pages/admin/GatewayFormPage'));
const DidsPage = lazy(() => import('@/pages/admin/DidsPage'));
const DidFormPage = lazy(() => import('@/pages/admin/DidFormPage'));
const RingGroupsPage = lazy(() => import('@/pages/admin/RingGroupsPage'));
const CdrsPage = lazy(() => import('@/pages/admin/CdrsPage'));
const AuditLogsPage = lazy(() => import('@/pages/admin/AuditLogsPage'));
const LogViewerPage = lazy(() => import('@/pages/admin/LogViewerPage'));
const SipStatusPage = lazy(() => import('@/pages/admin/SipStatusPage'));

// ─── Route Guards ────────────────────────────────────────────

function ProtectedRoute({ children }: { children: React.ReactNode }) {
    const { isAuthenticated, isLoading } = useAuth();

    if (isLoading) {
        return (
            <div className="flex h-screen items-center justify-center bg-background">
                <div className="flex flex-col items-center gap-4">
                    <div className="size-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
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
        <div className="flex h-screen items-center justify-center bg-background">
            <div className="size-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
        </div>
    );
}

// ─── App ─────────────────────────────────────────────────────

function App() {
    return (
        <Suspense fallback={<PageLoader />}>
            <Routes>
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
                            <TenantProvider>
                                <SuperadminLayout />
                            </TenantProvider>
                        </ProtectedRoute>
                    }
                >
                    {/* General */}
                    <Route index element={<DashboardPage />} />
                    <Route path="tenants" element={<TenantsPage />} />
                    <Route path="users" element={<UsersPage />} />

                    {/* Phone System (tenant-scoped) */}
                    <Route path="extensions" element={<ExtensionsPage />} />
                    <Route path="ring-groups" element={<RingGroupsPage />} />
                    <Route path="dids" element={<DidsPage />} />
                    <Route path="dids/create" element={<DidFormPage />} />
                    <Route path="dids/:id/edit" element={<DidFormPage />} />

                    {/* Connectivity (tenant-scoped) */}
                    <Route path="gateways" element={<GatewaysPage />} />
                    <Route path="gateways/create" element={<GatewayFormPage />} />
                    <Route path="gateways/:id/edit" element={<GatewayFormPage />} />

                    {/* Calls (tenant-scoped) */}
                    <Route path="cdrs" element={<CdrsPage />} />

                    {/* System */}
                    <Route path="logs" element={<AuditLogsPage />} />
                    <Route path="system-logs" element={<LogViewerPage />} />
                    <Route path="sip-status" element={<SipStatusPage />} />
                </Route>

                {/* Catch-all */}
                <Route path="*" element={<Navigate to="/login" replace />} />
            </Routes>
        </Suspense>
    );
}

// ─── Mount ───────────────────────────────────────────────────

const rootEl = document.getElementById('app');
if (rootEl) {
    const storedTheme = localStorage.getItem('nizam-theme');
    if (storedTheme === 'dark') {
        document.documentElement.classList.add('dark');
    }

    createRoot(rootEl).render(
        <StrictMode>
            <QueryClientProvider client={queryClient}>
                <BrowserRouter>
                    <AuthProvider>
                        <App />
                    </AuthProvider>
                </BrowserRouter>
            </QueryClientProvider>
        </StrictMode>,
    );
}
