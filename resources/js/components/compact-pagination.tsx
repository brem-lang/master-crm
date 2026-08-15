import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Paginator } from '@/types';

const PER_PAGE_OPTIONS = ['10', '20', '25', '50', '100'];

type Props = {
    paginator: Paginator<unknown>;
    /** Current filter query params to preserve when the per-page selection changes. */
    filters?: Record<
        string,
        string | number | boolean | string[] | undefined | null
    >;
    /** Rendered between the per-page control and the Previous/Next controls — e.g. a search box. */
    children?: ReactNode;
};

/**
 * Compact Previous/Next pagination bar (page X of Y) — a leaner alternative
 * to `DataPagination`'s numbered page links, used where the toolbar needs
 * to combine per-page, search, and pagination in a single row.
 */
export function CompactPagination({
    paginator,
    filters = {},
    children,
}: Props) {
    const handlePerPageChange = (value: string) => {
        router.get(
            paginator.path,
            { ...filters, per_page: value, page: 1 },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const firstRow =
        paginator.total === 0
            ? 0
            : (paginator.current_page - 1) * paginator.per_page + 1;
    const lastRow = firstRow + paginator.data.length - 1;

    return (
        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div className="flex flex-1 items-center gap-4">
                <div className="flex shrink-0 items-center gap-2 text-sm text-muted-foreground">
                    <span>View</span>
                    <Select
                        defaultValue={String(paginator.per_page)}
                        onValueChange={handlePerPageChange}
                    >
                        <SelectTrigger
                            className="w-16"
                            aria-label="Rows per page"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent align="start">
                            <SelectGroup>
                                {PER_PAGE_OPTIONS.map((option) => (
                                    <SelectItem key={option} value={option}>
                                        {option}
                                    </SelectItem>
                                ))}
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <span>per page</span>
                </div>

                {children}
            </div>

            <div className="flex shrink-0 items-center gap-6">
                <div className="flex items-center gap-3">
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!paginator.prev_page_url}
                        onClick={() =>
                            paginator.prev_page_url &&
                            router.get(paginator.prev_page_url, undefined, {
                                preserveState: true,
                                preserveScroll: true,
                            })
                        }
                    >
                        <ChevronLeft className="size-4" />
                        Previous
                    </Button>

                    <span className="text-sm text-muted-foreground">
                        {paginator.current_page} of {paginator.last_page}
                    </span>

                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!paginator.next_page_url}
                        onClick={() =>
                            paginator.next_page_url &&
                            router.get(paginator.next_page_url, undefined, {
                                preserveState: true,
                                preserveScroll: true,
                            })
                        }
                    >
                        Next
                        <ChevronRight className="size-4" />
                    </Button>
                </div>

                <p className="shrink-0 text-sm text-muted-foreground">
                    Showing {firstRow}–{lastRow} of {paginator.total} leads
                </p>
            </div>
        </div>
    );
}
