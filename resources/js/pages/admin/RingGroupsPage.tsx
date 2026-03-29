import { useQuery } from '@tanstack/react-query';
import { Plus, Pencil, Trash2, GitBranch } from 'lucide-react';

import api from '@/lib/api';
import { useTenant } from '@/context/TenantContext';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

interface RingGroup {
    id: number;
    name: string;
    strategy: string;
    timeout: number;
    created_at: string;
}

export default function RingGroupsPage() {
    const { activeTenant, tenantApiPrefix } = useTenant();

    const { data: groups = [], isLoading } = useQuery({
        queryKey: ['ring-groups', activeTenant?.id],
        queryFn: async () => {
            const res = await api.get<{ data: RingGroup[] }>(
                `${tenantApiPrefix}/ring-groups`,
            );
            return res.data.data;
        },
        enabled: !!activeTenant,
    });

    if (!activeTenant) {
        return (
            <div className="flex h-64 items-center justify-center text-muted-foreground">
                Select a tenant to view ring groups.
            </div>
        );
    }

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <div className="flex items-center justify-between">
                <div>
                    <p className="text-sm text-muted-foreground">
                        {activeTenant.name} &rsaquo; Routing
                    </p>
                    <h1 className="text-2xl font-bold tracking-tight">Ring Groups</h1>
                    <p className="text-muted-foreground">
                        Simultaneous and sequential ring strategies for call distribution.
                    </p>
                </div>
                <Button>
                    <Plus className="size-4" />
                    Create Ring Group
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>All Ring Groups</CardTitle>
                    <CardDescription>
                        {groups.length} ring groups for {activeTenant.domain}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {isLoading ? (
                        <div className="flex h-32 items-center justify-center">
                            <div className="size-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Strategy</TableHead>
                                    <TableHead>Timeout</TableHead>
                                    <TableHead>Created</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {groups.map((group) => (
                                    <TableRow key={group.id}>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <GitBranch className="size-4 text-muted-foreground" />
                                                <span className="font-medium">{group.name}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{group.strategy}</Badge>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {group.timeout}s
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {new Date(group.created_at).toLocaleDateString()}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button variant="ghost" size="icon">
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <Button variant="ghost" size="icon">
                                                    <Trash2 className="size-4 text-destructive" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {groups.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={5} className="h-24 text-center text-muted-foreground">
                                            No ring groups configured.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
