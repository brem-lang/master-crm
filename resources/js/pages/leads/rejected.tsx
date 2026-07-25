import { Form, Head, router, usePage } from '@inertiajs/react';
import { Eye, MoreHorizontal, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import LeadsController from '@/actions/App/Http/Controllers/LeadsController';
import { BulkDeleteBar } from '@/components/bulk-delete-bar';
import { DataPagination } from '@/components/data-pagination';
import Heading from '@/components/heading';
import { LeadDetailsDialog } from '@/components/lead-details-dialog';
import { RefreshButton } from '@/components/refresh-button';
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
import { useDebouncedCallback } from '@/hooks/use-debounced-callback';
import { useRowSelection } from '@/hooks/use-row-selection';
import { rejected as rejectedLeadsIndex } from '@/routes/leads';
import type { Auth, Company, Lead, Paginator, User } from '@/types';

function statusBadgeClass(status: string): string {
    switch (status.toLowerCase()) {
        case 'rejected':
            return 'border-transparent bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
        default:
            return 'border-transparent bg-muted text-muted-foreground';
    }
}

type PageProps = {
    auth: Auth;
    total: number;
    ftd: number;
    leads: Paginator<Lead>;
    companies?: Pick<Company, 'id' | 'name'>[];
    salesReps: Pick<User, 'id' | 'name' | 'company_id'>[];
    viewLead: Lead | null;
    filters: {
        search: string;
        status: string | null;
        company_id: number | null;
        assigned_to: number | null;
    };
};

export default function RejectedLeadsIndex() {
    const { auth, total, leads, companies, salesReps, viewLead, filters } =
        usePage<PageProps>().props;
    const [search, setSearch] = useState(filters.search);
    const [viewingLead, setViewingLead] = useState<Lead | null>(null);
    const canDeleteRejectedLeads = auth.permissions?.includes(
        'delete-rejected-leads',
    );
    const selection = useRowSelection(leads.data, leads.current_page);

    const applyFilters = (next: Partial<typeof filters>) => {
        router.get(
            rejectedLeadsIndex().url,
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
            rejectedLeadsIndex().url,
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
            <Head title="Rejected Leads" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Rejected Leads"
                        description={
                            companies
                                ? "Rejected leads from all companies' CRMs"
                                : "Rejected leads from your company's CRM"
                        }
                    />
                    <RefreshButton />
                </div>

                <div className="grid gap-4 sm:grid-cols-1">
                    <StatCard label="Total rejected" value={total} />
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
                            placeholder="Search by name or email…"
                            className="pl-8"
                        />
                    </div>

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

                {canDeleteRejectedLeads && (
                    <BulkDeleteBar
                        count={selection.selectedIds.length}
                        description="This will permanently delete the selected rejected leads. This action cannot be undone."
                        onConfirm={(onFinish) => {
                            router.delete(
                                LeadsController.bulkDestroyRejected().url,
                                {
                                    data: { ids: selection.selectedIds },
                                    preserveScroll: true,
                                    onSuccess: () =>
                                        selection.setSelectedIds([]),
                                    onFinish,
                                },
                            );
                        }}
                    />
                )}

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                {canDeleteRejectedLeads && (
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
                                <TableHead>Name</TableHead>
                                {companies && (
                                    <TableHead className="hidden md:table-cell">
                                        Company
                                    </TableHead>
                                )}
                                <TableHead className="hidden sm:table-cell">
                                    Email
                                </TableHead>
                                <TableHead>Status</TableHead>
                                {salesReps.length > 0 && (
                                    <TableHead className="hidden md:table-cell">
                                        Assigned to
                                    </TableHead>
                                )}
                                <TableHead className="hidden lg:table-cell">
                                    Affiliate
                                </TableHead>
                                <TableHead className="hidden lg:table-cell">
                                    Offer
                                </TableHead>
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
                                            canDeleteRejectedLeads ? 9 : 8
                                        }
                                        className="py-8 text-center text-sm text-muted-foreground"
                                    >
                                        No rejected leads.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                leads.data.map((lead) => (
                                    <TableRow
                                        key={lead.id}
                                        data-state={
                                            selection.isSelected(lead.id)
                                                ? 'selected'
                                                : undefined
                                        }
                                    >
                                        {canDeleteRejectedLeads && (
                                            <TableCell>
                                                <Checkbox
                                                    checked={selection.isSelected(
                                                        lead.id,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        selection.toggleOne(
                                                            lead.id,
                                                            checked === true,
                                                        )
                                                    }
                                                    aria-label={`Select ${[lead.first_name, lead.last_name].filter(Boolean).join(' ') || 'lead'}`}
                                                />
                                            </TableCell>
                                        )}
                                        <TableCell className="font-medium">
                                            {[lead.first_name, lead.last_name]
                                                .filter(Boolean)
                                                .join(' ') || '—'}
                                        </TableCell>
                                        {companies && (
                                            <TableCell className="hidden md:table-cell">
                                                {lead.company?.name ?? '—'}
                                            </TableCell>
                                        )}
                                        <TableCell className="hidden font-semibold sm:table-cell">
                                            {lead.email ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            {lead.status ? (
                                                <Badge
                                                    variant="outline"
                                                    className={statusBadgeClass(
                                                        lead.status,
                                                    )}
                                                >
                                                    {lead.status}
                                                </Badge>
                                            ) : (
                                                '—'
                                            )}
                                        </TableCell>
                                        {salesReps.length > 0 && (
                                            <TableCell className="hidden md:table-cell">
                                                {lead.assignee?.name ?? '—'}
                                            </TableCell>
                                        )}
                                        <TableCell className="hidden lg:table-cell">
                                            {lead.affiliate_name ?? '—'}
                                        </TableCell>
                                        <TableCell className="hidden lg:table-cell">
                                            {lead.offer_name ?? '—'}
                                        </TableCell>
                                        <TableCell className="hidden md:table-cell">
                                            {lead.lead_created_at
                                                ? new Date(
                                                      lead.lead_created_at,
                                                  ).toLocaleDateString()
                                                : '—'}
                                        </TableCell>
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
                                                            setViewingLead(
                                                                lead,
                                                            )
                                                        }
                                                    >
                                                        <Eye />
                                                        View
                                                    </DropdownMenuItem>

                                                    {canDeleteRejectedLeads && (
                                                        <Dialog>
                                                            <DialogTrigger
                                                                asChild
                                                            >
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
                                                                    delete
                                                                    this lead.
                                                                    This
                                                                    action
                                                                    cannot be
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
                                                                        {...LeadsController.destroyRejected.form(
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

RejectedLeadsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Rejected Leads',
            href: rejectedLeadsIndex(),
        },
    ],
};
