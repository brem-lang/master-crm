import { Head, router, usePage } from '@inertiajs/react';
import { Eye } from 'lucide-react';
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
import { index as jobsIndex } from '@/routes/jobs';
import type { Company, JobRun, Paginator } from '@/types';

function statusBadgeClass(success: boolean): string {
    return success
        ? 'border-transparent bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
        : 'border-transparent bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
}

function JobRunDetailsDialog({
    run,
    open,
    onOpenChange,
}: {
    run: JobRun | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    if (!run) {
        return null;
    }

    const fields: [string, string][] = [
        ['Company', run.company?.name ?? '—'],
        ['Triggered by', run.triggered_by],
        ['Attempt', run.attempt ? String(run.attempt) : '—'],
        ['Pulled', String(run.pulled)],
        ['Ran at', new Date(run.created_at).toLocaleString()],
    ];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {run.company?.name ?? 'Job run'} details
                    </DialogTitle>
                    <DialogDescription>
                        <Badge
                            variant="outline"
                            className={statusBadgeClass(run.success)}
                        >
                            {run.success ? 'Success' : 'Failed'}
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

                    <div>
                        <p className="mb-1 text-sm font-medium text-muted-foreground">
                            Message
                        </p>
                        <p className="text-sm wrap-break-word">{run.message}</p>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}

type PageProps = {
    stats: {
        total: number;
        successful: number;
        failed: number;
        pulled: number;
    };
    runs: Paginator<JobRun>;
    companies: Pick<Company, 'id' | 'name'>[];
    filters: {
        status: string | null;
        company_id: number | null;
    };
};

export default function JobsIndex() {
    const { stats, runs, companies, filters } = usePage<PageProps>().props;
    const [viewingRun, setViewingRun] = useState<JobRun | null>(null);

    const applyFilters = (next: Partial<typeof filters>) => {
        router.get(
            jobsIndex().url,
            {
                ...filters,
                ...next,
                per_page: runs.per_page,
                page: 1,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Jobs" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Jobs"
                        description="History of every lead-sync run, manual or scheduled"
                    />
                    <RefreshButton />
                </div>

                <div className="grid gap-4 sm:grid-cols-4">
                    <StatCard label="Total runs" value={stats.total} />
                    <StatCard label="Successful" value={stats.successful} />
                    <StatCard label="Failed" value={stats.failed} />
                    <StatCard label="Leads pulled" value={stats.pulled} />
                </div>

                <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                    <Select
                        value={filters.status ?? 'all'}
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
                                <SelectItem value="success">Success</SelectItem>
                                <SelectItem value="failed">Failed</SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>

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
                </div>

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Company</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">
                                    Pulled
                                </TableHead>
                                <TableHead>Triggered by</TableHead>
                                <TableHead className="hidden lg:table-cell">
                                    Message
                                </TableHead>
                                <TableHead className="hidden md:table-cell">
                                    Ran at
                                </TableHead>
                                <TableHead className="w-px" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {runs.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={7}
                                        className="py-8 text-center text-sm text-muted-foreground"
                                    >
                                        No runs yet.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                runs.data.map((run) => (
                                    <TableRow key={run.id}>
                                        <TableCell className="font-medium">
                                            {run.company?.name ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={statusBadgeClass(
                                                    run.success,
                                                )}
                                            >
                                                {run.success
                                                    ? 'Success'
                                                    : 'Failed'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {run.pulled}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="secondary">
                                                {run.triggered_by}
                                                {run.attempt
                                                    ? ` · attempt ${run.attempt}`
                                                    : ''}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="hidden max-w-xs truncate lg:table-cell">
                                            {run.message}
                                        </TableCell>
                                        <TableCell className="hidden md:table-cell">
                                            {new Date(
                                                run.created_at,
                                            ).toLocaleString()}
                                        </TableCell>
                                        <TableCell>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`View run for ${run.company?.name ?? 'job'}`}
                                                onClick={() =>
                                                    setViewingRun(run)
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

                <DataPagination paginator={runs} filters={filters} />

                <JobRunDetailsDialog
                    run={viewingRun}
                    open={!!viewingRun}
                    onOpenChange={(open) => !open && setViewingRun(null)}
                />
            </div>
        </>
    );
}

JobsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Jobs',
            href: jobsIndex(),
        },
    ],
};
