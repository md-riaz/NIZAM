import { useQuery } from '@tanstack/react-query';
import { ArrowRight, Radio, Sparkles } from 'lucide-react';

import CapabilityCard, { type Capability } from '@/components/admin/CapabilityCard';
import { PageHeader } from '@/components/scaffolds/PageHeader';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import api from '@/lib/api';
import { Link } from 'react-router-dom';

interface CapabilitiesResponse {
    data: Capability[];
}

export default function CapabilitiesPage() {
    const {
        data: capabilities = [],
        isLoading,
        isError,
        error,
    } = useQuery({
        queryKey: ['admin-capabilities'],
        queryFn: async () => {
            const res = await api.get<CapabilitiesResponse>('admin/capabilities');
            return res.data.data;
        },
    });

    const activeCount = capabilities.filter((capability) => capability.status === 'active').length;

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <PageHeader
                title="Capabilities"
                description="Platform enhancements and PBX features currently available across the system."
                breadcrumbs="Platform Admin › System"
            />

            <Card className="border-border/70 bg-gradient-to-br from-card via-card to-primary/5">
                <CardHeader className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-1.5">
                        <div className="flex items-center gap-2 text-sm font-medium text-primary">
                            <Sparkles className="size-4" />
                            Platform Capability Registry
                        </div>
                        <CardTitle className="text-xl">System feature overview</CardTitle>
                        <CardDescription className="max-w-2xl leading-6">
                            Review which advanced routing, security, and performance features are enabled in the platform.
                        </CardDescription>
                    </div>
                    <div className="flex items-center gap-2 rounded-lg border bg-background/80 px-3 py-2 text-sm">
                        <Radio className="size-4 text-primary" />
                        <span className="font-medium text-foreground">{activeCount}</span>
                        <span className="text-muted-foreground">active capabilities</span>
                    </div>
                </CardHeader>
            </Card>

            <Card className="border-border/70">
                <CardHeader className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div className="space-y-1">
                        <CardTitle className="text-lg">FreeSWITCH runtime modules</CardTitle>
                        <CardDescription className="max-w-2xl leading-6">
                            Inspect platform-level module availability and runtime status from the dedicated FreeSWITCH modules page.
                        </CardDescription>
                    </div>
                    <Button asChild variant="outline" className="w-full md:w-auto">
                        <Link to="/admin/freeswitch/modules">
                            Open modules page
                            <ArrowRight className="size-4" />
                        </Link>
                    </Button>
                </CardHeader>
            </Card>

            {isLoading ? (
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {Array.from({ length: 6 }).map((_, index) => (
                        <Card key={index} className="h-full animate-pulse border-border/60">
                            <CardHeader>
                                <div className="h-5 w-24 rounded bg-muted" />
                                <div className="h-6 w-40 rounded bg-muted" />
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2">
                                    <div className="h-4 w-full rounded bg-muted" />
                                    <div className="h-4 w-11/12 rounded bg-muted" />
                                    <div className="h-4 w-3/4 rounded bg-muted" />
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            ) : isError ? (
                <Card>
                    <CardContent className="py-10">
                        <div className="space-y-2 text-center">
                            <p className="text-base font-semibold">Unable to load capabilities</p>
                            <p className="text-sm text-muted-foreground">
                                {error instanceof Error
                                    ? error.message
                                    : 'The capability registry could not be retrieved.'}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            ) : capabilities.length === 0 ? (
                <Card>
                    <CardContent className="py-10">
                        <div className="space-y-2 text-center">
                            <p className="text-base font-semibold">No capabilities found</p>
                            <p className="text-sm text-muted-foreground">
                                The platform did not return any registered capabilities.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            ) : (
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {capabilities.map((capability) => (
                        <CapabilityCard key={capability.id} capability={capability} />
                    ))}
                </div>
            )}
        </div>
    );
}
