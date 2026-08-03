import { router } from '@inertiajs/react';
import { Field, FieldLabel } from '@/components/ui/field';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Paginator } from '@/types';

const PER_PAGE_OPTIONS = ['10', '25', '50', '100'];

type Props = {
    paginator: Paginator<unknown>;
    /** Current filter query params to preserve when the per-page selection changes. */
    filters?: Record<string, string | number | string[] | undefined | null>;
};

export function DataPagination({ paginator, filters = {} }: Props) {
    const handlePerPageChange = (value: string) => {
        router.get(
            paginator.path,
            { ...filters, per_page: value },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const firstRow =
        paginator.total === 0
            ? 0
            : (paginator.current_page - 1) * paginator.per_page + 1;
    const lastRow = firstRow + paginator.data.length - 1;

    // Laravel's `links` array is [Previous, page 1, ..., page N, Next] —
    // the ends are already rendered separately via PaginationPrevious/Next.
    const pageLinks = paginator.links.slice(1, -1);

    return (
        <div className="flex flex-col items-center justify-between gap-4 sm:flex-row">
            <div className="flex items-center gap-4">
                <Field orientation="horizontal" className="w-fit">
                    <FieldLabel htmlFor="select-rows-per-page">
                        Rows per page
                    </FieldLabel>
                    <Select
                        defaultValue={String(paginator.per_page)}
                        onValueChange={handlePerPageChange}
                    >
                        <SelectTrigger
                            className="w-20"
                            id="select-rows-per-page"
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
                </Field>

                <p className="text-sm text-muted-foreground">
                    Showing {firstRow}–{lastRow} of {paginator.total}
                </p>
            </div>

            <Pagination className="mx-0 w-auto">
                <PaginationContent>
                    <PaginationItem>
                        <PaginationPrevious
                            href={paginator.prev_page_url}
                            preserveScroll
                        />
                    </PaginationItem>
                    {pageLinks.map((link, index) => (
                        <PaginationItem key={index}>
                            {link.url === null ? (
                                <PaginationEllipsis />
                            ) : (
                                <PaginationLink
                                    href={link.url}
                                    isActive={link.active}
                                    preserveScroll
                                >
                                    {link.label}
                                </PaginationLink>
                            )}
                        </PaginationItem>
                    ))}
                    <PaginationItem>
                        <PaginationNext
                            href={paginator.next_page_url}
                            preserveScroll
                        />
                    </PaginationItem>
                </PaginationContent>
            </Pagination>
        </div>
    );
}
