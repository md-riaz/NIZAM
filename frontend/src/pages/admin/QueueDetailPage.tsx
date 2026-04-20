import { zodResolver } from '@hookform/resolvers/zod';
import { useQuery } from '@tanstack/react-query';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useParams } from 'react-router-dom';
import { z } from 'zod';

import { PageHeader } from '@/components/scaffolds/PageHeader';
import { DeleteDialog } from '@/components/scaffolds/DeleteDialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import {
    Form,
    FormControl,
    FormField,
    FormItem,
    FormLabel,
    FormMessage,
} from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
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

const memberSchema = z.object({
    agent_id: z.string().uuid("Agent is required"),
    priority: z.coerce.number().min(0).max(99).default(0),
});

interface Agent {
    id: string;
    name: string;
    role: string;
    state: string;
}

interface Member extends Agent {
    pivot: {
        priority: number;
    };
}

export default function QueueDetailPage() {
    const { id } = useParams<{ id: string }>();
    const { activeOrganization } = useOrganization();
    const [isAddOpen, setIsAddOpen] = useState(false);
    const [memberToRemove, setMemberToRemove] = useState<Member | null>(null);

    const { data: queue } = useQuery({
        queryKey: ['queue', activeOrganization?.id, id],
        queryFn: async () => {
             const res = await api.get(`organizations/${activeOrganization!.id}/queues/${id}`);
             return res.data.data;
        },
        enabled: !!activeOrganization && !!id,
    });

    const { data: members = [], isLoading } = useQuery<Member[]>({
        queryKey: ['queue-members', activeOrganization?.id, id],
        queryFn: async () => {
            const res = await api.get(`organizations/${activeOrganization!.id}/queues/${id}/members`);
            return res.data.data;
        },
        enabled: !!activeOrganization && !!id,
    });

    const { data: agents = [] } = useQuery<Agent[]>({
        queryKey: ['agents', activeOrganization?.id],
        queryFn: async () => {
            const res = await api.get(`organizations/${activeOrganization!.id}/agents`);
            return res.data.data;
        },
        enabled: !!activeOrganization && isAddOpen, // Only fetch when dialog opens
    });

    const form = useForm<z.infer<typeof memberSchema>>({
        resolver: zodResolver(memberSchema),
        defaultValues: { agent_id: '', priority: 0 },
    });

    const addMutation = useApiMutation({
        mutationFn: async (values: z.infer<typeof memberSchema>) => {
            return api.post(`organizations/${activeOrganization!.id}/queues/${id}/members`, values);
        },
        successMessage: 'Agent added to queue',
        invalidateQueries: [['queue-members', activeOrganization?.id || '', id || '']],
        onSuccess: () => {
            setIsAddOpen(false);
            form.reset();
        },
    });

    const removeMutation = useApiMutation({
        mutationFn: async (agentId: string) => {
            return api.delete(`organizations/${activeOrganization!.id}/queues/${id}/members/${agentId}`);
        },
        successMessage: 'Agent removed from queue',
        invalidateQueries: [['queue-members', activeOrganization?.id || '', id || '']],
        onSettled: () => setMemberToRemove(null),
    });

    if (!activeOrganization || !queue) return null;

    // Filter agents not already in the queue
    const availableAgents = agents.filter(a => !members.find(m => m.id === a.id));

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <PageHeader
                title={`Queue: ${queue.name}`}
                breadcrumbs="Contact Center Configuration"
                actionLabel="Back to Queues"
                actionRoute="/admin/queues"
            />

            <Card className="max-w-4xl">
                <CardHeader className="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle>Queue Members (Agents)</CardTitle>
                        <CardDescription>
                            Agents assigned to answer calls in this queue.
                        </CardDescription>
                    </div>
                    <Dialog open={isAddOpen} onOpenChange={setIsAddOpen}>
                        <DialogTrigger asChild>
                            <Button size="sm"><Plus className="mr-2 size-4" />Assign Agent</Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Assign Agent</DialogTitle>
                                <DialogDescription>Bind an agent to this queue.</DialogDescription>
                            </DialogHeader>
                            <Form {...form}>
                                <form onSubmit={form.handleSubmit(v => addMutation.mutate(v))} className="space-y-4">
                                    <FormField
                                        control={form.control}
                                        name="agent_id"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Agent</FormLabel>
                                                <Select onValueChange={field.onChange} value={field.value}>
                                                    <FormControl>
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Select an agent" />
                                                        </SelectTrigger>
                                                    </FormControl>
                                                    <SelectContent>
                                                        {availableAgents.map((ag) => (
                                                            <SelectItem key={ag.id} value={ag.id}>{ag.name}</SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                    <FormField
                                        control={form.control}
                                        name="priority"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Priority (0-99, lower is higher priority)</FormLabel>
                                                <FormControl>
                                                    <Input type="number" min="0" max="99" {...field} />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                    <DialogFooter>
                                        <Button type="submit" disabled={addMutation.isPending}>
                                            {addMutation.isPending ? 'Assigning...' : 'Assign'}
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </Form>
                        </DialogContent>
                    </Dialog>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Agent Name</TableHead>
                                <TableHead>Priority</TableHead>
                                <TableHead>State</TableHead>
                                <TableHead className="w-[100px] text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {isLoading ? (
                                <TableRow>
                                    <TableCell colSpan={4} className="py-4 text-center">Loading members...</TableCell>
                                </TableRow>
                            ) : members.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={4} className="py-4 text-center text-muted-foreground">No agents assigned.</TableCell>
                                </TableRow>
                            ) : (
                                members.map((member) => (
                                    <TableRow key={member.id}>
                                        <TableCell className="font-medium">{member.name}</TableCell>
                                        <TableCell>{member.pivot.priority}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline" className="capitalize">{member.state.replace('_', ' ')}</Badge>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button variant="ghost" size="icon" onClick={() => setMemberToRemove(member)}>
                                                <Trash2 className="size-4 text-destructive" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <DeleteDialog
                open={!!memberToRemove}
                onOpenChange={(open) => !open && setMemberToRemove(null)}
                title="Remove Agent"
                description={<>Are you sure you want to remove <strong>{memberToRemove?.name}</strong> from this queue?</>}
                isDeleting={removeMutation.isPending}
                onConfirm={() => memberToRemove && removeMutation.mutate(memberToRemove.id)}
            />
        </div>
    );
}
