import { Form, Head, router, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    Check,
    Copy,
    Eye,
    MoreHorizontal,
    Search,
} from 'lucide-react';
import { useState } from 'react';
import LeadsController from '@/actions/App/Http/Controllers/LeadsController';
import { DataPagination } from '@/components/data-pagination';
import { DateRangeFilter } from '@/components/date-range-filter';
import Heading from '@/components/heading';
import { LeadDetailsDialog } from '@/components/lead-details-dialog';
import { RefreshButton } from '@/components/refresh-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { conversions as conversionsIndex } from '@/routes/leads';
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

function releasedBadgeClass(released: boolean): string {
    return released
        ? GREEN_BADGE_CLASS
        : 'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400';
}

type PageProps = {
    auth: Auth;
    byStatus: Record<string, number>;
    leads: Paginator<Lead>;
    companies?: Pick<Company, 'id' | 'name'>[];
    salesReps: Pick<User, 'id' | 'name' | 'company_id'>[];
    viewLead: Lead | null;
    filterOptions: {
        countries: string[];
        affiliates: string[];
        advertisers: string[];
    };
    filters: {
        search: string;
        status: string[];
        country: string[];
        advertiser: string | null;
        affiliate: string | null;
        released: string | null;
        company_id: number | null;
        assigned_to: number | null;
        range: string;
        from: string | null;
        to: string | null;
    };
};

export default function ConversionsIndex() {
    const {
        auth,
        byStatus,
        leads,
        companies,
        salesReps,
        viewLead,
        filterOptions,
        filters,
    } = usePage<PageProps>().props;
    const canReleaseFtd = auth.permissions?.includes('release-ftd');
    const [search, setSearch] = useState(filters.search);
    const [viewingLead, setViewingLead] = useState<Lead | null>(null);
    const [releasingLeadId, setReleasingLeadId] = useState<number | null>(null);

    const applyFilters = (next: Partial<typeof filters>) => {
        router.get(
            conversionsIndex().url,
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
            conversionsIndex().url,
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
            <Head title="Conversions (FTD)" />

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-5">
                    <div className="flex items-center justify-between">
                        <Heading
                            className="mb-0"
                            title="Conversions (FTD)"
                            description={
                                companies
                                    ? "First-time deposits from all companies' CRMs"
                                    : "First-time deposits from your company's CRM"
                            }
                        />
                        <RefreshButton />
                    </div>

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
                                    placeholder="Sale status"
                                    selected={filters.status}
                                    onChange={(status) =>
                                        applyFilters({ status })
                                    }
                                    options={Object.keys(byStatus).map(
                                        (status) => ({
                                            value: status,
                                            label: status,
                                        }),
                                    )}
                                    className="min-w-40 flex-1"
                                />

                                <Select
                                    value={filters.released ?? 'all'}
                                    onValueChange={(value) =>
                                        applyFilters({
                                            released:
                                                value === 'all' ? null : value,
                                        })
                                    }
                                >
                                    <SelectTrigger className="min-w-40 flex-1">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="all">
                                                All FTDs
                                            </SelectItem>
                                            <SelectItem value="released">
                                                Released
                                            </SelectItem>
                                            <SelectItem value="pending">
                                                Pending
                                            </SelectItem>
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
                            </div>

                            <div className="relative w-full">
                                <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(event) => {
                                        setSearch(event.target.value);
                                        debouncedSearch(event.target.value);
                                    }}
                                    placeholder="Search by ID, name, email, phone…"
                                    className="pl-8"
                                />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="rounded-md border p-2">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>First Name</TableHead>
                                <TableHead>Last Name</TableHead>
                                <TableHead className="hidden lg:table-cell">
                                    Lead ID
                                </TableHead>
                                <TableHead className="hidden lg:table-cell">
                                    Phone
                                </TableHead>
                                {companies && (
                                    <TableHead className="hidden md:table-cell">
                                        Company
                                    </TableHead>
                                )}
                                <TableHead className="hidden sm:table-cell">
                                    Email
                                </TableHead>
                                <TableHead className="hidden lg:table-cell">
                                    Country
                                </TableHead>
                                <TableHead>Sale Status</TableHead>
                                <TableHead className="hidden lg:table-cell">
                                    Advertiser
                                </TableHead>
                                <TableHead>Released</TableHead>
                                <TableHead className="hidden lg:table-cell">
                                    Affiliate
                                </TableHead>
                                {salesReps.length > 0 && (
                                    <TableHead className="hidden md:table-cell">
                                        Assigned to
                                    </TableHead>
                                )}
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
                                        colSpan={
                                            (companies ? 1 : 0) +
                                            (salesReps.length > 0 ? 1 : 0) +
                                            10
                                        }
                                        className="py-8 text-center text-sm text-muted-foreground"
                                    >
                                        No FTD leads.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                leads.data.map((lead) => (
                                    <TableRow key={lead.id}>
                                        <TableCell className="font-medium">
                                            {lead.first_name ?? '—'}
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {lead.last_name ?? '—'}
                                        </TableCell>
                                        <TableCell className="hidden lg:table-cell">
                                            <CopyableLeadId
                                                externalId={lead.external_id}
                                            />
                                        </TableCell>
                                        <TableCell className="hidden lg:table-cell">
                                            {lead.mobile ?? '—'}
                                        </TableCell>
                                        {companies && (
                                            <TableCell className="hidden md:table-cell">
                                                {lead.company?.name ?? '—'}
                                            </TableCell>
                                        )}
                                        <TableCell className="hidden font-semibold sm:table-cell">
                                            {lead.email ?? '—'}
                                        </TableCell>
                                        <TableCell className="hidden lg:table-cell">
                                            {lead.country_code ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            {lead.sale_status ? (
                                                <Badge
                                                    variant="outline"
                                                    className={statusBadgeClass(
                                                        lead.sale_status,
                                                    )}
                                                >
                                                    {lead.sale_status}
                                                </Badge>
                                            ) : (
                                                '—'
                                            )}
                                        </TableCell>
                                        <TableCell className="hidden lg:table-cell">
                                            {lead.advertiser_name ? (
                                                <Badge
                                                    variant="outline"
                                                    className={
                                                        GREEN_BADGE_CLASS
                                                    }
                                                >
                                                    {lead.advertiser_name}
                                                </Badge>
                                            ) : (
                                                '—'
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={releasedBadgeClass(
                                                    lead.ftd_released,
                                                )}
                                            >
                                                {lead.ftd_released
                                                    ? 'Released'
                                                    : 'Pending'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="hidden lg:table-cell">
                                            {lead.affiliate_name ?? '—'}
                                        </TableCell>
                                        {salesReps.length > 0 && (
                                            <TableCell className="hidden md:table-cell">
                                                {lead.assignee?.name ?? '—'}
                                            </TableCell>
                                        )}
                                        <TableCell className="hidden md:table-cell">
                                            {lead.lead_created_at
                                                ? new Date(
                                                      lead.lead_created_at,
                                                  ).toLocaleDateString()
                                                : '—'}
                                        </TableCell>
                                        <TableCell>
                                            <Dialog
                                                open={
                                                    releasingLeadId === lead.id
                                                }
                                                onOpenChange={(open) =>
                                                    setReleasingLeadId(
                                                        open ? lead.id : null,
                                                    )
                                                }
                                            >
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
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
                                                                setViewingLead(
                                                                    lead,
                                                                )
                                                            }
                                                        >
                                                            <Eye />
                                                            View
                                                        </DropdownMenuItem>

                                                        {canReleaseFtd &&
                                                            !lead.ftd_released && (
                                                                <DialogTrigger
                                                                    asChild
                                                                >
                                                                    <DropdownMenuItem
                                                                        onSelect={(
                                                                            event,
                                                                        ) =>
                                                                            event.preventDefault()
                                                                        }
                                                                    >
                                                                        <BadgeCheck />
                                                                        Release
                                                                        FTD
                                                                    </DropdownMenuItem>
                                                                </DialogTrigger>
                                                            )}
                                                    </DropdownMenuContent>
                                                </DropdownMenu>

                                                <DialogContent>
                                                    <DialogTitle>
                                                        Release this FTD?
                                                    </DialogTitle>
                                                    <DialogDescription>
                                                        This marks{' '}
                                                        {[
                                                            lead.first_name,
                                                            lead.last_name,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' ') ||
                                                            'this lead'}
                                                        's FTD as released. This
                                                        action cannot be undone.
                                                    </DialogDescription>

                                                    <DialogFooter className="gap-2">
                                                        <DialogClose asChild>
                                                            <Button variant="secondary">
                                                                Cancel
                                                            </Button>
                                                        </DialogClose>

                                                        <Form
                                                            {...LeadsController.releaseFtd.form(
                                                                lead.id,
                                                            )}
                                                            options={{
                                                                preserveScroll: true,
                                                            }}
                                                            onSuccess={() =>
                                                                setReleasingLeadId(
                                                                    null,
                                                                )
                                                            }
                                                        >
                                                            {({
                                                                processing,
                                                            }) => (
                                                                <Button
                                                                    disabled={
                                                                        processing
                                                                    }
                                                                    type="submit"
                                                                >
                                                                    <BadgeCheck />
                                                                    Release FTD
                                                                </Button>
                                                            )}
                                                        </Form>
                                                    </DialogFooter>
                                                </DialogContent>
                                            </Dialog>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
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

ConversionsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Conversions (FTD)',
            href: conversionsIndex(),
        },
    ],
};
