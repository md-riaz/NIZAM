import { useQuery } from '@tanstack/react-query';
import { BookUser } from 'lucide-react';
import { useMemo, useState } from 'react';

import { PageHeader } from '@/components/scaffolds/PageHeader';
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

interface DirectoryEntry {
    id: string;
    extension: string;
    first_name?: string | null;
    last_name?: string | null;
}

export default function DirectoryPage() {
    const { activeOrganization, organizationApiPrefix } = useOrganization();
    const [search, setSearch] = useState('');

    const { data: entries = [], isLoading } = useQuery<DirectoryEntry[]>({
        queryKey: ['directory', activeOrganization?.id, search],
        queryFn: async () => {
            const response = await api.get<{ data: DirectoryEntry[] }>(`${organizationApiPrefix}/directory`, {
                params: search.trim() ? { search: search.trim() } : undefined,
            });
            return response.data.data;
        },
        enabled: !!activeOrganization,
    });

    const sortedEntries = useMemo(
        () => [...entries].sort((left, right) => left.extension.localeCompare(right.extension)),
        [entries],
    );

    if (!activeOrganization) return null;

    return (
        <div className="space-y-6 p-6 lg:p-8">
            <PageHeader
                title="Directory"
                description="Search active organization extensions by name or extension number."
                breadcrumbs={`${activeOrganization.name} › Phone System`}
            />

            <div className="rounded-lg border bg-card p-4">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <BookUser className="size-4" />
                        Dial-by-name directory
                    </div>
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search by name or extension"
                        className="md:max-w-sm"
                    />
                </div>

                <div className="mt-4">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Extension</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {isLoading ? (
                                <TableRow>
                                    <TableCell colSpan={2} className="py-8 text-center text-muted-foreground">
                                        Loading directory...
                                    </TableCell>
                                </TableRow>
                            ) : sortedEntries.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={2} className="py-8 text-center text-muted-foreground">
                                        No directory entries found.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                sortedEntries.map((entry) => {
                                    const fullName = [entry.first_name, entry.last_name].filter(Boolean).join(' ').trim();

                                    return (
                                        <TableRow key={entry.id}>
                                            <TableCell className="font-medium">{fullName || 'Unnamed extension'}</TableCell>
                                            <TableCell className="font-mono">{entry.extension}</TableCell>
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </div>
    );
}
