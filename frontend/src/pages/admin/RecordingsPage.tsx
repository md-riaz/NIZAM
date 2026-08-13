import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Download, FileAudio, Search, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

import { RecordingPlayer } from '@/components/admin/RecordingPlayer';
import { DeleteDialog } from '@/components/scaffolds/DeleteDialog';
import { PageHeader } from '@/components/scaffolds/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { TablePagination } from '@/components/ui/table-pagination';
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
import { downloadAuthenticatedFile, formatDuration, formatFileSize } from '@/lib/media';
import type { PaginationMeta, Recording } from '@/types/models';

interface Filters {
    caller_id_number: string;
    destination_number: string;
    date_from: string;
    date_to: string;
}

const EMPTY_FILTERS: Filters = {
    caller_id_number: '',
    destination_number: '',
    date_from: '',
    date_to: '',
};

function formatDateTime(value?: string | null): string {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}

export default function RecordingsPage() {
    const { activeOrganization, organizationApiPrefix } = useOrganization();
    const queryClient = useQueryClient();

    // Draft state is separate from applied state so typing in the filter bar
    // does not refetch on every keystroke.
    const [draft, setDraft] = useState<Filters>(EMPTY_FILTERS);
    const [applied, setApplied] = useState<Filters>(EMPTY_FILTERS);
    const [page, setPage] = useState(1);
    const [toDelete, setToDelete] = useState<Recording | null>(null);

    const queryKey = ['recordings', activeOrganization?.id, applied, page] as const;

    const { data, isLoading, isError, error } = useQuery({
        queryKey,
        queryFn: async () => {
            const response = await api.get<{ data: Recording[]; meta?: PaginationMeta }>(
                `${organizationApiPrefix}/recordings`,
                { params: { ...applied, page } },
            );
            return response.data;
        },
        enabled: Boolean(activeOrganization),
    });

    const deleteMutation = useApiMutation({
        mutationFn: async (id: string) => {
            await api.delete(`${organizationApiPrefix}/recordings/${id}`);
        },
        successMessage: 'Recording deleted',
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: ['recordings', activeOrganization?.id] });
        },
        onSettled: () => setToDelete(null),
    });

    const download = async (recording: Recording) => {
        try {
            await downloadAuthenticatedFile(
                `${organizationApiPrefix}/recordings/${recording.id}/download`,
                recording.file_name || `recording-${recording.id}.${recording.format ?? 'wav'}`,
            );
        } catch {
            toast.error('Could not download this recording.');
        }
    };

    const applyFilters = () => {
        setApplied(draft);
        setPage(1);
    };

    const clearFilters = () => {
        setDraft(EMPTY_FILTERS);
        setApplied(EMPTY_FILTERS);
        setPage(1);
    };

    const hasFilters = Object.values(applied).some(Boolean);
    const recordings = data?.data ?? [];

    if (!activeOrganization) {
        return (
            <div className="flex h-64 items-center justify-center text-muted-foreground">
                Select an organization to view recordings.
            </div>
        );
    }

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <PageHeader
                title="Call Recordings"
                description="Play, download, and manage recorded calls for this organization."
                breadcrumbs={`${activeOrganization.name} › Calls`}
            />

            <Card>
                <CardContent className="pt-6">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="caller">From number</Label>
                            <Input
                                id="caller"
                                value={draft.caller_id_number}
                                placeholder="e.g. +15550100"
                                onChange={(e) => setDraft({ ...draft, caller_id_number: e.target.value })}
                                onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="destination">To number</Label>
                            <Input
                                id="destination"
                                value={draft.destination_number}
                                placeholder="e.g. 1001"
                                onChange={(e) => setDraft({ ...draft, destination_number: e.target.value })}
                                onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="from">Recorded after</Label>
                            <Input
                                id="from"
                                type="date"
                                value={draft.date_from}
                                onChange={(e) => setDraft({ ...draft, date_from: e.target.value })}
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="to">Recorded before</Label>
                            <Input
                                id="to"
                                type="date"
                                value={draft.date_to}
                                onChange={(e) => setDraft({ ...draft, date_to: e.target.value })}
                            />
                        </div>
                    </div>

                    <div className="mt-4 flex items-center gap-2">
                        <Button onClick={applyFilters} size="sm">
                            <Search className="size-4" />
                            Apply filters
                        </Button>
                        {hasFilters && (
                            <Button onClick={clearFilters} size="sm" variant="ghost">
                                <X className="size-4" />
                                Clear
                            </Button>
                        )}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="pt-6">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>From</TableHead>
                                <TableHead>To</TableHead>
                                <TableHead>Direction</TableHead>
                                <TableHead>Recorded</TableHead>
                                <TableHead>Length</TableHead>
                                <TableHead>Size</TableHead>
                                <TableHead>Playback</TableHead>
                                <TableHead className="w-[100px] text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {isLoading ? (
                                <TableRow>
                                    <TableCell colSpan={8} className="py-10 text-center">
                                        <div role="status">
                                            <div
                                                className="mx-auto size-6 animate-spin rounded-full border-2 border-primary border-t-transparent"
                                                aria-hidden="true"
                                            />
                                            <span className="sr-only">Loading recordings…</span>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ) : isError ? (
                                <TableRow>
                                    <TableCell colSpan={8} className="py-10 text-center text-sm text-destructive">
                                        Could not load recordings.
                                        {error instanceof Error ? ` ${error.message}` : ''}
                                    </TableCell>
                                </TableRow>
                            ) : recordings.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={8} className="py-10 text-center text-muted-foreground">
                                        {hasFilters ? (
                                            <>No recordings match these filters. Try widening the date range.</>
                                        ) : (
                                            <>
                                                No recordings yet. Calls are recorded when a recording policy is
                                                active on the organization, the number, or the extension.
                                            </>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ) : (
                                recordings.map((recording) => (
                                    <TableRow key={recording.id}>
                                        <TableCell className="font-mono text-sm">
                                            {recording.caller_id_number || '—'}
                                        </TableCell>
                                        <TableCell className="font-mono text-sm">
                                            {recording.destination_number || '—'}
                                        </TableCell>
                                        <TableCell>
                                            {recording.direction ? (
                                                <Badge variant="outline" className="capitalize">
                                                    {recording.direction}
                                                </Badge>
                                            ) : (
                                                '—'
                                            )}
                                        </TableCell>
                                        <TableCell className="text-sm text-muted-foreground">
                                            {formatDateTime(recording.created_at)}
                                        </TableCell>
                                        <TableCell className="text-sm">{formatDuration(recording.duration)}</TableCell>
                                        <TableCell className="text-sm text-muted-foreground">
                                            {formatFileSize(recording.file_size)}
                                        </TableCell>
                                        <TableCell>
                                            <RecordingPlayer
                                                downloadUrl={`${organizationApiPrefix}/recordings/${recording.id}/download`}
                                                format={recording.format}
                                                compact
                                            />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => void download(recording)}
                                                    aria-label="Download recording"
                                                >
                                                    <Download className="size-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => setToDelete(recording)}
                                                    aria-label="Delete recording"
                                                >
                                                    <Trash2 className="size-4 text-destructive" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>

                    <TablePagination meta={data?.meta} onPageChange={setPage} itemLabel="recordings" />
                </CardContent>
            </Card>

            <DeleteDialog
                open={Boolean(toDelete)}
                onOpenChange={(open) => !open && setToDelete(null)}
                title="Delete recording"
                description={
                    <>
                        Permanently delete the recording of the call from{' '}
                        <strong>{toDelete?.caller_id_number || 'unknown'}</strong> to{' '}
                        <strong>{toDelete?.destination_number || 'unknown'}</strong>? The audio file is removed
                        from storage and cannot be recovered.
                    </>
                }
                isDeleting={deleteMutation.isPending}
                onConfirm={() => toDelete && deleteMutation.mutate(toDelete.id)}
            />

            <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                <FileAudio className="size-3.5" />
                Recordings are retained according to this organization&rsquo;s retention policy. Access is
                governed by the recordings permissions on each user.
            </p>
        </div>
    );
}
