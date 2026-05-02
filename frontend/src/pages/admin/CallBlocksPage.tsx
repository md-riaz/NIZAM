import { useQuery } from '@tanstack/react-query';
import { Pencil, Shield, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';

import { DeleteDialog } from '@/components/scaffolds/DeleteDialog';
import { PageHeader } from '@/components/scaffolds/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
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
import type { CallBlock } from '@/types/models';

interface CallBlockFormState {
    name: string;
    number: string;
    description: string;
    is_active: boolean;
}

const EMPTY_FORM: CallBlockFormState = {
    name: '',
    number: '',
    description: '',
    is_active: true,
};

function toFormState(callBlock: CallBlock): CallBlockFormState {
    return {
        name: callBlock.name,
        number: callBlock.number,
        description: callBlock.description ?? '',
        is_active: callBlock.is_active,
    };
}

export default function CallBlocksPage() {
    const { activeOrganization, organizationApiPrefix } = useOrganization();
    const [callBlockToDelete, setCallBlockToDelete] = useState<CallBlock | null>(null);
    const [editingCallBlock, setEditingCallBlock] = useState<CallBlock | null>(null);
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [formState, setFormState] = useState<CallBlockFormState>(EMPTY_FORM);

    const { data: callBlocks = [], isLoading } = useQuery<CallBlock[]>({
        queryKey: ['call-blocks', activeOrganization?.id],
        queryFn: async () => {
            if (!activeOrganization) return [];
            const response = await api.get<{ data: CallBlock[] }>(`${organizationApiPrefix}/call-blocks`);
            return response.data.data;
        },
        enabled: !!activeOrganization,
    });

    const sortedCallBlocks = useMemo(
        () => [...callBlocks].sort((left, right) => left.name.localeCompare(right.name)),
        [callBlocks],
    );

    const saveMutation = useApiMutation<unknown, CallBlockFormState>({
        mutationFn: async (values) => {
            const payload = {
                name: values.name,
                number: values.number,
                description: values.description || null,
                is_active: values.is_active,
            };

            if (editingCallBlock) {
                await api.put(`${organizationApiPrefix}/call-blocks/${editingCallBlock.id}`, payload);
                return;
            }

            await api.post(`${organizationApiPrefix}/call-blocks`, payload);
        },
        successMessage: editingCallBlock ? 'Call block updated successfully' : 'Call block created successfully',
        invalidateQueries: [['call-blocks', activeOrganization?.id || '']],
        onSuccess: () => {
            setIsDialogOpen(false);
            setEditingCallBlock(null);
            setFormState(EMPTY_FORM);
        },
    });

    const deleteMutation = useApiMutation({
        mutationFn: async (id: string) => {
            await api.delete(`${organizationApiPrefix}/call-blocks/${id}`);
        },
        successMessage: 'Call block deleted successfully',
        invalidateQueries: [['call-blocks', activeOrganization?.id || '']],
        onSettled: () => setCallBlockToDelete(null),
    });

    if (!activeOrganization) return null;

    const openCreateDialog = () => {
        setEditingCallBlock(null);
        setFormState(EMPTY_FORM);
        setIsDialogOpen(true);
    };

    const openEditDialog = (callBlock: CallBlock) => {
        setEditingCallBlock(callBlock);
        setFormState(toFormState(callBlock));
        setIsDialogOpen(true);
    };

    const closeDialog = (open: boolean) => {
        setIsDialogOpen(open);
        if (!open && !saveMutation.isPending) {
            setEditingCallBlock(null);
            setFormState(EMPTY_FORM);
        }
    };

    const submitForm = async () => {
        await saveMutation.mutateAsync(formState);
    };

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <PageHeader
                title="Call Block"
                description="Block inbound caller numbers before normal routing runs."
                actionLabel="Add Call Block"
                actionIcon={<Shield className="size-4 mr-2" />}
                onAction={openCreateDialog}
            />

            <div className="rounded-md border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Blocked Number</TableHead>
                            <TableHead>Action</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Description</TableHead>
                            <TableHead className="w-[100px] text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {isLoading ? (
                            <TableRow>
                                <TableCell colSpan={6} className="py-8 text-center text-muted-foreground">
                                    Loading call blocks...
                                </TableCell>
                            </TableRow>
                        ) : sortedCallBlocks.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={6} className="py-8 text-center text-muted-foreground">
                                    No call blocks found. Add one to start blocking inbound callers.
                                </TableCell>
                            </TableRow>
                        ) : (
                            sortedCallBlocks.map((callBlock) => (
                                <TableRow key={callBlock.id}>
                                    <TableCell className="font-medium">{callBlock.name}</TableCell>
                                    <TableCell className="font-mono">{callBlock.number}</TableCell>
                                    <TableCell>
                                        <Badge variant="destructive">Reject</Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant={callBlock.is_active ? 'default' : 'secondary'}>
                                            {callBlock.is_active ? 'Active' : 'Inactive'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {callBlock.description || '—'}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-2">
                                            <Button variant="ghost" size="icon" onClick={() => openEditDialog(callBlock)}>
                                                <Pencil className="size-4" />
                                                <span className="sr-only">Edit call block</span>
                                            </Button>
                                            <Button variant="ghost" size="icon" onClick={() => setCallBlockToDelete(callBlock)}>
                                                <Trash2 className="size-4 text-destructive" />
                                                <span className="sr-only">Delete call block</span>
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>

            <Dialog open={isDialogOpen} onOpenChange={closeDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{editingCallBlock ? 'Edit Call Block' : 'Add Call Block'}</DialogTitle>
                        <DialogDescription>
                            Reject matching inbound caller numbers before flow or extension routing.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        <div className="space-y-2">
                            <label className="text-sm font-medium" htmlFor="call-block-name">Name</label>
                            <Input
                                id="call-block-name"
                                placeholder="Spam caller"
                                value={formState.name}
                                onChange={(event) => setFormState((current) => ({ ...current, name: event.target.value }))}
                            />
                        </div>
                        <div className="space-y-2">
                            <label className="text-sm font-medium" htmlFor="call-block-number">Blocked Number</label>
                            <Input
                                id="call-block-number"
                                placeholder="15551234567"
                                value={formState.number}
                                onChange={(event) => setFormState((current) => ({ ...current, number: event.target.value }))}
                            />
                        </div>
                        <div className="space-y-2">
                            <label className="text-sm font-medium" htmlFor="call-block-description">Description</label>
                            <Input
                                id="call-block-description"
                                placeholder="Inbound spam ANI"
                                value={formState.description}
                                onChange={(event) => setFormState((current) => ({ ...current, description: event.target.value }))}
                            />
                        </div>
                        <label className="flex items-center gap-3 text-sm font-medium">
                            <Checkbox
                                checked={formState.is_active}
                                onCheckedChange={(checked) => setFormState((current) => ({ ...current, is_active: checked === true }))}
                            />
                            Active
                        </label>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={() => closeDialog(false)} disabled={saveMutation.isPending}>
                            Cancel
                        </Button>
                        <Button onClick={submitForm} disabled={saveMutation.isPending}>
                            {saveMutation.isPending ? 'Saving…' : editingCallBlock ? 'Save Changes' : 'Create Call Block'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <DeleteDialog
                open={!!callBlockToDelete}
                onOpenChange={(open) => !open && setCallBlockToDelete(null)}
                title="Delete Call Block"
                description={<>Are you sure you want to delete call block <strong>{callBlockToDelete?.name}</strong>?</>}
                isDeleting={deleteMutation.isPending}
                onConfirm={() => callBlockToDelete && deleteMutation.mutate(callBlockToDelete.id)}
            />
        </div>
    );
}
