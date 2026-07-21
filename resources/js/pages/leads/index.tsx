import { Head, router, usePage } from '@inertiajs/react';
import { Eye, Search } from 'lucide-react';
import { useState } from 'react';
import { BulkAssignBar } from '@/components/bulk-assign-bar';
import { DataPagination } from '@/components/data-pagination';
import Heading from '@/components/heading';
import { LeadDetailsDialog } from '@/components/lead-details-dialog';
import { RefreshButton } from '@/components/refresh-button';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { useRowSelection } from '@/hooks/use-row-selection';
import {
    assign as assignLead,
    bulkAssign,
    index as leadsIndex,
} from '@/routes/leads';
import type { Auth, Company, Lead, Paginator, User } from '@/types';

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
    auth: Auth;
    total: number;
    ftd: number;
    byStatus: Record<string, number>;
    leads: Paginator<Lead>;
    companies?: Pick<Company, 'id' | 'name'>[];
    salesReps: Pick<User, 'id' | 'name' | 'company_id'>[];
    viewLead: Lead | null;
    filters: {
        search: string;
        status: string | null;
        company_id: number | null;
        assigned_to: number | null;
    };
};

export default function LeadsIndex() {
    const {
        auth,
        total,
        ftd,
        byStatus,
        leads,
        companies,
        salesReps,
        viewLead,
        filters,
    } = usePage<PageProps>().props;
    const [search, setSearch] = useState(filters.search);
    const [viewingLead, setViewingLead] = useState<Lead | null>(null);
    const canAssignLeads = auth.permissions?.includes('assign-leads');
    const selection = useRowSelection(leads.data, leads.current_page);

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

                <div className="grid gap-4 sm:grid-cols-2">
                    <StatCard label="Total leads" value={total} />
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

                    {salesReps.length > 0 && (
                        <Select
                            value={
                                filters.assigned_to
                                    ? String(filters.assigned_to)
                                    : 'all'
                            }
                            onValueChange={(value) =>
                                applyFilters({
                                    assigned_to:
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
                                        All reps
                                    </SelectItem>
                                    {salesReps.map((rep) => (
                                        <SelectItem
                                            key={rep.id}
                                            value={String(rep.id)}
                                        >
                                            {rep.name}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    )}
                </div>

                {canAssignLeads && (
                    <BulkAssignBar
                        count={selection.selectedIds.length}
                        reps={salesReps}
                        onAssign={(assignedTo, onFinish) => {
                            router.patch(
                                bulkAssign().url,
                                {
                                    ids: selection.selectedIds,
                                    assigned_to: assignedTo,
                                },
                                {
                                    preserveScroll: true,
                                    onSuccess: () =>
                                        selection.setSelectedIds([]),
                                    onFinish,
                                },
                            );
                        }}
                    />
                )}

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                {canAssignLeads && (
                                    <TableHead className="w-px">
                                        <Checkbox
                                            checked={
                                                selection.allSelected
                                                    ? true
                                                    : selection.someSelected
                                                      ? 'indeterminate'
                                                      : false
                                            }
                                            onCheckedChange={(checked) =>
                                                selection.toggleAll(
                                                    checked === true,
                                                )
                                            }
                                            aria-label="Select all"
                                        />
                                    </TableHead>
                                )}
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
                                {salesReps.length > 0 && (
                                    <TableHead className="hidden md:table-cell">
                                        Assigned to
                                    </TableHead>
                                )}
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
                                <TableRow
                                    key={lead.id}
                                    data-state={
                                        selection.isSelected(lead.id)
                                            ? 'selected'
                                            : undefined
                                    }
                                >
                                    {canAssignLeads && (
                                        <TableCell>
                                            <Checkbox
                                                checked={selection.isSelected(
                                                    lead.id,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    selection.toggleOne(
                                                        lead.id,
                                                        checked === true,
                                                    )
                                                }
                                                aria-label={`Select ${[lead.first_name, lead.last_name].filter(Boolean).join(' ') || 'lead'}`}
                                            />
                                        </TableCell>
                                    )}
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
                                    {salesReps.length > 0 &&
                                        (() => {
                                            const companyReps =
                                                salesReps.filter(
                                                    (rep) =>
                                                        rep.company_id ===
                                                        lead.company_id,
                                                );

                                            return (
                                                <TableCell className="hidden md:table-cell">
                                                    {canAssignLeads &&
                                                    companyReps.length > 0 ? (
                                                        <Select
                                                            value={
                                                                lead.assigned_to
                                                                    ? String(
                                                                          lead.assigned_to,
                                                                      )
                                                                    : 'unassigned'
                                                            }
                                                            onValueChange={(
                                                                value,
                                                            ) =>
                                                                router.patch(
                                                                    assignLead(
                                                                        lead.id,
                                                                    ).url,
                                                                    {
                                                                        assigned_to:
                                                                            value ===
                                                                            'unassigned'
                                                                                ? null
                                                                                : Number(
                                                                                      value,
                                                                                  ),
                                                                    },
                                                                    {
                                                                        preserveScroll: true,
                                                                        preserveState: true,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            <SelectTrigger className="w-40">
                                                                <SelectValue />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectGroup>
                                                                    <SelectItem value="unassigned">
                                                                        Unassigned
                                                                    </SelectItem>
                                                                    {companyReps.map(
                                                                        (
                                                                            rep,
                                                                        ) => (
                                                                            <SelectItem
                                                                                key={
                                                                                    rep.id
                                                                                }
                                                                                value={String(
                                                                                    rep.id,
                                                                                )}
                                                                            >
                                                                                {
                                                                                    rep.name
                                                                                }
                                                                            </SelectItem>
                                                                        ),
                                                                    )}
                                                                </SelectGroup>
                                                            </SelectContent>
                                                        </Select>
                                                    ) : (
                                                        (lead.assignee?.name ??
                                                        '—')
                                                    )}
                                                </TableCell>
                                            );
                                        })()}
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
