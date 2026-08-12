import { Head, router, usePage } from '@inertiajs/react';
import { Eye, MoreHorizontal } from 'lucide-react';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import { index as distributionRulesIndex } from '@/routes/distribution-rules';
import type { Company, DistributionRule, Paginator } from '@/types';

function statusBadgeClass(isActive: boolean): string {
    return isActive
        ? 'border-transparent bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
        : 'border-transparent bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
}

function priorityTypeBadgeClass(priorityType: string): string {
    return priorityType === 'primary'
        ? 'border-transparent bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'
        : 'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400';
}

function affiliateLabel(rule: DistributionRule): string {
    return (
        (rule.meta?.affiliate_name as string | undefined) ??
        rule.affiliate_id ??
        '—'
    );
}

function advertiserLabel(rule: DistributionRule): string {
    return (
        (rule.meta?.advertiser_name as string | undefined) ??
        rule.advertiser_id ??
        '—'
    );
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

function DistributionRuleDetailsDialog({
    rule,
    open,
    onOpenChange,
}: {
    rule: DistributionRule | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    if (!rule) {
        return null;
    }

    const meta = rule.meta ?? {};
    const metaEntries = Object.entries(meta).filter(
        ([, value]) => value !== null && value !== '' && value !== undefined,
    );

    const fields: [string, string][] = [
        ['Affiliate', affiliateLabel(rule)],
        ['Advertiser', advertiserLabel(rule)],
        ['Country', metaValue(rule.country_code)],
        ['Leads', metaValue(rule.leads_count)],
        ['Daily Cap', metaValue(rule.daily_cap)],
        ['Hourly Cap', metaValue(rule.hourly_cap)],
        ['Tier', metaValue(rule.priority_type)],
        ['Priority', metaValue(rule.priority)],
        ['Weight', metaValue(rule.weight)],
        ['Start Time', metaValue(rule.start_time)],
        ['End Time', metaValue(rule.end_time)],
        ['Timezone', metaValue(rule.timezone)],
        [
            'Synced',
            rule.synced_at ? new Date(rule.synced_at).toLocaleString() : '—',
        ],
    ];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Distribution Rule</DialogTitle>
                    <DialogDescription>
                        <Badge
                            variant="outline"
                            className={statusBadgeClass(rule.is_active)}
                        >
                            {rule.is_active ? 'Active' : 'Inactive'}
                        </Badge>
                    </DialogDescription>
                </DialogHeader>

                <div className="max-h-[70vh] space-y-6 overflow-y-auto">
                    <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
                        {rule.company && (
                            <div>
                                <dt className="text-muted-foreground">
                                    Company
                                </dt>
                                <dd className="font-medium wrap-break-word">
                                    {rule.company.name}
                                </dd>
                            </div>
                        )}
                        <div>
                            <dt className="text-muted-foreground">
                                External ID
                            </dt>
                            <dd className="font-medium wrap-break-word">
                                {rule.external_id}
                            </dd>
                        </div>
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
    stats: { total: number; active: number; inactive: number };
    rules: Paginator<DistributionRule>;
    companies?: Pick<Company, 'id' | 'name'>[];
    filters: {
        status: string | null;
        priority_type: string | null;
        country_code: string | null;
        company_id: number | null;
    };
};

export default function DistributionRulesIndex() {
    const { stats, rules, companies, filters } = usePage<PageProps>().props;
    const [viewingRule, setViewingRule] = useState<DistributionRule | null>(
        null,
    );

    const applyFilters = (next: Partial<typeof filters>) => {
        router.get(
            distributionRulesIndex().url,
            {
                ...filters,
                ...next,
                per_page: rules.per_page,
                page: 1,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Distribution Rules" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        className="mb-0"
                        title="Distribution Rules"
                        description="Rules synced from your company's CRM that control how leads are distributed to advertisers"
                    />
                    <RefreshButton />
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard label="Total rules" value={stats.total} />
                    <StatCard label="Active" value={stats.active} />
                    <StatCard label="Inactive" value={stats.inactive} />
                </div>

                <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
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

                    <Select
                        value={filters.priority_type || 'all'}
                        onValueChange={(value) =>
                            applyFilters({
                                priority_type: value === 'all' ? null : value,
                            })
                        }
                    >
                        <SelectTrigger className="w-full sm:w-40">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="all">All tiers</SelectItem>
                                <SelectItem value="primary">Primary</SelectItem>
                                <SelectItem value="fallback">
                                    Fallback
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
                                <TableHead>External ID</TableHead>
                                <TableHead>Affiliate</TableHead>
                                <TableHead className="hidden lg:table-cell">
                                    Country
                                </TableHead>
                                <TableHead>Advertiser</TableHead>
                                <TableHead className="hidden lg:table-cell">
                                    Leads
                                </TableHead>
                                <TableHead>Tier</TableHead>
                                <TableHead className="hidden md:table-cell">
                                    Priority
                                </TableHead>
                                <TableHead className="hidden md:table-cell">
                                    Weight
                                </TableHead>
                                <TableHead>Active</TableHead>
                                <TableHead className="w-px" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rules.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={10}
                                        className="py-8 text-center text-sm text-muted-foreground"
                                    >
                                        No distribution rules synced yet.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                rules.data.map((rule) => (
                                    <TableRow key={rule.id}>
                                        <TableCell className="font-mono text-xs">
                                            {rule.external_id.slice(0, 8)}
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {affiliateLabel(rule)}
                                        </TableCell>
                                        <TableCell className="hidden lg:table-cell">
                                            {rule.country_code ? (
                                                <Badge
                                                    variant="outline"
                                                    className="text-muted-foreground"
                                                >
                                                    {rule.country_code}
                                                </Badge>
                                            ) : (
                                                '—'
                                            )}
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {advertiserLabel(rule)}
                                        </TableCell>
                                        <TableCell className="hidden lg:table-cell">
                                            {rule.leads_count}
                                        </TableCell>
                                        <TableCell>
                                            {rule.priority_type ? (
                                                <Badge
                                                    variant="outline"
                                                    className={priorityTypeBadgeClass(
                                                        rule.priority_type,
                                                    )}
                                                >
                                                    {rule.priority_type}
                                                </Badge>
                                            ) : (
                                                '—'
                                            )}
                                        </TableCell>
                                        <TableCell className="hidden md:table-cell">
                                            {rule.priority ?? '—'}
                                        </TableCell>
                                        <TableCell className="hidden md:table-cell">
                                            {rule.weight ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={statusBadgeClass(
                                                    rule.is_active,
                                                )}
                                            >
                                                {rule.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label="Actions for this distribution rule"
                                                    >
                                                        <MoreHorizontal className="size-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem
                                                        onSelect={() =>
                                                            setViewingRule(rule)
                                                        }
                                                    >
                                                        <Eye />
                                                        View
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

                <DataPagination paginator={rules} filters={filters} />

                <DistributionRuleDetailsDialog
                    rule={viewingRule}
                    open={!!viewingRule}
                    onOpenChange={(open) => !open && setViewingRule(null)}
                />
            </div>
        </>
    );
}

DistributionRulesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Distribution Rules',
            href: distributionRulesIndex(),
        },
    ],
};
