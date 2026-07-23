import { Head, router, usePage } from '@inertiajs/react';
import { Eye, MoreHorizontal, Search, Send } from 'lucide-react';
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
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import { index as advertisersIndex } from '@/routes/advertisers';
import type { Advertiser, Company, Paginator } from '@/types';

function statusBadgeClass(isActive: boolean): string {
    return isActive
        ? 'border-transparent bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
        : 'border-transparent bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
}

function metaLabel(key: string): string {
    return key
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function isPlainObject(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
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

/**
 * Flattens one level of nested objects (e.g. a `config` blob holding its own
 * named fields like `crm_type`/`api_url`/`affiliate_token`) so each surfaces
 * as its own labeled row instead of a single raw JSON blob.
 */
function flattenMetaEntries(
    meta: Record<string, unknown>,
): [string, unknown][] {
    return Object.entries(meta).flatMap(([key, value]) => {
        if (isPlainObject(value)) {
            const nested = Object.entries(value);

            return nested.length > 0
                ? nested.map(
                      ([nestedKey, nestedValue]) =>
                          [
                              `${metaLabel(key)}: ${metaLabel(nestedKey)}`,
                              nestedValue,
                          ] as [string, unknown],
                  )
                : [];
        }

        return [[key, value] as [string, unknown]];
    });
}

function AdvertiserDetailsDialog({
    advertiser,
    open,
    onOpenChange,
}: {
    advertiser: Advertiser | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    if (!advertiser) {
        return null;
    }

    const meta = advertiser.meta ?? {};
    const metaEntries = flattenMetaEntries(meta).filter(
        ([, value]) => value !== null && value !== '' && value !== undefined,
    );

    const fields: [string, string][] = [
        ['Type', metaValue(advertiser.advertiser_type)],
        ['URL', metaValue(advertiser.url)],
        ['Daily Cap', metaValue(advertiser.daily_cap)],
        ['Hourly Cap', metaValue(advertiser.hourly_cap)],
        ['Default Deal Type', metaValue(advertiser.default_deal_type)],
        [
            'Synced',
            advertiser.synced_at
                ? new Date(advertiser.synced_at).toLocaleString()
                : '—',
        ],
    ];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{advertiser.name}</DialogTitle>
                    <DialogDescription>
                        {advertiser.company && (
                            <span className="mr-2">
                                {advertiser.company.name}
                            </span>
                        )}
                        <Badge
                            variant="outline"
                            className={statusBadgeClass(advertiser.is_active)}
                        >
                            {advertiser.is_active ? 'Active' : 'Inactive'}
                        </Badge>
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

function SendTestLeadDialog({
    advertiser,
    open,
    onOpenChange,
}: {
    advertiser: Advertiser | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    if (!advertiser) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        Send test lead to {advertiser.name}
                    </DialogTitle>
                    <DialogDescription>
                        <Badge
                            variant="outline"
                            className={statusBadgeClass(advertiser.is_active)}
                        >
                            {advertiser.is_active ? 'Active' : 'Inactive'}
                        </Badge>
                    </DialogDescription>
                </DialogHeader>

                <p className="text-sm text-muted-foreground">
                    Send a test lead to verify this advertiser&apos;s
                    integration. This isn&apos;t wired up to a live endpoint
                    yet.
                </p>

                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Close
                    </Button>
                    <Button disabled>
                        <Send />
                        Send Test Lead
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

type PageProps = {
    stats: { total: number; active: number; inactive: number };
    advertisers: Paginator<Advertiser>;
    companies?: Pick<Company, 'id' | 'name'>[];
    filters: {
        search: string;
        status: string | null;
        company_id: number | null;
    };
};

export default function AdvertisersIndex() {
    const { stats, advertisers, companies, filters } =
        usePage<PageProps>().props;
    const [search, setSearch] = useState(filters.search);
    const [viewingAdvertiser, setViewingAdvertiser] =
        useState<Advertiser | null>(null);
    const [sendingTestLeadTo, setSendingTestLeadTo] =
        useState<Advertiser | null>(null);

    const applyFilters = (next: Partial<typeof filters>) => {
        router.get(
            advertisersIndex().url,
            {
                ...filters,
                ...next,
                per_page: advertisers.per_page,
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
            <Head title="Advertisers" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Advertisers"
                        description="Advertisers synced from your company's CRM"
                    />
                    <RefreshButton />
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard label="Total advertisers" value={stats.total} />
                    <StatCard label="Active" value={stats.active} />
                    <StatCard label="Inactive" value={stats.inactive} />
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
                            placeholder="Search by name…"
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
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="inactive">
                                    Inactive
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
                                <TableHead className="hidden lg:table-cell">
                                    Type
                                </TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="hidden lg:table-cell">
                                    Daily Cap
                                </TableHead>
                                <TableHead className="w-px" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {advertisers.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="py-8 text-center text-sm text-muted-foreground"
                                    >
                                        No advertisers synced yet.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                advertisers.data.map((advertiser) => (
                                    <TableRow key={advertiser.id}>
                                        <TableCell className="font-medium">
                                            {advertiser.name}
                                        </TableCell>
                                        {companies && (
                                            <TableCell className="hidden md:table-cell">
                                                {advertiser.company?.name ??
                                                    '—'}
                                            </TableCell>
                                        )}
                                        <TableCell className="hidden lg:table-cell">
                                            {advertiser.advertiser_type ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={statusBadgeClass(
                                                    advertiser.is_active,
                                                )}
                                            >
                                                {advertiser.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="hidden lg:table-cell">
                                            {advertiser.daily_cap !== null ? (
                                                <Badge variant="secondary">
                                                    {advertiser.daily_cap}
                                                </Badge>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Actions for ${advertiser.name}`}
                                                    >
                                                        <MoreHorizontal className="size-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem
                                                        onSelect={() =>
                                                            setViewingAdvertiser(
                                                                advertiser,
                                                            )
                                                        }
                                                    >
                                                        <Eye />
                                                        View
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        onSelect={() =>
                                                            setSendingTestLeadTo(
                                                                advertiser,
                                                            )
                                                        }
                                                    >
                                                        <Send />
                                                        Send Test Leads
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                <DataPagination paginator={advertisers} filters={filters} />

                <AdvertiserDetailsDialog
                    advertiser={viewingAdvertiser}
                    open={!!viewingAdvertiser}
                    onOpenChange={(open) => !open && setViewingAdvertiser(null)}
                />

                <SendTestLeadDialog
                    advertiser={sendingTestLeadTo}
                    open={!!sendingTestLeadTo}
                    onOpenChange={(open) => !open && setSendingTestLeadTo(null)}
                />
            </div>
        </>
    );
}

AdvertisersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Advertisers',
            href: advertisersIndex(),
        },
    ],
};
