import { Head, router, usePage } from '@inertiajs/react';
import { Eye, Search } from 'lucide-react';
import { useState } from 'react';
import { DataPagination } from '@/components/data-pagination';
import Heading from '@/components/heading';
import { RefreshButton } from '@/components/refresh-button';
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
import { index as leadsLogsIndex } from '@/routes/leads-logs';
import type { LeadLog, Paginator } from '@/types';

function LeadLogDetailsDialog({
    entry,
    onOpenChange,
}: {
    entry: LeadLog | null;
    onOpenChange: (open: boolean) => void;
}) {
    if (!entry) {
        return null;
    }

    const fields: [string, string][] = [
        ['Email', entry.email],
        ['Offer', entry.offer_name],
        ['IP address', entry.ip_address ?? '—'],
        ['Received', new Date(entry.created_at).toLocaleString()],
    ];

    return (
        <Dialog open onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Lead log #{entry.id}</DialogTitle>
                    <DialogDescription>{entry.email}</DialogDescription>
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

                    <div>
                        <p className="mb-2 text-sm font-medium text-muted-foreground">
                            Data
                        </p>
                        <pre className="max-h-96 overflow-auto rounded-md bg-muted p-3 font-mono text-xs">
                            {JSON.stringify(entry.data, null, 2)}
                        </pre>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}

type PageProps = {
    entries: Paginator<LeadLog>;
    offers: string[];
    filters: {
        search: string;
        offer: string | null;
    };
};

export default function LeadsLogsIndex() {
    const { entries, offers, filters } = usePage<PageProps>().props;
    const [search, setSearch] = useState(filters.search);
    const [viewingEntry, setViewingEntry] = useState<LeadLog | null>(null);

    const applyFilters = (next: Partial<typeof filters>) => {
        router.get(
            leadsLogsIndex().url,
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
            <Head title="Leads Logs" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Leads Logs"
                        description="Lead payloads received through the leads-logs API"
                    />
                    <RefreshButton />
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
                            placeholder="Search by email or offer…"
                            className="pl-8"
                        />
                    </div>

                    <Select
                        value={filters.offer || 'all'}
                        onValueChange={(value) =>
                            applyFilters({
                                offer: value === 'all' ? null : value,
                            })
                        }
                    >
                        <SelectTrigger className="w-full sm:w-56">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="all">All offers</SelectItem>
                                {offers.map((offer) => (
                                    <SelectItem key={offer} value={offer}>
                                        {offer}
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
                                <TableHead>Email</TableHead>
                                <TableHead>Offer</TableHead>
                                <TableHead className="hidden lg:table-cell">
                                    IP address
                                </TableHead>
                                <TableHead className="hidden md:table-cell">
                                    Received
                                </TableHead>
                                <TableHead className="w-px" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {entries.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="py-8 text-center text-sm text-muted-foreground"
                                    >
                                        No lead logs received yet.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                entries.data.map((entry) => (
                                    <TableRow key={entry.id}>
                                        <TableCell className="font-medium">
                                            {entry.email}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {entry.offer_name}
                                            </Badge>
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
                                                aria-label={`View log for ${entry.email}`}
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

                <LeadLogDetailsDialog
                    entry={viewingEntry}
                    onOpenChange={(open) => !open && setViewingEntry(null)}
                />
            </div>
        </>
    );
}

LeadsLogsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Leads Logs',
            href: leadsLogsIndex(),
        },
    ],
};
