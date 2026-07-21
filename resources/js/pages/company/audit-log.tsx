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
import { index as companyAuditLogIndex } from '@/routes/company-audit-log';
import type { AuditLog, Paginator } from '@/types';

function actionLabel(action: string): string {
    return action
        .replace(/[._]/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function actionBadgeClass(action: string): string {
    if (action.endsWith('.created')) {
        return 'border-transparent bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
    }

    if (
        action.endsWith('.deleted') ||
        action.endsWith('.removed_from_company')
    ) {
        return 'border-transparent bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
    }

    return 'border-transparent bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400';
}

function subjectLabel(entry: AuditLog): string {
    const type = entry.subject_type?.split('\\').pop() ?? 'Record';

    return entry.subject_id ? `${type} #${entry.subject_id}` : type;
}

function changeValue(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

function AuditLogDetailsDialog({
    entry,
    open,
    onOpenChange,
}: {
    entry: AuditLog | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    if (!entry) {
        return null;
    }

    const fields: [string, string][] = [
        ['Actor', entry.actor?.name ?? 'System'],
        ['Email', entry.actor?.email ?? '—'],
        ['IP address', entry.ip_address ?? '—'],
        ['Subject', subjectLabel(entry)],
        ['When', new Date(entry.created_at).toLocaleString()],
    ];

    const changeEntries = Object.entries(entry.changes ?? {});

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{actionLabel(entry.action)}</DialogTitle>
                    <DialogDescription>
                        <Badge
                            variant="outline"
                            className={actionBadgeClass(entry.action)}
                        >
                            {entry.action}
                        </Badge>
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
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

                    {changeEntries.length > 0 && (
                        <div>
                            <p className="mb-2 text-sm font-medium text-muted-foreground">
                                Changes
                            </p>
                            <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                                {changeEntries.map(([key, value]) => (
                                    <div key={key}>
                                        <dt className="text-muted-foreground">
                                            {key}
                                        </dt>
                                        <dd className="font-medium wrap-break-word">
                                            {changeValue(value)}
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
    stats: {
        total: number;
        created: number;
        updated: number;
        deleted: number;
    };
    entries: Paginator<AuditLog>;
    actions: string[];
    filters: {
        search: string;
        action: string | null;
    };
};

export default function CompanyAuditLogIndex() {
    const { stats, entries, actions, filters } = usePage<PageProps>().props;
    const [search, setSearch] = useState(filters.search);
    const [viewingEntry, setViewingEntry] = useState<AuditLog | null>(null);

    const applyFilters = (next: Partial<typeof filters>) => {
        router.get(
            companyAuditLogIndex().url,
            {
                ...filters,
                ...next,
                per_page: entries.per_page,
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
            <Head title="Activity Log" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Activity Log"
                        description="Changes to your company and its users"
                    />
                    <RefreshButton />
                </div>

                <div className="grid gap-4 sm:grid-cols-4">
                    <StatCard label="Total entries" value={stats.total} />
                    <StatCard label="Created" value={stats.created} />
                    <StatCard label="Updated" value={stats.updated} />
                    <StatCard label="Deleted" value={stats.deleted} />
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
                            placeholder="Search by actor name or email…"
                            className="pl-8"
                        />
                    </div>

                    <Select
                        value={filters.action || 'all'}
                        onValueChange={(value) =>
                            applyFilters({
                                action: value === 'all' ? null : value,
                            })
                        }
                    >
                        <SelectTrigger className="w-full sm:w-56">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="all">All actions</SelectItem>
                                {actions.map((action) => (
                                    <SelectItem key={action} value={action}>
                                        {actionLabel(action)}
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
                                <TableHead>Actor</TableHead>
                                <TableHead>Action</TableHead>
                                <TableHead>Subject</TableHead>
                                <TableHead className="hidden lg:table-cell">
                                    IP address
                                </TableHead>
                                <TableHead className="hidden md:table-cell">
                                    When
                                </TableHead>
                                <TableHead className="w-px" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {entries.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="py-8 text-center text-sm text-muted-foreground"
                                    >
                                        No activity recorded yet.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                entries.data.map((entry) => (
                                    <TableRow key={entry.id}>
                                        <TableCell className="font-medium">
                                            {entry.actor?.name ?? 'System'}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={actionBadgeClass(
                                                    entry.action,
                                                )}
                                            >
                                                {actionLabel(entry.action)}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {subjectLabel(entry)}
                                        </TableCell>
                                        <TableCell className="hidden font-mono text-xs lg:table-cell">
                                            {entry.ip_address ?? '—'}
                                        </TableCell>
                                        <TableCell className="hidden md:table-cell">
                                            {new Date(
                                                entry.created_at,
                                            ).toLocaleString()}
                                        </TableCell>
                                        <TableCell>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`View ${actionLabel(entry.action)}`}
                                                onClick={() =>
                                                    setViewingEntry(entry)
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

                <DataPagination paginator={entries} filters={filters} />

                <AuditLogDetailsDialog
                    entry={viewingEntry}
                    open={!!viewingEntry}
                    onOpenChange={(open) => !open && setViewingEntry(null)}
                />
            </div>
        </>
    );
}

CompanyAuditLogIndex.layout = {
    breadcrumbs: [
        {
            title: 'Activity Log',
            href: companyAuditLogIndex(),
        },
    ],
};
