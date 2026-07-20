import { Form, Head, router, usePage } from '@inertiajs/react';
import {
    Download,
    MoreHorizontal,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import CompanyController from '@/actions/App/Http/Controllers/Admin/CompanyController';
import { CompanyFormDialog } from '@/components/admin/company-form-dialog';
import { CompanyUsersDialog } from '@/components/admin/company-users-dialog';
import { BulkDeleteBar } from '@/components/bulk-delete-bar';
import { DataPagination } from '@/components/data-pagination';
import Heading from '@/components/heading';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { WebsiteStatusBadge } from '@/components/website-status-badge';
import { useDebouncedCallback } from '@/hooks/use-debounced-callback';
import { useRowSelection } from '@/hooks/use-row-selection';
import { relativeTime } from '@/lib/relative-time';
import { index as companiesIndex } from '@/routes/companies';
import type { Company, Paginator, User } from '@/types';

function syncBadgeClass(failureStreak: number): string {
    return failureStreak > 0
        ? 'border-transparent bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
        : 'border-transparent bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
}

type Filters = {
    search: string;
    status: string;
};

type Stats = {
    total: number;
    active: number;
    inactive: number;
};

type PageProps = {
    companies: Paginator<Company>;
    filters: Filters;
    stats: Stats;
    viewCompany: Company | null;
    companyUsers: User[] | null;
};

export default function CompaniesIndex() {
    const { companies, filters, stats, viewCompany, companyUsers } =
        usePage<PageProps>().props;
    const [editingCompany, setEditingCompany] = useState<Company | null>(null);
    const [createOpen, setCreateOpen] = useState(false);
    const [search, setSearch] = useState(filters.search);
    const selection = useRowSelection(companies.data, companies.current_page);

    const applyFilters = (
        next: Partial<Filters & { view_company?: number }>,
    ) => {
        router.get(
            companiesIndex().url,
            {
                ...filters,
                ...next,
                per_page: companies.per_page,
                page: 1,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const openUsersModal = (company: Company) => {
        applyFilters({ view_company: company.id });
    };

    const closeUsersModal = () => {
        router.get(
            companiesIndex().url,
            {
                ...filters,
                per_page: companies.per_page,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const debouncedSearch = useDebouncedCallback((value: string) => {
        applyFilters({ search: value });
    }, 300);

    return (
        <>
            <Head title="Companies" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Companies"
                        description="Manage child companies overseen by this CRM"
                    />

                    <Button onClick={() => setCreateOpen(true)}>
                        <Plus />
                        New company
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard label="Total companies" value={stats.total} />
                    <StatCard label="Active" value={stats.active} />
                    <StatCard label="Inactive" value={stats.inactive} />
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(event) => {
                                setSearch(event.target.value);
                                debouncedSearch(event.target.value);
                            }}
                            placeholder="Search by name or slug…"
                            className="pl-8"
                        />
                    </div>

                    <Select
                        value={filters.status || 'all'}
                        onValueChange={(value) =>
                            applyFilters({
                                status: value === 'all' ? '' : value,
                            })
                        }
                    >
                        <SelectTrigger className="w-36">
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
                </div>

                <BulkDeleteBar
                    count={selection.selectedIds.length}
                    description="This will permanently delete the selected companies. Users assigned to them will keep their account but lose their company assignment. This action cannot be undone."
                    onConfirm={(onFinish) => {
                        router.delete(CompanyController.bulkDestroy().url, {
                            data: { ids: selection.selectedIds },
                            preserveScroll: true,
                            onSuccess: () => selection.setSelectedIds([]),
                            onFinish,
                        });
                    }}
                />

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
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
                                <TableHead>Name</TableHead>
                                <TableHead>Slug</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Website</TableHead>
                                <TableHead>Sync</TableHead>
                                <TableHead>Users</TableHead>
                                <TableHead className="w-px" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {companies.data.map((company) => (
                                <TableRow
                                    key={company.id}
                                    data-state={
                                        selection.isSelected(company.id)
                                            ? 'selected'
                                            : undefined
                                    }
                                >
                                    <TableCell>
                                        <Checkbox
                                            checked={selection.isSelected(
                                                company.id,
                                            )}
                                            onCheckedChange={(checked) =>
                                                selection.toggleOne(
                                                    company.id,
                                                    checked === true,
                                                )
                                            }
                                            aria-label={`Select ${company.name}`}
                                        />
                                    </TableCell>
                                    <TableCell className="font-medium">
                                        {company.name}
                                    </TableCell>
                                    <TableCell>{company.slug}</TableCell>
                                    <TableCell>
                                        <Badge
                                            variant="outline"
                                            className={
                                                company.is_active
                                                    ? 'border-transparent bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                                    : 'border-transparent bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
                                            }
                                        >
                                            {company.is_active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        {company.website ? (
                                            <WebsiteStatusBadge
                                                status={company.website_status}
                                            />
                                        ) : (
                                            <span className="text-xs text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex flex-col gap-1">
                                            <Badge
                                                variant="outline"
                                                className={syncBadgeClass(
                                                    company.failure_streak ?? 0,
                                                )}
                                            >
                                                {(company.failure_streak ?? 0) >
                                                0
                                                    ? `${company.failure_streak} failed in a row`
                                                    : 'Healthy'}
                                            </Badge>
                                            <span className="text-xs text-muted-foreground">
                                                {company.last_synced_at
                                                    ? relativeTime(
                                                          company.last_synced_at,
                                                      )
                                                    : 'Never synced'}
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            asChild
                                            variant="secondary"
                                            className="cursor-pointer hover:bg-secondary/80"
                                        >
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    openUsersModal(company)
                                                }
                                            >
                                                {company.users_count} users
                                            </button>
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                >
                                                    <MoreHorizontal className="size-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        setEditingCompany(
                                                            company,
                                                        )
                                                    }
                                                >
                                                    <Pencil />
                                                    Edit
                                                </DropdownMenuItem>

                                                {company.is_active && (
                                                    <Dialog>
                                                        <DialogTrigger asChild>
                                                            <DropdownMenuItem
                                                                onSelect={(
                                                                    event,
                                                                ) =>
                                                                    event.preventDefault()
                                                                }
                                                            >
                                                                <Download />
                                                                Pull data
                                                            </DropdownMenuItem>
                                                        </DialogTrigger>

                                                        <DialogContent>
                                                            <DialogTitle>
                                                                Pull data from{' '}
                                                                {company.name}?
                                                            </DialogTitle>
                                                            <DialogDescription>
                                                                This will
                                                                contact{' '}
                                                                {company.name}
                                                                &apos;s API to
                                                                check for new
                                                                data. It
                                                                won&apos;t
                                                                change anything
                                                                in this CRM.
                                                            </DialogDescription>

                                                            <DialogFooter className="gap-2">
                                                                <DialogClose
                                                                    asChild
                                                                >
                                                                    <Button variant="secondary">
                                                                        Cancel
                                                                    </Button>
                                                                </DialogClose>

                                                                <DialogClose
                                                                    asChild
                                                                >
                                                                    <Button
                                                                        onClick={() =>
                                                                            router.post(
                                                                                CompanyController.pullData(
                                                                                    company.id,
                                                                                )
                                                                                    .url,
                                                                                {},
                                                                                {
                                                                                    preserveScroll: true,
                                                                                },
                                                                            )
                                                                        }
                                                                    >
                                                                        <Download />
                                                                        Pull
                                                                        data
                                                                    </Button>
                                                                </DialogClose>
                                                            </DialogFooter>
                                                        </DialogContent>
                                                    </Dialog>
                                                )}

                                                {!company.is_active && (
                                                    <Dialog>
                                                        <DialogTrigger asChild>
                                                            <DropdownMenuItem
                                                                onSelect={(
                                                                    event,
                                                                ) =>
                                                                    event.preventDefault()
                                                                }
                                                            >
                                                                <RotateCcw />
                                                                Reactivate
                                                            </DropdownMenuItem>
                                                        </DialogTrigger>

                                                        <DialogContent>
                                                            <DialogTitle>
                                                                Reactivate{' '}
                                                                {company.name}?
                                                            </DialogTitle>
                                                            <DialogDescription>
                                                                Users assigned
                                                                to this company
                                                                will regain
                                                                access to their
                                                                dashboard and
                                                                leads
                                                                immediately.
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
                                                                    {...CompanyController.reactivate.form(
                                                                        company.id,
                                                                    )}
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
                                                                            <RotateCcw />
                                                                            Reactivate
                                                                        </Button>
                                                                    )}
                                                                </Form>
                                                            </DialogFooter>
                                                        </DialogContent>
                                                    </Dialog>
                                                )}

                                                <Dialog>
                                                    <DialogTrigger asChild>
                                                        <DropdownMenuItem
                                                            variant="destructive"
                                                            onSelect={(event) =>
                                                                event.preventDefault()
                                                            }
                                                        >
                                                            <Trash2 />
                                                            Delete
                                                        </DropdownMenuItem>
                                                    </DialogTrigger>

                                                    <DialogContent>
                                                        <DialogTitle>
                                                            Delete{' '}
                                                            {company.name}?
                                                        </DialogTitle>
                                                        <DialogDescription>
                                                            This will
                                                            permanently delete
                                                            this company. Users
                                                            assigned to it will
                                                            keep their account
                                                            but lose their
                                                            company assignment.
                                                            This action cannot
                                                            be undone.
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
                                                                {...CompanyController.destroy.form(
                                                                    company.id,
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
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <DataPagination paginator={companies} filters={filters} />
            </div>

            <CompanyFormDialog open={createOpen} onOpenChange={setCreateOpen} />

            <CompanyFormDialog
                key={editingCompany?.id}
                open={!!editingCompany}
                onOpenChange={(open) => !open && setEditingCompany(null)}
                company={editingCompany}
            />

            <CompanyUsersDialog
                key={viewCompany?.id}
                open={!!viewCompany}
                onOpenChange={(open) => !open && closeUsersModal()}
                company={viewCompany}
                users={companyUsers ?? []}
            />
        </>
    );
}

CompaniesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Companies',
            href: companiesIndex(),
        },
    ],
};
