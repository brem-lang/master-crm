import { Form, Head, router, usePage } from '@inertiajs/react';
import {
    Check,
    Copy,
    Download,
    Eye,
    MoreHorizontal,
    Send,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import LeadsController from '@/actions/App/Http/Controllers/LeadsController';
import { BulkAssignBar } from '@/components/bulk-assign-bar';
import { BulkDeleteBar } from '@/components/bulk-delete-bar';
import { BulkResendDialog } from '@/components/bulk-resend-dialog';
import { ColumnsMenu } from '@/components/columns-menu';
import { CompactPagination } from '@/components/compact-pagination';
import { DateRangeFilter } from '@/components/date-range-filter';
import Heading from '@/components/heading';
import { RefreshButton } from '@/components/refresh-button';
import { RequestDetailsDialog } from '@/components/request-details-dialog';
import { ResendLeadDialog } from '@/components/resend-lead-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { MultiSelect } from '@/components/ui/multi-select';
import { SearchableSelect } from '@/components/ui/searchable-select';
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
import { useClipboard } from '@/hooks/use-clipboard';
import { useDebouncedCallback } from '@/hooks/use-debounced-callback';
import { useRowSelection } from '@/hooks/use-row-selection';
import { cn } from '@/lib/utils';
import {
    assign as assignLead,
    bulkAssign,
    exportMethod as exportLeads,
    index as leadsIndex,
} from '@/routes/leads';
import type { Auth, Company, Lead, Paginator, User } from '@/types';

function CopyableLeadId({ externalId }: { externalId: string }) {
    const [copiedText, copy] = useClipboard();
    const isCopied = copiedText === externalId;

    return (
        <div className="flex items-center gap-1.5">
            <code className="text-xs">{externalId.slice(0, 8)}</code>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-6 shrink-0"
                aria-label="Copy lead ID"
                onClick={() => copy(externalId)}
            >
                {isCopied ? (
                    <Check className="size-3.5" />
                ) : (
                    <Copy className="size-3.5" />
                )}
            </Button>
        </div>
    );
}

const GREEN_BADGE_CLASS =
    'border-transparent bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';

const LIVE_LEAD_STATUS_OPTIONS = [
    { value: 'green', label: '🟢 Green' },
    { value: 'orange', label: '🟡 Orange' },
    { value: 'light-red', label: '🟠 Light Red' },
    { value: 'red', label: '🔴 Red' },
];

function statusBadgeClass(status: string): string {
    switch (status.toLowerCase()) {
        case 'rejected':
            return 'border-transparent bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
        case 'converted':
            return GREEN_BADGE_CLASS;
        case 'contacted':
            return 'border-transparent bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400';
        case 'new':
            return 'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400';
        default:
            return 'border-transparent bg-muted text-muted-foreground';
    }
}

function metaText(lead: Lead, key: string): string {
    const value = lead.meta?.[key];

    return value === null || value === undefined || value === ''
        ? '—'
        : String(value);
}

function metaYesNo(lead: Lead, key: string): string {
    return lead.meta?.[key] ? 'Yes' : '—';
}

function formatDate(value: string | null | undefined): string {
    return value ? new Date(value).toLocaleString() : '—';
}

// Columns already rendered today stay visible by default; every other
// column (mostly sourced from `lead.meta`) starts hidden until toggled on.
const LEAD_COLUMNS: { key: string; label: string }[] = [
    { key: 'external_id', label: 'Lead ID' },
    { key: 'first_name', label: 'First Name' },
    { key: 'last_name', label: 'Last Name' },
    { key: 'mobile', label: 'Phone' },
    { key: 'company', label: 'Company' },
    { key: 'email', label: 'Email' },
    { key: 'country_code', label: 'Country' },
    { key: 'sale_status', label: 'Sale Status' },
    { key: 'advertiser_name', label: 'Advertiser' },
    { key: 'is_ftd', label: 'FTD' },
    { key: 'affiliate_name', label: 'Affiliate' },
    { key: 'assigned_to', label: 'Assigned to' },
    { key: 'lead_created_at', label: 'Created' },
    { key: 'request_id', label: 'Request ID' },
    { key: 'ip_address', label: 'IP Address' },
    { key: 'offer_name', label: 'Offer Name' },
    { key: 'status', label: 'Status' },
    { key: 'ftd_released', label: 'FTD Released' },
    { key: 'created_at', label: 'Record Created' },
    { key: 'updated_at', label: 'Updated At' },
    { key: 'live_lead_status', label: 'Live Lead Status' },
    { key: 'country', label: 'Country (Full)' },
    { key: 'city', label: 'City' },
    { key: 'locale', label: 'Locale' },
    { key: 'user_agent', label: 'User Agent' },
    { key: 'platform', label: 'Platform' },
    { key: 'browser', label: 'Browser' },
    { key: 'aff_sub', label: 'Aff Sub' },
    { key: 'affiliate_id', label: 'Affiliate ID' },
    { key: 'advertiser_id', label: 'Advertiser ID' },
    { key: 'click_id', label: 'Click ID' },
    { key: 'autologin', label: 'Autologin' },
    { key: 'comment', label: 'Comment' },
    { key: 'custom1', label: 'Custom 1' },
    { key: 'custom2', label: 'Custom 2' },
    { key: 'custom3', label: 'Custom 3' },
    { key: 'custom4', label: 'Custom 4' },
    { key: 'custom5', label: 'Custom 5' },
    { key: 'ftd_date', label: 'FTD Date' },
    { key: 'ftd_id', label: 'FTD ID' },
    { key: 'ftd_released_at', label: 'FTD Released At' },
    { key: 'ftd_released_by', label: 'FTD Released By' },
    { key: 'is_live', label: 'Is Live' },
    { key: 'needs_review', label: 'Needs Review' },
    { key: 'is_proxy', label: 'Is Proxy' },
    { key: 'fraud_score', label: 'Fraud Score' },
    { key: 'fraud_flags', label: 'Fraud Flags' },
    { key: 'time_to_click', label: 'Time To Click' },
    { key: 'distributed_at', label: 'Distributed At' },
    { key: 'live_lead_score', label: 'Live Lead Score' },
    { key: 'click_ip', label: 'Click IP' },
    { key: 'click_country', label: 'Click Country' },
    { key: 'click_asn', label: 'Click ASN' },
    { key: 'click_ua', label: 'Click UA' },
    { key: 'submission_country', label: 'Submission Country' },
    { key: 'submission_asn', label: 'Submission ASN' },
    { key: 'submission_ua', label: 'Submission UA' },
];

const DEFAULT_VISIBLE_COLUMN_KEYS = new Set([
    'external_id',
    'first_name',
    'last_name',
    'mobile',
    'company',
    'email',
    'country_code',
    'sale_status',
    'advertiser_name',
    'is_ftd',
    'affiliate_name',
    'assigned_to',
    'lead_created_at',
]);

const ALL_COLUMN_KEYS = LEAD_COLUMNS.map((c) => c.key);

const DEFAULT_HIDDEN_COLUMNS = ALL_COLUMN_KEYS.filter(
    (key) => !DEFAULT_VISIBLE_COLUMN_KEYS.has(key),
);

/**
 * Resolve the saved column order against the current set of columns:
 * drop stale/unknown keys, then append any column the user has never
 * ordered (e.g. one added after they last saved) at the end.
 */
function sanitizeColumnOrder(savedOrder: string[] | null): string[] {
    const known =
        savedOrder?.filter((key) => ALL_COLUMN_KEYS.includes(key)) ?? [];
    const missing = ALL_COLUMN_KEYS.filter((key) => !known.includes(key));

    return [...known, ...missing];
}

// Columns whose responsive visibility class matches an existing column
// (undefined = always visible, no `hidden ...:table-cell`).
const COLUMN_RESPONSIVE_CLASS: Record<string, string | undefined> = {
    external_id: undefined,
    first_name: undefined,
    last_name: undefined,
    mobile: 'hidden lg:table-cell',
    company: 'hidden md:table-cell',
    email: 'hidden sm:table-cell',
    country_code: 'hidden lg:table-cell',
    sale_status: undefined,
    advertiser_name: 'hidden lg:table-cell',
    is_ftd: 'hidden md:table-cell',
    affiliate_name: 'hidden lg:table-cell',
    assigned_to: 'hidden md:table-cell',
    lead_created_at: 'hidden md:table-cell',
};

function columnClassName(key: string): string {
    return key in COLUMN_RESPONSIVE_CLASS
        ? (COLUMN_RESPONSIVE_CLASS[key] ?? '')
        : 'hidden lg:table-cell';
}

const CELL_EXTRA_CLASS: Record<string, string> = {
    first_name: 'font-medium',
    last_name: 'font-medium',
    email: 'font-semibold',
};

type PageProps = {
    auth: Auth;
    byStatus: Record<string, number>;
    leads: Paginator<Lead>;
    companies?: Pick<Company, 'id' | 'name'>[];
    salesReps: Pick<User, 'id' | 'name' | 'company_id'>[];
    viewLead: Lead | null;
    hiddenColumns: string[] | null;
    columnOrder: string[] | null;
    filterOptions: {
        countries: string[];
        affiliates: string[];
        advertisers: string[];
        saleStatuses: string[];
    };
    filters: {
        search: string;
        sale_status: string[];
        live_lead_status: string | null;
        country: string[];
        advertiser: string | null;
        affiliate: string | null;
        company_id: number | null;
        assigned_to: number | null;
        range: string;
        from: string | null;
        to: string | null;
    };
};

export default function LeadsIndex() {
    const {
        auth,
        leads,
        companies,
        salesReps,
        viewLead,
        hiddenColumns,
        columnOrder,
        filterOptions,
        filters,
    } = usePage<PageProps>().props;
    const [search, setSearch] = useState(filters.search);
    const [viewingLead, setViewingLead] = useState<Lead | null>(null);
    const [resendingLead, setResendingLead] = useState<Lead | null>(null);
    const [hidden, setHidden] = useState(
        hiddenColumns ?? DEFAULT_HIDDEN_COLUMNS,
    );
    const [order, setOrder] = useState(sanitizeColumnOrder(columnOrder));
    const canAssignLeads = auth.permissions?.includes('assign-leads');
    const canDeleteLeads = auth.permissions?.includes('delete-leads');
    const canResendLeads = auth.permissions?.includes('resend-leads');
    const selection = useRowSelection(leads.data, leads.current_page);

    const columnsByKey = new Map(
        LEAD_COLUMNS.map((column) => [column.key, column]),
    );

    const updateColumnPreferences = (
        nextOrder: string[],
        nextHidden: string[],
    ) => {
        setOrder(nextOrder);
        setHidden(nextHidden);
        router.patch(
            LeadsController.updateColumnPreferences().url,
            { hidden_columns: nextHidden, column_order: nextOrder },
            { preserveState: true, preserveScroll: true, only: [] },
        );
    };

    const visibleColumns = order
        .map((key) => columnsByKey.get(key))
        .filter(
            (column): column is (typeof LEAD_COLUMNS)[number] =>
                column !== undefined &&
                !hidden.includes(column.key) &&
                (column.key !== 'company' || !!companies) &&
                (column.key !== 'assigned_to' || salesReps.length > 0),
        );

    const renderLeadCell = (key: string, lead: Lead) => {
        switch (key) {
            case 'first_name':
                return lead.first_name ?? '—';
            case 'last_name':
                return lead.last_name ?? '—';
            case 'external_id':
                return <CopyableLeadId externalId={lead.external_id} />;
            case 'mobile':
                return lead.mobile ?? '—';
            case 'company':
                return lead.company?.name ?? '—';
            case 'email':
                return lead.email ?? '—';
            case 'country_code':
                return lead.country_code ?? '—';
            case 'sale_status':
                return lead.sale_status ? (
                    <Badge
                        variant="outline"
                        className={statusBadgeClass(lead.sale_status)}
                    >
                        {lead.sale_status}
                    </Badge>
                ) : (
                    '—'
                );
            case 'advertiser_name':
                return lead.advertiser_name ? (
                    <Badge variant="outline" className={GREEN_BADGE_CLASS}>
                        {lead.advertiser_name}
                    </Badge>
                ) : (
                    '—'
                );
            case 'is_ftd':
                return lead.is_ftd ? (
                    <Badge variant="outline" className={GREEN_BADGE_CLASS}>
                        Yes
                    </Badge>
                ) : (
                    '—'
                );
            case 'affiliate_name':
                return lead.affiliate_name ?? '—';
            case 'assigned_to': {
                const companyReps = salesReps.filter(
                    (rep) => rep.company_id === lead.company_id,
                );

                return canAssignLeads && companyReps.length > 0 ? (
                    <Select
                        value={
                            lead.assigned_to
                                ? String(lead.assigned_to)
                                : 'unassigned'
                        }
                        onValueChange={(value) =>
                            router.patch(
                                assignLead(lead.id).url,
                                {
                                    assigned_to:
                                        value === 'unassigned'
                                            ? null
                                            : Number(value),
                                },
                                { preserveScroll: true, preserveState: true },
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
                                {companyReps.map((rep) => (
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
                ) : (
                    (lead.assignee?.name ?? '—')
                );
            }
            case 'lead_created_at':
                return lead.lead_created_at
                    ? new Date(lead.lead_created_at).toLocaleDateString()
                    : '—';
            case 'request_id':
                return lead.request_id ?? '—';
            case 'ip_address':
                return lead.ip_address ?? '—';
            case 'offer_name':
                return lead.offer_name ?? '—';
            case 'status':
                return lead.status ? (
                    <Badge
                        variant="outline"
                        className={statusBadgeClass(lead.status)}
                    >
                        {lead.status}
                    </Badge>
                ) : (
                    '—'
                );
            case 'ftd_released':
                return lead.ftd_released ? 'Yes' : '—';
            case 'created_at':
                return formatDate(lead.created_at);
            case 'updated_at':
                return formatDate(lead.updated_at);
            case 'live_lead_status':
                return (
                    LIVE_LEAD_STATUS_OPTIONS.find(
                        (option) => option.value === lead.live_lead_status,
                    )?.label ?? '—'
                );
            case 'is_live':
            case 'needs_review':
            case 'is_proxy':
                return metaYesNo(lead, key);
            case 'fraud_flags': {
                const flags = lead.meta?.fraud_flags;

                return Array.isArray(flags) && flags.length > 0
                    ? flags.join(', ')
                    : '—';
            }
            default:
                return metaText(lead, key);
        }
    };

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
                <div className="flex flex-col gap-5">
                    <div className="flex items-center justify-between">
                        <Heading
                            className="mb-0"
                            title="Leads"
                            description={
                                companies
                                    ? "Leads pulled from all companies' CRMs"
                                    : "Leads pulled from your company's CRM"
                            }
                        />
                        <RefreshButton />
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

                    <Card className="p-2!">
                        <CardContent className="flex flex-col gap-4 p-2!">
                            <DateRangeFilter
                                filters={filters}
                                onChange={applyFilters}
                            />

                            <div className="flex flex-nowrap items-center gap-4 overflow-x-auto">
                                <SearchableSelect
                                    placeholder="Advertiser"
                                    value={filters.advertiser}
                                    onChange={(advertiser) =>
                                        applyFilters({ advertiser })
                                    }
                                    options={filterOptions.advertisers.map(
                                        (name) => ({
                                            value: name,
                                            label: name,
                                        }),
                                    )}
                                    className="min-w-40 flex-1"
                                />

                                <MultiSelect
                                    placeholder="Country"
                                    selected={filters.country}
                                    onChange={(country) =>
                                        applyFilters({ country })
                                    }
                                    options={filterOptions.countries.map(
                                        (code) => ({
                                            value: code,
                                            label: code,
                                        }),
                                    )}
                                    className="min-w-40 flex-1"
                                />

                                <SearchableSelect
                                    placeholder="Affiliate"
                                    value={filters.affiliate}
                                    onChange={(affiliate) =>
                                        applyFilters({ affiliate })
                                    }
                                    options={filterOptions.affiliates.map(
                                        (name) => ({
                                            value: name,
                                            label: name,
                                        }),
                                    )}
                                    className="min-w-40 flex-1"
                                />

                                <MultiSelect
                                    placeholder="All sale status"
                                    selected={filters.sale_status}
                                    onChange={(sale_status) =>
                                        applyFilters({ sale_status })
                                    }
                                    options={filterOptions.saleStatuses.map(
                                        (status) => ({
                                            value: status,
                                            label: status,
                                        }),
                                    )}
                                    className="min-w-40 flex-1"
                                />

                                <SearchableSelect
                                    placeholder="All Live Leads"
                                    value={filters.live_lead_status}
                                    onChange={(live_lead_status) =>
                                        applyFilters({ live_lead_status })
                                    }
                                    options={LIVE_LEAD_STATUS_OPTIONS}
                                    className="min-w-40 flex-1"
                                />

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
                                                    value === 'all'
                                                        ? null
                                                        : Number(value),
                                            })
                                        }
                                    >
                                        <SelectTrigger className="min-w-40 flex-1">
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
                                                        value={String(
                                                            company.id,
                                                        )}
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
                                                    value === 'all'
                                                        ? null
                                                        : Number(value),
                                            })
                                        }
                                    >
                                        <SelectTrigger className="min-w-40 flex-1">
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

                                <ColumnsMenu
                                    columns={LEAD_COLUMNS}
                                    order={order}
                                    hidden={hidden}
                                    onChange={updateColumnPreferences}
                                />

                                <Dialog>
                                    <DialogTrigger asChild>
                                        <Button
                                            variant="outline"
                                            className="shrink-0"
                                        >
                                            <Download className="size-4" />
                                            Export All
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogTitle>Export leads?</DialogTitle>
                                        <DialogDescription>
                                            This will export all leads matching
                                            your current filters to an Excel
                                            (.xlsx) file.
                                        </DialogDescription>
                                        <DialogFooter className="gap-2">
                                            <DialogClose asChild>
                                                <Button variant="secondary">
                                                    Cancel
                                                </Button>
                                            </DialogClose>
                                            <DialogClose asChild>
                                                <Button
                                                    onClick={() => {
                                                        window.location.href =
                                                            exportLeads({
                                                                query: filters,
                                                            }).url;
                                                    }}
                                                >
                                                    <Download className="size-4" />
                                                    Export
                                                </Button>
                                            </DialogClose>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>
                            </div>

                            <CompactPagination
                                paginator={leads}
                                filters={filters}
                            >
                                <Input
                                    value={search}
                                    onChange={(event) => {
                                        setSearch(event.target.value);
                                        debouncedSearch(event.target.value);
                                    }}
                                    placeholder="Search ID, email, phone, IP…"
                                    className="max-w-sm"
                                />
                            </CompactPagination>
                        </CardContent>
                    </Card>
                </div>

                {(canAssignLeads || canDeleteLeads || canResendLeads) &&
                    selection.selectedIds.length > 0 && (
                        <div className="flex flex-wrap items-center justify-between gap-2 rounded-md border bg-muted/50 px-4 py-2">
                            <span className="text-sm text-muted-foreground">
                                {selection.selectedIds.length} selected
                            </span>

                            <div className="flex flex-wrap items-center gap-2">
                                {canAssignLeads && (
                                    <BulkAssignBar
                                        bare
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
                                                        selection.setSelectedIds(
                                                            [],
                                                        ),
                                                    onFinish,
                                                },
                                            );
                                        }}
                                    />
                                )}

                                {canResendLeads && (
                                    <BulkResendDialog
                                        bare
                                        count={selection.selectedIds.length}
                                        selectedIds={selection.selectedIds}
                                        onDone={() =>
                                            selection.setSelectedIds([])
                                        }
                                    />
                                )}

                                {canDeleteLeads && (
                                    <BulkDeleteBar
                                        bare
                                        count={selection.selectedIds.length}
                                        description="This will permanently delete the selected leads. This action cannot be undone."
                                        onConfirm={(onFinish) => {
                                            router.delete(
                                                LeadsController.bulkDestroy()
                                                    .url,
                                                {
                                                    data: {
                                                        ids: selection.selectedIds,
                                                    },
                                                    preserveScroll: true,
                                                    onSuccess: () =>
                                                        selection.setSelectedIds(
                                                            [],
                                                        ),
                                                    onFinish,
                                                },
                                            );
                                        }}
                                    />
                                )}
                            </div>
                        </div>
                    )}

                <div className="rounded-md border p-2">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                {(canAssignLeads || canDeleteLeads) && (
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
                                {visibleColumns.map((column) => (
                                    <TableHead
                                        key={column.key}
                                        className={columnClassName(column.key)}
                                    >
                                        {column.label}
                                    </TableHead>
                                ))}
                                <TableHead className="w-px" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {leads.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={
                                            visibleColumns.length +
                                            1 +
                                            (canAssignLeads || canDeleteLeads
                                                ? 1
                                                : 0)
                                        }
                                        className="h-24 text-center text-muted-foreground"
                                    >
                                        No leads found.
                                    </TableCell>
                                </TableRow>
                            )}
                            {leads.data.map((lead) => (
                                <TableRow
                                    key={lead.id}
                                    data-state={
                                        selection.isSelected(lead.id)
                                            ? 'selected'
                                            : undefined
                                    }
                                >
                                    {(canAssignLeads || canDeleteLeads) && (
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
                                    {visibleColumns.map((column) => (
                                        <TableCell
                                            key={column.key}
                                            className={cn(
                                                columnClassName(column.key),
                                                CELL_EXTRA_CLASS[column.key],
                                            )}
                                        >
                                            {renderLeadCell(column.key, lead)}
                                        </TableCell>
                                    ))}
                                    <TableCell>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Actions for ${[lead.first_name, lead.last_name].filter(Boolean).join(' ') || 'lead'}`}
                                                >
                                                    <MoreHorizontal className="size-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        setViewingLead(lead)
                                                    }
                                                >
                                                    <Eye />
                                                    View
                                                </DropdownMenuItem>

                                                {canResendLeads && (
                                                    <DropdownMenuItem
                                                        onSelect={() =>
                                                            setResendingLead(
                                                                lead,
                                                            )
                                                        }
                                                    >
                                                        <Send />
                                                        Resend
                                                    </DropdownMenuItem>
                                                )}

                                                {canDeleteLeads && (
                                                    <Dialog>
                                                        <DialogTrigger asChild>
                                                            <DropdownMenuItem
                                                                variant="destructive"
                                                                onSelect={(
                                                                    event,
                                                                ) =>
                                                                    event.preventDefault()
                                                                }
                                                            >
                                                                <Trash2 />
                                                                Delete
                                                            </DropdownMenuItem>
                                                        </DialogTrigger>

                                                        <DialogContent>
                                                            <DialogTitle>
                                                                Delete this
                                                                lead?
                                                            </DialogTitle>
                                                            <DialogDescription>
                                                                This will
                                                                permanently
                                                                delete this
                                                                lead. This
                                                                action cannot be
                                                                undone.
                                                            </DialogDescription>

                                                            <DialogFooter className="gap-2">
                                                                <DialogClose
                                                                    asChild
                                                                >
                                                                    <Button variant="secondary">
                                                                        Cancel
                                                                    </Button>
                                                                </DialogClose>

                                                                <Form
                                                                    {...LeadsController.destroy.form(
                                                                        lead.id,
                                                                    )}
                                                                >
                                                                    {({
                                                                        processing,
                                                                    }) => (
                                                                        <Button
                                                                            variant="destructive"
                                                                            disabled={
                                                                                processing
                                                                            }
                                                                            type="submit"
                                                                        >
                                                                            <Trash2 />
                                                                            Delete
                                                                        </Button>
                                                                    )}
                                                                </Form>
                                                            </DialogFooter>
                                                        </DialogContent>
                                                    </Dialog>
                                                )}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <RequestDetailsDialog
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

                <ResendLeadDialog
                    key={resendingLead?.id ?? 'none'}
                    lead={resendingLead}
                    open={!!resendingLead}
                    onOpenChange={(open) => !open && setResendingLead(null)}
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
