import { useQuery } from '@tanstack/react-query';
import { Edit, GitBranch, Plus, Rocket } from 'lucide-react';
import { Link } from 'react-router-dom';

import { PageHeader } from '@/components/scaffolds/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useOrganization } from '@/context/OrganizationContext';
import api from '@/lib/api';
import { useApiMutation } from '@/lib/api-hooks';
import type { Flow } from '@/types/models';

function statusLabel(flow: Flow) {
    if (flow.active_version?.is_published) return 'Published';
    if (flow.active_version) return 'Draft';
    return 'Unconfigured';
}

export default function FlowsPage() {
    const { activeOrganization, organizationApiPrefix } = useOrganization();

    const { data: flows = [], isLoading } = useQuery<Flow[]>({
        queryKey: ['flows', activeOrganization?.id],
        queryFn: async () => {
            const response = await api.get<{ data: Flow[] }>(`${organizationApiPrefix}/flows`);
            return response.data.data;
        },
        enabled: !!activeOrganization,
    });

    const publishMutation = useApiMutation({
        mutationFn: async (flowId: string) => api.post(`${organizationApiPrefix}/flows/${flowId}/publish`),
        successMessage: 'Flow published successfully',
        invalidateQueries: [
            ['flows', activeOrganization?.id || ''],
            ['flow'],
        ],
    });

    if (!activeOrganization) {
        return (
            <div className="flex h-64 items-center justify-center text-muted-foreground">
                Select organization to manage call flows.
            </div>
        );
    }

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <PageHeader
                title="Call Flows"
                description="Build inbound call-routing graphs and publish them for number assignment."
                actionLabel="Create Flow"
                actionRoute="/admin/flows/create"
                actionIcon={<Plus className="mr-2 size-4" />}
            />

            <div className="rounded-md border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Flow</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Version</TableHead>
                            <TableHead>Updated</TableHead>
                            <TableHead className="w-[160px] text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {isLoading ? (
                            <TableRow>
                                <TableCell colSpan={5} className="py-8 text-center text-muted-foreground">
                                    Loading call flows...
                                </TableCell>
                            </TableRow>
                        ) : flows.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={5} className="py-8 text-center text-muted-foreground">
                                    No call flows yet. Create first inbound routing graph.
                                </TableCell>
                            </TableRow>
                        ) : (
                            flows.map((flow) => (
                                <TableRow key={flow.id}>
                                    <TableCell>
                                        <div className="flex items-center gap-3">
                                            <div className="rounded-lg border bg-muted/50 p-2">
                                                <GitBranch className="size-4 text-primary" />
                                            </div>
                                            <div>
                                                <div className="font-medium">{flow.name}</div>
                                                <div className="text-sm text-muted-foreground">
                                                    {flow.description || 'Inbound routing graph'}
                                                </div>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant={flow.active_version?.is_published ? 'success' : 'secondary'}>
                                            {statusLabel(flow)}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        {flow.active_version?.version_number ? `v${flow.active_version.version_number}` : '—'}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {flow.updated_at ? new Date(flow.updated_at).toLocaleString() : '—'}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-2">
                                            <Button variant="ghost" size="icon" asChild>
                                                <Link to={`/admin/flows/${flow.id}/edit`}>
                                                    <Edit className="size-4" />
                                                    <span className="sr-only">Edit flow</span>
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                disabled={publishMutation.isPending}
                                                onClick={() => publishMutation.mutate(flow.id)}
                                            >
                                                <Rocket className="size-4 text-primary" />
                                                <span className="sr-only">Publish flow</span>
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>
        </div>
    );
}
