import { Form, Head, router, usePage } from '@inertiajs/react';
import {
    Ban,
    Check,
    Copy,
    Eye,
    FlaskConical,
    MoreHorizontal,
    Pencil,
    Search,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import AffiliateController from '@/actions/App/Http/Controllers/AffiliateController';
import { AffiliateEditDialog } from '@/components/affiliate-edit-dialog';
import { BulkAffiliateEditDialog } from '@/components/bulk-affiliate-edit-dialog';
import { BulkAffiliateStatusBar } from '@/components/bulk-affiliate-status-bar';
import { BulkDeleteBar } from '@/components/bulk-delete-bar';
import { DataPagination } from '@/components/data-pagination';
import Heading from '@/components/heading';
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
    DialogHeader,
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
import { useClipboard } from '@/hooks/use-clipboard';
import { useDebouncedCallback } from '@/hooks/use-debounced-callback';
import { useRowSelection } from '@/hooks/use-row-selection';
import { index as affiliatesIndex } from '@/routes/affiliates';
import type { Affiliate, Auth, Company, Paginator } from '@/types';

function statusBadgeClass(isActive: boolean): string {
    return isActive
        ? 'border-transparent bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
        : 'border-transparent bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
}

function CopyableApiKey({ apiKey }: { apiKey: string | null }) {
    const [copiedText, copy] = useClipboard();

    if (!apiKey) {
        return <span className="text-muted-foreground">—</span>;
    }

    const isCopied = copiedText === apiKey;

    return (
        <div className="flex items-center gap-1.5">
            <code className="max-w-40 truncate text-xs">{apiKey}</code>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-6 shrink-0"
                aria-label="Copy API key"
                onClick={() => copy(apiKey)}
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

function TestModeBadge({ testMode }: { testMode: boolean }) {
    return testMode ? (
        <Badge
            variant="outline"
            className="gap-1 border-transparent bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400"
        >
            <FlaskConical className="size-3" />
            Test
        </Badge>
    ) : (
        <Badge variant="outline" className="gap-1 text-muted-foreground">
            <Ban className="size-3" />
            Live
        </Badge>
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

function AffiliateDetailsDialog({
    affiliate,
    open,
    onOpenChange,
}: {
    affiliate: Affiliate | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    if (!affiliate) {
        return null;
    }

    const meta = affiliate.meta ?? {};
    const metaEntries = Object.entries(meta).filter(
        ([, value]) => value !== null && value !== '' && value !== undefined,
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{affiliate.name}</DialogTitle>
                    <DialogDescription>
                        <Badge
                            variant="outline"
                            className={statusBadgeClass(affiliate.is_active)}
                        >
                            {affiliate.is_active ? 'Active' : 'Inactive'}
                        </Badge>
                    </DialogDescription>
                </DialogHeader>

                <div className="max-h-[70vh] space-y-6 overflow-y-auto">
                    <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
                        {affiliate.company && (
                            <div>
                                <dt className="text-muted-foreground">
                                    Company
                                </dt>
                                <dd className="font-medium wrap-break-word">
                                    {affiliate.company.name}
                                </dd>
                            </div>
                        )}
                        <div>
                            <dt className="text-muted-foreground">
                                External ID
                            </dt>
                            <dd className="font-medium wrap-break-word">
                                {affiliate.external_id}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Synced</dt>
                            <dd className="font-medium wrap-break-word">
                                {affiliate.synced_at
                                    ? new Date(
                                          affiliate.synced_at,
                                      ).toLocaleString()
                                    : '—'}
                            </dd>
                        </div>
                        <div className="col-span-2 sm:col-span-3">
                            <dt className="mb-1 text-muted-foreground">
                                API Key
                            </dt>
                            <dd className="font-medium">
                                <CopyableApiKey apiKey={affiliate.api_key} />
                            </dd>
                        </div>
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

function AffiliateStatusDialog({
    affiliate,
    open,
    onOpenChange,
}: {
    affiliate: Affiliate | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [processing, setProcessing] = useState(false);

    if (!affiliate) {
        return null;
    }

    const nextActive = !affiliate.is_active;

    const handleConfirm = () => {
        setProcessing(true);
        router.patch(
            AffiliateController.updateStatus(affiliate.id).url,
            { is_active: nextActive },
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                    onOpenChange(false);
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogTitle>
                    Set {affiliate.name} {nextActive ? 'active' : 'inactive'}?
                </DialogTitle>
                <DialogDescription>
                    This notifies the affiliate&apos;s child CRM that it should
                    be marked as {nextActive ? 'active' : 'inactive'}.
                </DialogDescription>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Button disabled={processing} onClick={handleConfirm}>
                        {nextActive ? <Check /> : <Ban />}
                        {nextActive ? 'Set Active' : 'Set Inactive'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

type PageProps = {
    auth: Auth;
    stats: { total: number; active: number; inactive: number };
    affiliates: Paginator<Affiliate>;
    companies?: Pick<Company, 'id' | 'name'>[];
    filters: {
        search: string;
        status: string | null;
        company_id: number | null;
    };
};

export default function AffiliatesIndex() {
    const { auth, stats, affiliates, companies, filters } =
        usePage<PageProps>().props;
    const [search, setSearch] = useState(filters.search);
    const [viewingAffiliate, setViewingAffiliate] = useState<Affiliate | null>(
        null,
    );
    const canDeleteAffiliates = auth.permissions?.includes('delete-affiliates');
    const canUpdateAffiliateStatus =
        auth.permissions?.includes('update-affiliates');
    const [statusChangeAffiliate, setStatusChangeAffiliate] =
        useState<Affiliate | null>(null);
    const [editingAffiliate, setEditingAffiliate] = useState<Affiliate | null>(
        null,
    );
    const selection = useRowSelection(affiliates.data, affiliates.current_page);

    const applyFilters = (next: Partial<typeof filters>) => {
        router.get(
            affiliatesIndex().url,
            {
                ...filters,
                ...next,
                per_page: affiliates.per_page,
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
            <Head title="Affiliates" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        className="mb-0"
                        title="Affiliates"
                        description="Affiliates synced from your company's CRM"
                    />
                    <RefreshButton />
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard label="Total affiliates" value={stats.total} />
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

                {(canUpdateAffiliateStatus || canDeleteAffiliates) &&
                    selection.selectedIds.length > 0 && (
                        <div className="flex flex-wrap items-center justify-between gap-2 rounded-md border bg-muted/50 px-4 py-2">
                            <span className="text-sm text-muted-foreground">
                                {selection.selectedIds.length} selected
                            </span>

                            <div className="flex flex-wrap items-center gap-2">
                                {canUpdateAffiliateStatus && (
                                    <BulkAffiliateStatusBar
                                        bare
                                        count={selection.selectedIds.length}
                                        onConfirm={(isActive, onFinish) => {
                                            router.patch(
                                                AffiliateController.bulkUpdateStatus()
                                                    .url,
                                                {
                                                    ids: selection.selectedIds,
                                                    is_active: isActive,
                                                },
                                                {
                                                    preserveScroll: true,
                                                    onSuccess: () =>
                                                        selection.setSelectedIds(
                                                            [],
                                                        ),
                                                    onFinish,
                                                },
                                            );
                                        }}
                                    />
                                )}

                                {canUpdateAffiliateStatus && (
                                    <BulkAffiliateEditDialog
                                        bare
                                        count={selection.selectedIds.length}
                                        selectedIds={selection.selectedIds}
                                        onDone={() =>
                                            selection.setSelectedIds([])
                                        }
                                    />
                                )}

                                {canDeleteAffiliates && (
                                    <BulkDeleteBar
                                        bare
                                        count={selection.selectedIds.length}
                                        description="This will permanently delete the selected affiliates. This action cannot be undone."
                                        onConfirm={(onFinish) => {
                                            router.delete(
                                                AffiliateController.bulkDestroy()
                                                    .url,
                                                {
                                                    data: {
                                                        ids: selection.selectedIds,
                                                    },
                                                    preserveScroll: true,
                                                    onSuccess: () =>
                                                        selection.setSelectedIds(
                                                            [],
                                                        ),
                                                    onFinish,
                                                },
                                            );
                                        }}
                                    />
                                )}
                            </div>
                        </div>
                    )}

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                {(canDeleteAffiliates ||
                                    canUpdateAffiliateStatus) && (
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
                                <TableHead>Status</TableHead>
                                <TableHead className="hidden lg:table-cell">
                                    API Key
                                </TableHead>
                                <TableHead className="hidden md:table-cell">
                                    Test Mode
                                </TableHead>
                                <TableHead className="hidden md:table-cell">
                                    Synced
                                </TableHead>
                                <TableHead className="w-px" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {affiliates.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={
                                            canDeleteAffiliates ||
                                            canUpdateAffiliateStatus
                                                ? 8
                                                : 7
                                        }
                                        className="py-8 text-center text-sm text-muted-foreground"
                                    >
                                        No affiliates synced yet.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                affiliates.data.map((affiliate) => (
                                    <TableRow
                                        key={affiliate.id}
                                        data-state={
                                            selection.isSelected(affiliate.id)
                                                ? 'selected'
                                                : undefined
                                        }
                                    >
                                        {(canDeleteAffiliates ||
                                            canUpdateAffiliateStatus) && (
                                            <TableCell>
                                                <Checkbox
                                                    checked={selection.isSelected(
                                                        affiliate.id,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        selection.toggleOne(
                                                            affiliate.id,
                                                            checked === true,
                                                        )
                                                    }
                                                    aria-label={`Select ${affiliate.name}`}
                                                />
                                            </TableCell>
                                        )}
                                        <TableCell className="font-medium">
                                            {affiliate.name}
                                        </TableCell>
                                        {companies && (
                                            <TableCell className="hidden md:table-cell">
                                                {affiliate.company?.name ?? '—'}
                                            </TableCell>
                                        )}
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={statusBadgeClass(
                                                    affiliate.is_active,
                                                )}
                                            >
                                                {affiliate.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="hidden lg:table-cell">
                                            <CopyableApiKey
                                                apiKey={affiliate.api_key}
                                            />
                                        </TableCell>
                                        <TableCell className="hidden md:table-cell">
                                            <TestModeBadge
                                                testMode={
                                                    !!affiliate.meta?.test_mode
                                                }
                                            />
                                        </TableCell>
                                        <TableCell className="hidden md:table-cell">
                                            {affiliate.synced_at
                                                ? new Date(
                                                      affiliate.synced_at,
                                                  ).toLocaleString()
                                                : '—'}
                                        </TableCell>
                                        <TableCell>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Actions for ${affiliate.name}`}
                                                    >
                                                        <MoreHorizontal className="size-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem
                                                        onSelect={() =>
                                                            setViewingAffiliate(
                                                                affiliate,
                                                            )
                                                        }
                                                    >
                                                        <Eye />
                                                        View
                                                    </DropdownMenuItem>

                                                    {canUpdateAffiliateStatus && (
                                                        <DropdownMenuItem
                                                            onSelect={() =>
                                                                setEditingAffiliate(
                                                                    affiliate,
                                                                )
                                                            }
                                                        >
                                                            <Pencil />
                                                            Edit
                                                        </DropdownMenuItem>
                                                    )}

                                                    {canUpdateAffiliateStatus && (
                                                        <DropdownMenuItem
                                                            onSelect={() =>
                                                                setStatusChangeAffiliate(
                                                                    affiliate,
                                                                )
                                                            }
                                                        >
                                                            {affiliate.is_active ? (
                                                                <Ban />
                                                            ) : (
                                                                <Check />
                                                            )}
                                                            {affiliate.is_active
                                                                ? 'Set Inactive'
                                                                : 'Set Active'}
                                                        </DropdownMenuItem>
                                                    )}

                                                    {canDeleteAffiliates && (
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
                                                                    Delete{' '}
                                                                    {
                                                                        affiliate.name
                                                                    }
                                                                    ?
                                                                </DialogTitle>
                                                                <DialogDescription>
                                                                    This will
                                                                    permanently
                                                                    delete this
                                                                    affiliate.
                                                                    This action
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
                                                                        {...AffiliateController.destroy.form(
                                                                            affiliate.id,
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

                <DataPagination paginator={affiliates} filters={filters} />

                <AffiliateDetailsDialog
                    affiliate={viewingAffiliate}
                    open={!!viewingAffiliate}
                    onOpenChange={(open) => !open && setViewingAffiliate(null)}
                />

                <AffiliateStatusDialog
                    affiliate={statusChangeAffiliate}
                    open={!!statusChangeAffiliate}
                    onOpenChange={(open) =>
                        !open && setStatusChangeAffiliate(null)
                    }
                />

                <AffiliateEditDialog
                    key={editingAffiliate?.id ?? 'none'}
                    affiliate={editingAffiliate}
                    open={!!editingAffiliate}
                    onOpenChange={(open) => !open && setEditingAffiliate(null)}
                />
            </div>
        </>
    );
}

AffiliatesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Affiliates',
            href: affiliatesIndex(),
        },
    ],
};
