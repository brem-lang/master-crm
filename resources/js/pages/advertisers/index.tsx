import { Head, router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import { DataPagination } from '@/components/data-pagination';
import Heading from '@/components/heading';
import { RefreshButton } from '@/components/refresh-button';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
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
                                <TableHead className="hidden lg:table-cell">
                                    Default Deal Type
                                </TableHead>
                                <TableHead className="hidden md:table-cell">
                                    Synced
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {advertisers.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={7}
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
                                            {advertiser.daily_cap ?? '—'}
                                        </TableCell>
                                        <TableCell className="hidden lg:table-cell">
                                            {advertiser.default_deal_type ??
                                                '—'}
                                        </TableCell>
                                        <TableCell className="hidden md:table-cell">
                                            {advertiser.synced_at
                                                ? new Date(
                                                      advertiser.synced_at,
                                                  ).toLocaleString()
                                                : '—'}
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                <DataPagination paginator={advertisers} filters={filters} />
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
