import { Head, router, usePage } from '@inertiajs/react';
import { Eye, Search } from 'lucide-react';
import { useState } from 'react';
import { DataPagination } from '@/components/data-pagination';
import Heading from '@/components/heading';
import { LeadDetailsDialog } from '@/components/lead-details-dialog';
import { RefreshButton } from '@/components/refresh-button';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useDebouncedCallback } from '@/hooks/use-debounced-callback';
import { index as myLeadsIndex } from '@/routes/my-leads';
import type { Lead, Paginator } from '@/types';

function statusBadgeClass(status: string): string {
    switch (status.toLowerCase()) {
        case 'rejected':
            return 'border-transparent bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
        case 'converted':
            return 'border-transparent bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
        case 'contacted':
            return 'border-transparent bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400';
        case 'new':
            return 'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400';
        default:
            return 'border-transparent bg-muted text-muted-foreground';
    }
}

type PageProps = {
    total: number;
    rejected: number;
    ftd: number;
    byStatus: Record<string, number>;
    leads: Paginator<Lead>;
    filters: {
        search: string;
        status: string | null;
    };
};

export default function MyLeadsIndex() {
    const { total, rejected, ftd, byStatus, leads, filters } =
        usePage<PageProps>().props;
    const [search, setSearch] = useState(filters.search);
    const [viewingLead, setViewingLead] = useState<Lead | null>(null);

    const applyFilters = (next: Partial<typeof filters>) => {
        router.get(
            myLeadsIndex().url,
            {
                ...filters,
                ...next,
                per_page: leads.per_page,
                page: 1,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const debouncedSearch = useDebouncedCallback((value: string) => {
        applyFilters({ search: value });
    }, 300);

    return (
        <>
            <Head title="My Leads" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="My Leads"
                        description="Leads assigned to you"
                    />
                    <RefreshButton />
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard label="Total leads" value={total} />
                    <StatCard label="Rejected" value={rejected} />
                    <StatCard label="FTD" value={ftd} />
                </div>

                <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                    <div className="relative w-full sm:max-w-xs">
                        <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(event) => {
                                setSearch(event.target.value);
                                debouncedSearch(event.target.value);
                            }}
                            placeholder="Search by name or email…"
                            className="pl-8"
                        />
                    </div>

                    <Select
                        value={filters.status || 'all'}
                        onValueChange={(value) =>
                            applyFilters({
                                status: value === 'all' ? null : value,
                            })
                        }
                    >
                        <SelectTrigger className="w-full sm:w-40">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="all">
                                    All statuses
                                </SelectItem>
                                {Object.keys(byStatus).map((status) => (
                                    <SelectItem key={status} value={status}>
                                        {status}
                                    </SelectItem>
                                ))}
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </div>

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead className="hidden sm:table-cell">
                                    Email
                                </TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="hidden lg:table-cell">
                                    Affiliate
                                </TableHead>
                                <TableHead className="hidden lg:table-cell">
                                    Offer
                                </TableHead>
                                <TableHead className="hidden md:table-cell">
                                    Created
                                </TableHead>
                                <TableHead className="w-px" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {leads.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="py-8 text-center text-sm text-muted-foreground"
                                    >
                                        No leads assigned to you yet.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                leads.data.map((lead) => (
                                    <TableRow key={lead.id}>
                                        <TableCell className="font-medium">
                                            {[lead.first_name, lead.last_name]
                                                .filter(Boolean)
                                                .join(' ') || '—'}
                                        </TableCell>
                                        <TableCell className="hidden font-semibold sm:table-cell">
                                            {lead.email ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            {lead.status ? (
                                                <Badge
                                                    variant="outline"
                                                    className={statusBadgeClass(
                                                        lead.status,
                                                    )}
                                                >
                                                    {lead.status}
                                                </Badge>
                                            ) : (
                                                '—'
                                            )}
                                        </TableCell>
                                        <TableCell className="hidden lg:table-cell">
                                            {lead.affiliate_name ?? '—'}
                                        </TableCell>
                                        <TableCell className="hidden lg:table-cell">
                                            {lead.offer_name ?? '—'}
                                        </TableCell>
                                        <TableCell className="hidden md:table-cell">
                                            {lead.lead_created_at
                                                ? new Date(
                                                      lead.lead_created_at,
                                                  ).toLocaleDateString()
                                                : '—'}
                                        </TableCell>
                                        <TableCell>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`View ${[lead.first_name, lead.last_name].filter(Boolean).join(' ') || 'lead'}`}
                                                onClick={() =>
                                                    setViewingLead(lead)
                                                }
                                            >
                                                <Eye className="size-4" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                <DataPagination paginator={leads} filters={filters} />

                <LeadDetailsDialog
                    lead={viewingLead}
                    open={!!viewingLead}
                    onOpenChange={(open) => !open && setViewingLead(null)}
                />
            </div>
        </>
    );
}

MyLeadsIndex.layout = {
    breadcrumbs: [
        {
            title: 'My Leads',
            href: myLeadsIndex(),
        },
    ],
};
