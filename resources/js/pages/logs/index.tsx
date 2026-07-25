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
import { index as logsIndex } from '@/routes/logs';
import type { LogEntry, Paginator } from '@/types';

const LEVELS = [
    'EMERGENCY',
    'ALERT',
    'CRITICAL',
    'ERROR',
    'WARNING',
    'NOTICE',
    'INFO',
    'DEBUG',
];

function levelBadgeClass(level: string): string {
    if (['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR'].includes(level)) {
        return 'border-transparent bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
    }

    if (level === 'WARNING') {
        return 'border-transparent bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400';
    }

    return 'border-transparent bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400';
}

function LogEntryDetailsDialog({
    entry,
    open,
    onOpenChange,
}: {
    entry: LogEntry | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    if (!entry) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{entry.timestamp}</DialogTitle>
                    <DialogDescription>
                        <Badge
                            variant="outline"
                            className={levelBadgeClass(entry.level)}
                        >
                            {entry.level}
                        </Badge>
                    </DialogDescription>
                </DialogHeader>

                <pre className="max-h-96 overflow-auto rounded-md bg-muted p-3 text-xs whitespace-pre-wrap break-words">
                    {entry.body}
                </pre>
            </DialogContent>
        </Dialog>
    );
}

type PageProps = {
    stats: {
        total: number;
        errors: number;
        warnings: number;
        info: number;
    };
    entries: Paginator<LogEntry>;
    filters: {
        search: string;
        level: string | null;
    };
};

export default function LogsIndex() {
    const { stats, entries, filters } = usePage<PageProps>().props;
    const [search, setSearch] = useState(filters.search);
    const [viewingEntry, setViewingEntry] = useState<LogEntry | null>(null);

    const applyFilters = (next: Partial<typeof filters>) => {
        router.get(
            logsIndex().url,
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
            <Head title="Application Logs" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Application Logs"
                        description="Recent entries from the application log file"
                    />
                    <RefreshButton />
                </div>

                <div className="grid gap-4 sm:grid-cols-4">
                    <StatCard label="Total entries" value={stats.total} />
                    <StatCard label="Errors" value={stats.errors} />
                    <StatCard label="Warnings" value={stats.warnings} />
                    <StatCard label="Info" value={stats.info} />
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
                            placeholder="Search log messages…"
                            className="pl-8"
                        />
                    </div>

                    <Select
                        value={filters.level || 'all'}
                        onValueChange={(value) =>
                            applyFilters({
                                level: value === 'all' ? null : value,
                            })
                        }
                    >
                        <SelectTrigger className="w-full sm:w-48">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="all">
                                    All levels
                                </SelectItem>
                                {LEVELS.map((level) => (
                                    <SelectItem key={level} value={level}>
                                        {level}
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
                                <TableHead>Timestamp</TableHead>
                                <TableHead>Level</TableHead>
                                <TableHead>Message</TableHead>
                                <TableHead className="w-px" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {entries.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="py-8 text-center text-sm text-muted-foreground"
                                    >
                                        No log entries found.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                entries.data.map((entry) => (
                                    <TableRow key={entry.id}>
                                        <TableCell className="font-mono text-xs whitespace-nowrap">
                                            {entry.timestamp}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={levelBadgeClass(
                                                    entry.level,
                                                )}
                                            >
                                                {entry.level}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="max-w-lg truncate">
                                            {entry.message}
                                        </TableCell>
                                        <TableCell>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label="View log entry"
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

                <LogEntryDetailsDialog
                    entry={viewingEntry}
                    open={!!viewingEntry}
                    onOpenChange={(open) => !open && setViewingEntry(null)}
                />
            </div>
        </>
    );
}

LogsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Application Logs',
            href: logsIndex(),
        },
    ],
};
