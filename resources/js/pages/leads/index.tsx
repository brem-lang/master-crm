import { Head, router, usePage } from '@inertiajs/react';
import { Eye, Search } from 'lucide-react';
import { useState } from 'react';
import { DataPagination } from '@/components/data-pagination';
import Heading from '@/components/heading';
import { RefreshButton } from '@/components/refresh-button';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { index as leadsIndex } from '@/routes/leads';
import type { Company, Lead, Paginator } from '@/types';

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

function metaLabel(key: string): string {
    return key
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function metaValue(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

function LeadDetailsDialog({
    lead,
    open,
    onOpenChange,
}: {
    lead: Lead | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    if (!lead) {
        return null;
    }

    const fields: [string, string][] = [
        ['Email', metaValue(lead.email)],
        ['Mobile', metaValue(lead.mobile)],
        ['Country', metaValue(lead.country_code)],
        ['IP address', metaValue(lead.ip_address)],
        ['Affiliate', metaValue(lead.affiliate_name)],
        ['Offer', metaValue(lead.offer_name)],
        ['FTD', lead.is_ftd ? 'Yes' : 'No'],
        [
            'Created',
            lead.lead_created_at
                ? new Date(lead.lead_created_at).toLocaleString()
                : '—',
        ],
    ];

    const metaEntries = Object.entries(lead.meta ?? {}).filter(
        ([, value]) => value !== null && value !== '' && value !== undefined,
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle className="text-xl">
                        {[lead.first_name, lead.last_name]
                            .filter(Boolean)
                            .join(' ') || 'Lead details'}
                    </DialogTitle>
                    <DialogDescription>
                        {lead.status ? (
                            <Badge
                                variant="outline"
                                className={statusBadgeClass(lead.status)}
                            >
                                {lead.status}
                            </Badge>
                        ) : null}
                    </DialogDescription>
                </DialogHeader>

                <div className="max-h-[70vh] space-y-6 overflow-y-auto">
                    <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
                        {fields.map(([label, value]) => (
                            <div key={label}>
                                <dt className="text-muted-foreground">
                                    {label}
                                </dt>
                                <dd className="font-medium wrap-break-word">
                                    {value}
                                </dd>
                            </div>
                        ))}
                    </dl>

                    {metaEntries.length > 0 && (
                        <div>
                            <p className="mb-2 text-sm font-medium text-muted-foreground">
                                Additional details
                            </p>
                            <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
                                {metaEntries.map(([key, value]) => (
                                    <div key={key}>
                                        <dt className="text-muted-foreground">
                                            {metaLabel(key)}
                                        </dt>
                                        <dd className="font-medium wrap-break-word">
                                            {metaValue(value)}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}

type PageProps = {
    total: number;
    rejected: number;
    ftd: number;
    byStatus: Record<string, number>;
    leads: Paginator<Lead>;
    companies?: Pick<Company, 'id' | 'name'>[];
    viewLead: Lead | null;
    filters: {
        search: string;
        status: string | null;
        company_id: number | null;
    };
};

export default function LeadsIndex() {
    const {
        total,
        rejected,
        ftd,
        byStatus,
        leads,
        companies,
        viewLead,
        filters,
    } = usePage<PageProps>().props;
    const [search, setSearch] = useState(filters.search);
    const [viewingLead, setViewingLead] = useState<Lead | null>(null);

    const applyFilters = (next: Partial<typeof filters>) => {
        router.get(
            leadsIndex().url,
            {
                ...filters,
                ...next,
                per_page: leads.per_page,
                page: 1,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const closeViewLead = () => {
        router.get(
            leadsIndex().url,
            {
                ...filters,
                per_page: leads.per_page,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const debouncedSearch = useDebouncedCallback((value: string) => {
        applyFilters({ search: value });
    }, 300);

    return (
        <>
            <Head title="Leads" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Leads"
                        description={
                            companies
                                ? "Leads pulled from all companies' CRMs"
                                : "Leads pulled from your company's CRM"
                        }
                    />
                    <RefreshButton />
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard label="Total leads" value={total} />
                    <StatCard label="Rejected" value={rejected} />
                    <StatCard label="FTD" value={ftd} />
                </div>

                {/* {Object.keys(byStatus).length > 0 && (
                    <div className="flex flex-wrap items-center gap-2">
                        {Object.entries(byStatus).map(([status, count]) => (
                            <Badge key={status} variant="secondary">
                                {status}: {count}
                            </Badge>
                        ))}
                    </div>
                )} */}

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

                    {companies && (
                        <Select
                            value={
                                filters.company_id
                                    ? String(filters.company_id)
                                    : 'all'
                            }
                            onValueChange={(value) =>
                                applyFilters({
                                    company_id:
                                        value === 'all' ? null : Number(value),
                                })
                            }
                        >
                            <SelectTrigger className="w-full sm:w-48">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="all">
                                        All companies
                                    </SelectItem>
                                    {companies.map((company) => (
                                        <SelectItem
                                            key={company.id}
                                            value={String(company.id)}
                                        >
                                            {company.name}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    )}
                </div>

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                {companies && (
                                    <TableHead className="hidden md:table-cell">
                                        Company
                                    </TableHead>
                                )}
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
                            {leads.data.map((lead) => (
                                <TableRow key={lead.id}>
                                    <TableCell className="font-medium">
                                        {[lead.first_name, lead.last_name]
                                            .filter(Boolean)
                                            .join(' ') || '—'}
                                    </TableCell>
                                    {companies && (
                                        <TableCell className="hidden md:table-cell">
                                            {lead.company?.name ?? '—'}
                                        </TableCell>
                                    )}
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
                                            onClick={() => setViewingLead(lead)}
                                        >
                                            <Eye className="size-4" />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <DataPagination paginator={leads} filters={filters} />

                <LeadDetailsDialog
                    lead={viewingLead ?? viewLead}
                    open={!!viewingLead || !!viewLead}
                    onOpenChange={(open) => {
                        if (!open) {
                            setViewingLead(null);

                            if (viewLead) {
                                closeViewLead();
                            }
                        }
                    }}
                />
            </div>
        </>
    );
}

LeadsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Leads',
            href: leadsIndex(),
        },
    ],
};
