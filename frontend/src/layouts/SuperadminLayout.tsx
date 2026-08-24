import {
    BookUser,
    Building2,
    ChartColumn,
    Check,
    ChevronLeft,
    ChevronRight,
    ChevronsUpDown,
    ClipboardList,
    FileAudio,
    FileText,
    Gauge,
    Globe,
    Hash,
    KeyRound,
    Laptop,
    LayoutDashboard,
    LogOut,
    Menu,
    Moon,
    Phone,
    PhoneCall,
    Radio,
    Route,
    ScrollText,
    Shield,
    ShieldCheck,
    SlidersHorizontal,
    Sparkles,
    Sun,
    Users,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { Link, Outlet, useLocation, useNavigate } from 'react-router-dom';

import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Separator } from '@/components/ui/separator';
import { useAuth } from '@/context/AuthContext';
import { useOrganization } from '@/context/OrganizationContext';
import { branding, setStoredValue } from '@/lib/branding';
import { cn } from '@/lib/utils';

// ─── Navigation Structure (Stitch Design) ────────────────────

interface NavItem {
    label: string | ((activeOrganization: boolean) => string);
    icon: React.ComponentType<{ className?: string }>;
    href: string;
    superadminOnly?: boolean;
    adminOnly?: boolean;
    organizationRequired?: boolean;
}

interface NavSection {
    title: string;
    items: NavItem[];
}

const NAV_SECTIONS: NavSection[] = [
    {
        title: 'General',
        items: [
            { label: 'Dashboard', icon: LayoutDashboard, href: '/admin' },
            { label: 'Organizations', icon: Building2, href: '/admin/organizations', superadminOnly: true },
            { label: 'Users', icon: Users, href: '/admin/users', adminOnly: true, organizationRequired: true },
        ],
    },
    {
        title: 'Phone System',
        items: [
            { label: 'Extensions', icon: Phone, href: '/admin/extensions', organizationRequired: true },
            { label: 'Devices', icon: Laptop, href: '/admin/device-profiles', organizationRequired: true },
            { label: 'Directory', icon: BookUser, href: '/admin/directory', organizationRequired: true },
            { label: 'Office Features', icon: SlidersHorizontal, href: '/admin/office-features', organizationRequired: true },
            { label: 'Call Flows', icon: Route, href: '/admin/flows', organizationRequired: true },
            { label: 'Media Library', icon: FileText, href: '/admin/system-media', organizationRequired: true },
            { label: 'Numbers', icon: Hash, href: '/admin/numbers', adminOnly: true, organizationRequired: true },
        ],
    },
    {
        title: 'Contact Center',
        items: [
            { label: 'Queues', icon: PhoneCall, href: '/admin/queues', organizationRequired: true },
            { label: 'Teams', icon: Users, href: '/admin/teams', organizationRequired: true },
            { label: 'Agents', icon: ShieldCheck, href: '/admin/agents', organizationRequired: true },
        ],
    },
    {
        title: 'Calls',
        items: [
            { label: 'Call History', icon: PhoneCall, href: '/admin/call-history', organizationRequired: true },
            { label: 'Recordings', icon: FileAudio, href: '/admin/recordings', organizationRequired: true },
        ],
    },
    {
        title: 'Reports',
        items: [
            { label: 'Call Analytics', icon: ChartColumn, href: '/admin/reports/call-analytics', organizationRequired: true },
            { label: 'Supervisor', icon: ClipboardList, href: '/admin/reports/supervisor', organizationRequired: true },
            { label: 'Usage', icon: Gauge, href: '/admin/reports/usage', organizationRequired: true },
        ],
    },
    {
        title: 'Security',
        items: [
            { label: 'Auth Tokens', icon: KeyRound, href: '/admin/auth-tokens', adminOnly: true, organizationRequired: true },
            { label: 'Call Block', icon: Shield, href: '/admin/call-blocks', adminOnly: true, organizationRequired: true },
            { label: 'SIP Profiles', icon: Radio, href: '/admin/sip-profiles', superadminOnly: true },
        ],
    },
    {
        title: 'System',
        items: [
            { label: 'Capabilities', icon: Sparkles, href: '/admin/capabilities', superadminOnly: true },
            { label: 'System Settings', icon: Globe, href: '/admin/system-settings', superadminOnly: true },
            { label: 'FreeSWITCH Modules', icon: Radio, href: '/admin/freeswitch/modules', superadminOnly: true },
            { label: 'Audit Logs', icon: ScrollText, href: '/admin/logs', adminOnly: true, organizationRequired: true },
            { label: 'System Logs', icon: FileText, href: '/admin/system-logs', superadminOnly: true },
            { label: 'SIP Status', icon: Radio, href: '/admin/sip-status', superadminOnly: true },
        ],
    },
];

// ─── Layout Component ────────────────────────────────────────

export default function SuperadminLayout() {
    const { user, logout } = useAuth();
    const { organizations, activeOrganization, switchOrganization } = useOrganization();
    const platformName = branding.appName;
    const location = useLocation();
    const navigate = useNavigate();
    const [collapsed, setCollapsed] = useState(false);
    const [mobileNavOpen, setMobileNavOpen] = useState(false);
    const [isDark, setIsDark] = useState(
        () => document.documentElement.classList.contains('dark'),
    );

    const isSuperadmin = user?.role === 'superadmin' && !user?.organization_id;
    const isAdmin = user?.role === 'admin';
    const roleLabel = isSuperadmin
        ? 'Platform Admin'
        : isAdmin
            ? 'Organization Admin'
            : user?.role ?? 'User';

    const filteredSections = NAV_SECTIONS.map((section) => ({
        ...section,
        items: section.items.filter((item) => {
            if (item.superadminOnly && !isSuperadmin) return false;
            if (item.adminOnly && !isAdmin && !isSuperadmin) return false;
            if (item.organizationRequired && !activeOrganization) return false;
            return true;
        }),
    })).filter((section) => section.items.length > 0);

    const toggleTheme = () => {
        const next = !isDark;
        setIsDark(next);
        document.documentElement.classList.toggle('dark', next);
        setStoredValue('theme', next ? 'dark' : 'light');
    };

    const handleLogout = async () => {
        await logout();
        navigate('/login', { replace: true });
    };

    const initials = user?.name
        ?.split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2) ?? 'SA';

    const handleNavigate = () => {
        setMobileNavOpen(false);
    };

    return (
        <div className="flex h-dvh overflow-hidden bg-background">
            {mobileNavOpen && (
                <button
                    type="button"
                    aria-label="Close navigation menu"
                    className="fixed inset-0 z-40 bg-black/50 lg:hidden"
                    onClick={() => setMobileNavOpen(false)}
                />
            )}

            {/* ─── Sidebar ────────────────────────────────── */}
            <aside
                className={cn(
                    'fixed inset-y-0 left-0 z-50 flex flex-col border-r bg-sidebar transition-transform duration-200 ease-out lg:static lg:z-auto lg:translate-x-0 lg:transition-all',
                    collapsed ? 'lg:w-17' : 'lg:w-64',
                    mobileNavOpen ? 'translate-x-0 w-72' : '-translate-x-full w-72',
                    activeOrganization ? 'border-primary/20' : ''
                )}
            >
                {/* Brand */}
                <div className={cn(
                    "flex h-14 items-center gap-3 px-4",
                    activeOrganization ? "bg-primary/5" : ""
                )}>
                    <div className={cn(
                        "flex size-8 shrink-0 items-center justify-center rounded-lg text-primary-foreground",
                        activeOrganization ? "bg-primary" : "bg-sidebar-primary"
                    )}>
                        {activeOrganization ? <Building2 className="size-4" /> : <Shield className="size-4" />}
                    </div>
                    {!collapsed && (
                        <div className="flex flex-col min-w-0">
                            <span className="truncate text-sm font-bold tracking-tight text-sidebar-foreground">
                                {activeOrganization ? activeOrganization.name : platformName}
                            </span>
                            <p className="truncate text-[10px] leading-none text-muted-foreground mt-1">
                                {activeOrganization ? 'ORGANIZATION MANAGEMENT' : 'GLOBAL PLATFORM CONTROL'}
                            </p>
                        </div>
                    )}
                </div>

                <Separator />

                {/* Navigation — grouped sections */}
                <nav className="flex-1 overflow-y-auto px-3 py-3">
                    {filteredSections.map((section) => (
                        <div key={section.title} className="mb-4">
                            <p
                                className={cn(
                                    'mb-1 px-3 text-[10px] font-semibold uppercase text-muted-foreground',
                                    collapsed && 'lg:hidden',
                                )}
                            >
                                {section.title}
                            </p>
                            {section.items.map((item) => {
                                const isActive =
                                    location.pathname === item.href ||
                                    (item.href !== '/admin' &&
                                        location.pathname.startsWith(item.href));
                                return (
                                    <Link
                                        key={item.href}
                                        to={item.href}
                                        onClick={handleNavigate}
                                        className={cn(
                                            'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-all',
                                            isActive
                                                ? 'bg-sidebar-primary text-sidebar-primary-foreground shadow-sm'
                                                : 'text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                                            collapsed && 'lg:justify-center lg:px-0',
                                        )}
                                    >
                                        <item.icon className="size-4 shrink-0" />
                                        <span className={cn(collapsed && 'lg:hidden')}>
                                            {typeof item.label === 'function' ? item.label(!!activeOrganization) : item.label}
                                        </span>
                                    </Link>
                                );
                            })}
                        </div>
                    ))}
                </nav>

                {/* Footer Controls */}
                <div className="space-y-1 border-t px-3 py-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={toggleTheme}
                        className={cn('w-full cursor-pointer', collapsed ? 'lg:px-0' : 'justify-start')}
                    >
                        {isDark ? (
                            <Sun className="size-4 shrink-0" />
                        ) : (
                            <Moon className="size-4 shrink-0" />
                        )}
                        <span className={cn('text-xs', collapsed && 'lg:hidden')}>
                            {isDark ? 'Light' : 'Dark'}
                        </span>
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => setCollapsed(!collapsed)}
                        aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                        className="mx-auto hidden cursor-pointer lg:flex"
                    >
                        {collapsed ? (
                            <ChevronRight className="size-4" />
                        ) : (
                            <ChevronLeft className="size-4" />
                        )}
                    </Button>
                </div>

                <Separator />

                {/* User */}
                <div className="p-3">
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button
                                type="button"
                                className={cn(
                                    'flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left transition-colors hover:bg-sidebar-accent',
                                    collapsed && 'lg:justify-center lg:px-0',
                                )}
                            >
                                <Avatar className="size-7">
                                    <AvatarFallback className="bg-primary/10 text-primary text-[10px] font-semibold">
                                        {initials}
                                    </AvatarFallback>
                                </Avatar>
                                <div className={cn('min-w-0 flex-1', collapsed && 'lg:hidden')}>
                                    <p className="truncate text-xs font-medium text-sidebar-foreground">
                                        {user?.name}
                                    </p>
                                    <p className="truncate text-[10px] text-muted-foreground">
                                        {roleLabel}
                                    </p>
                                </div>
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" side="top" className="w-56">
                            <DropdownMenuLabel className="font-normal">
                                <div className="flex flex-col space-y-1">
                                    <p className="text-sm font-medium">{user?.name}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {user?.email}
                                    </p>
                                </div>
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem onClick={handleLogout} variant="destructive">
                                <LogOut className="mr-2 size-4" />
                                Sign Out
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </aside>

            {/* ─── Main Area ──────────────────────────────── */}
            <div className="flex min-w-0 flex-1 flex-col overflow-hidden">
                {/* Top Header Bar with Organization Switcher */}
                <header className="flex h-14 items-center justify-between gap-3 border-b bg-background px-4 lg:px-6">
                    <div className="flex min-w-0 items-center gap-3">
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={mobileNavOpen ? 'Close navigation menu' : 'Open navigation menu'}
                            className="cursor-pointer lg:hidden"
                            onClick={() => setMobileNavOpen((open) => !open)}
                        >
                            {mobileNavOpen ? (
                                <X className="size-4" />
                            ) : (
                                <Menu className="size-4" />
                            )}
                        </Button>
                        <div className="flex flex-col">
                            <span className="truncate text-sm font-semibold text-foreground">
                                {platformName}
                            </span>
                            <span className={cn(
                                "text-[10px] font-medium tracking-wider uppercase",
                                activeOrganization ? "text-primary" : "text-muted-foreground"
                            )}>
                                {activeOrganization ? `Organization: ${activeOrganization.name}` : 'Platform Admin'}
                            </span>
                        </div>
                    </div>

                    {/* Organization Switcher (FusionPBX-style) */}
                    {isSuperadmin ? (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="max-w-48 gap-2 font-normal sm:max-w-64"
                                >
                                    <Building2 className="size-4 text-primary" />
                                    <span className="max-w-50 truncate">
                                        {activeOrganization?.name ?? 'Select Organization'}
                                    </span>
                                    <ChevronsUpDown className="size-3 text-muted-foreground" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-64">
                                <DropdownMenuLabel>Switch Organization</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    onClick={() => {
                                        switchOrganization(null);
                                        navigate('/admin');
                                    }}
                                >
                                    <div className="flex flex-1 items-center justify-between">
                                        <div>
                                            <p className="text-sm font-medium">Platform Admin</p>
                                            <p className="text-xs text-muted-foreground">Global Scope</p>
                                        </div>
                                        {!activeOrganization && (
                                            <Check className="size-4 text-primary" />
                                        )}
                                    </div>
                                </DropdownMenuItem>
                                {organizations.length > 0 && <DropdownMenuSeparator />}
                                {organizations.map((organization) => (
                                    <DropdownMenuItem
                                        key={organization.id}
                                        onClick={() => {
                                            switchOrganization(organization);
                                            if (location.pathname === '/admin/organizations') {
                                                navigate('/admin');
                                            }
                                        }}
                                    >
                                        <div className="flex flex-1 items-center justify-between">
                                            <div>
                                                <p className="text-sm font-medium">
                                                    {organization.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {organization.domain}
                                                </p>
                                            </div>
                                            {activeOrganization?.id === organization.id && (
                                                <Check className="size-4 text-primary" />
                                            )}
                                        </div>
                                    </DropdownMenuItem>
                                ))}
                                {organizations.length === 0 && (
                                    <DropdownMenuItem disabled>
                                        No organizations available
                                    </DropdownMenuItem>
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    ) : (
                        <div className="flex max-w-48 items-center gap-2 rounded-md border bg-muted/50 px-3 py-1.5 text-sm font-medium sm:max-w-64">
                            <Building2 className="size-4 shrink-0 text-primary" />
                            <span className="truncate">
                                {activeOrganization?.name ?? platformName}
                            </span>
                        </div>
                    )}
                </header>

                {/* Page Content */}
                <main className="min-w-0 flex-1 overflow-y-auto">
                    <Outlet />
                </main>
            </div>
        </div>
    );
}
