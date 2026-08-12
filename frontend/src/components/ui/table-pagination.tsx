import { ChevronLeft, ChevronRight } from 'lucide-react';

import { Button } from '@/components/ui/button';
import type { PaginationMeta } from '@/types/models';

interface TablePaginationProps {
    meta?: PaginationMeta | null;
    onPageChange: (page: number) => void;
    /** Noun for the row count, e.g. "calls" or "recordings". */
    itemLabel?: string;
}

/**
 * Prev/next pager driven by Laravel paginator metadata.
 *
 * Renders the real total rather than the loaded row count — several pages
 * previously derived their stat tiles from a single page of results and reported
 * numbers that silently capped at the page size.
 */
export function TablePagination({ meta, onPageChange, itemLabel = 'results' }: TablePaginationProps) {
    if (!meta || meta.total === 0) {
        return null;
    }

    const { current_page: current, last_page: last, from, to, total } = meta;

    return (
        <div className="flex flex-col gap-3 border-t px-1 pt-4 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-sm text-muted-foreground">
                Showing <span className="font-medium text-foreground">{from ?? 0}</span>–
                <span className="font-medium text-foreground">{to ?? 0}</span> of{' '}
                <span className="font-medium text-foreground">{total.toLocaleString()}</span> {itemLabel}
            </p>

            <div className="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => onPageChange(current - 1)}
                    disabled={current <= 1}
                >
                    <ChevronLeft className="size-4" />
                    Previous
                </Button>
                <span className="px-1 text-sm text-muted-foreground">
                    Page {current} of {last}
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => onPageChange(current + 1)}
                    disabled={current >= last}
                >
                    Next
                    <ChevronRight className="size-4" />
                </Button>
            </div>
        </div>
    );
}
