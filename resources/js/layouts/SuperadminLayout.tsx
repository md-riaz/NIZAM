import { useState } from 'react';
import { Link, Outlet, useLocation, useNavigate } from 'react-router-dom';
import {
    LayoutDashboard,
    Building2,
    ScrollText,
    Settings,
    LogOut,
    ChevronLeft,
    ChevronRight,
    Moon,
    Sun,
    Shield,
    Phone,
    Globe,
    Hash,
    PhoneCall,
    GitBranch,
    Users,
    ChevronsUpDown,
    Check,
} from 'lucide-react';

import { useAuth } from '@/context/AuthContext';
import { useTenant } from '@/context/TenantContext';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

// ─── Navigation Structure (Stitch Design) ────────────────────

interface NavItem {
    label: string;
    icon: React.ComponentType<{ className?: string }>;
    href: string;
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
            { label: 'Tenants', icon: Building2, href: '/admin/tenants' },
            { label: 'Users', icon: Users, href: '/admin/users' },
        ],
    },
    {
        title: 'Phone System',
        items: [
            { label: 'Extensions', icon: Phone, href: '/admin/extensions' },
            { label: 'Ring Groups', icon: GitBranch, href: '/admin/ring-groups' },
            { label: 'DIDs', icon: Hash, href: '/admin/dids' },
        ],
    },
    {
        title: 'Connectivity',
        items: [
            { label: 'Gateways', icon: Globe, href: '/admin/gateways' },
        ],
    },
    {
        title: 'Calls',
        items: [
            { label: 'CDRs', icon: PhoneCall, href: '/admin/cdrs' },
        ],
    },
    {
        title: 'System',
        items: [
            { label: 'Audit Logs', icon: ScrollText, href: '/admin/logs' },
            { label: 'Settings', icon: Settings, href: '/admin/settings' },
        ],
    },
];

// ─── Layout Component ────────────────────────────────────────

export default function SuperadminLayout() {
    const { user, logout } = useAuth();
    const { tenants, activeTenant, switchTenant } = useTenant();
    const location = useLocation();
    const navigate = useNavigate();
    const [collapsed, setCollapsed] = useState(false);
    const [isDark, setIsDark] = useState(
        () => document.documentElement.classList.contains('dark'),
    );

    const toggleTheme = () => {
        const next = !isDark;
        setIsDark(next);
        document.documentElement.classList.toggle('dark', next);
        localStorage.setItem('nizam-theme', next ? 'dark' : 'light');
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

    return (
        <div className="flex h-screen overflow-hidden bg-background">
            {/* ─── Sidebar ────────────────────────────────── */}
            <aside
                className={cn(
                    'flex flex-col border-r bg-sidebar transition-all duration-300',
                    collapsed ? 'w-[68px]' : 'w-64',
                )}
            >
                {/* Brand */}
                <div className="flex h-14 items-center gap-3 px-4">
                    <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                        <Shield className="size-4" />
                    </div>
                    {!collapsed && (
                        <div>
                            <span className="text-sm font-bold tracking-tight text-sidebar-foreground">
                                NIZAM
                            </span>
                            <p className="text-[10px] leading-none text-muted-foreground">
                                COMMUNICATIONS CONTROL
                            </p>
                        </div>
                    )}
                </div>

                <Separator />

                {/* Navigation — grouped sections */}
                <nav className="flex-1 overflow-y-auto px-3 py-3">
                    {NAV_SECTIONS.map((section) => (
                        <div key={section.title} className="mb-4">
                            {!collapsed && (
                                <p className="mb-1 px-3 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                                    {section.title}
                                </p>
                            )}
                            {section.items.map((item) => {
                                const isActive =
                                    location.pathname === item.href ||
                                    (item.href !== '/admin' &&
                                        location.pathname.startsWith(item.href));
                                return (
                                    <Link
                                        key={item.href}
                                        to={item.href}
                                        className={cn(
                                            'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-all',
                                            isActive
                                                ? 'bg-sidebar-primary text-sidebar-primary-foreground shadow-sm'
                                                : 'text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                                            collapsed && 'justify-center px-0',
                                        )}
                                    >
                                        <item.icon className="size-4 shrink-0" />
                                        {!collapsed && <span>{item.label}</span>}
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
                        className={cn('w-full', collapsed ? 'px-0' : 'justify-start')}
                    >
                        {isDark ? (
                            <Sun className="size-4 shrink-0" />
                        ) : (
                            <Moon className="size-4 shrink-0" />
                        )}
                        {!collapsed && (
                            <span className="text-xs">{isDark ? 'Light' : 'Dark'}</span>
                        )}
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => setCollapsed(!collapsed)}
                        className="mx-auto flex"
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
                                className={cn(
                                    'flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left transition-colors hover:bg-sidebar-accent',
                                    collapsed && 'justify-center px-0',
                                )}
                            >
                                <Avatar className="size-7">
                                    <AvatarFallback className="bg-primary/10 text-primary text-[10px] font-semibold">
                                        {initials}
                                    </AvatarFallback>
                                </Avatar>
                                {!collapsed && (
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-xs font-medium text-sidebar-foreground">
                                            {user?.name}
                                        </p>
                                        <p className="truncate text-[10px] text-muted-foreground">
                                            {user?.role}
                                        </p>
                                    </div>
                                )}
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
            <div className="flex flex-1 flex-col overflow-hidden">
                {/* Top Header Bar with Tenant Switcher */}
                <header className="flex h-14 items-center justify-between border-b bg-background px-6">
                    <div className="flex items-center gap-4">
                        {/* Breadcrumb-style page context */}
                        <span className="text-sm text-muted-foreground">
                            NIZAM Admin
                        </span>
                    </div>

                    {/* Tenant Switcher (FusionPBX-style) */}
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="outline"
                                size="sm"
                                className="gap-2 font-normal"
                            >
                                <Building2 className="size-4 text-primary" />
                                <span className="max-w-[200px] truncate">
                                    {activeTenant?.name ?? 'Select Tenant'}
                                </span>
                                <ChevronsUpDown className="size-3 text-muted-foreground" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-64">
                            <DropdownMenuLabel>Switch Tenant</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            {tenants.map((tenant) => (
                                <DropdownMenuItem
                                    key={tenant.id}
                                    onClick={() => switchTenant(tenant)}
                                >
                                    <div className="flex flex-1 items-center justify-between">
                                        <div>
                                            <p className="text-sm font-medium">
                                                {tenant.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {tenant.domain}
                                            </p>
                                        </div>
                                        {activeTenant?.id === tenant.id && (
                                            <Check className="size-4 text-primary" />
                                        )}
                                    </div>
                                </DropdownMenuItem>
                            ))}
                            {tenants.length === 0 && (
                                <DropdownMenuItem disabled>
                                    No tenants available
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </header>

                {/* Page Content */}
                <main className="flex-1 overflow-y-auto">
                    <Outlet />
                </main>
            </div>
        </div>
    );
}
