import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import {
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    Trash2,
    UserCog,
} from 'lucide-react';
import { useState } from 'react';
import ImpersonateController from '@/actions/App/Http/Controllers/Admin/ImpersonateController';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import { UserFormDialog } from '@/components/admin/user-form-dialog';
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
import { useDebouncedCallback } from '@/hooks/use-debounced-callback';
import { useRowSelection } from '@/hooks/use-row-selection';
import { ROLE_BADGE_CLASSES, roleLabel } from '@/lib/role-badge';
import { index as usersIndex } from '@/routes/users';
import type { Auth, Company, Paginator, User } from '@/types';

type Filters = {
    search: string;
    role: string;
    company_id: string;
};

type Stats = {
    total: number;
    parent_admin: number;
    child_admin: number;
    sales_rep: number;
};

type PageProps = {
    auth: Auth;
    users: Paginator<User>;
    roles: string[];
    companies: Company[];
    filters: Filters;
    stats: Stats;
    viewUser: User | null;
};

export default function UsersIndex() {
    const { auth, users, roles, companies, filters, stats, viewUser } =
        usePage<PageProps>().props;
    const [editingUser, setEditingUser] = useState<User | null>(null);
    const [createOpen, setCreateOpen] = useState(false);
    const [search, setSearch] = useState(filters.search);
    const isParentAdmin = auth.permissions?.includes('manage-companies');
    const selectableUsers = users.data.filter(
        (user) => user.id !== auth.user.id,
    );
    const selection = useRowSelection(selectableUsers, users.current_page);

    const applyFilters = (next: Partial<Filters>) => {
        router.get(
            usersIndex().url,
            {
                ...filters,
                ...next,
                per_page: users.per_page,
                page: 1,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const closeViewUser = () => {
        router.get(
            usersIndex().url,
            {
                ...filters,
                per_page: users.per_page,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const debouncedSearch = useDebouncedCallback((value: string) => {
        applyFilters({ search: value });
    }, 300);

    return (
        <>
            <Head title="Users" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Users"
                        description="Manage user accounts and roles"
                    />

                    <Button onClick={() => setCreateOpen(true)}>
                        <Plus />
                        New user
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Total users" value={stats.total} />
                    {isParentAdmin && (
                        <StatCard
                            label="Parent admins"
                            value={stats.parent_admin}
                        />
                    )}
                    <StatCard label="Child admins" value={stats.child_admin} />
                    <StatCard label="Sales Reps" value={stats.sales_rep} />
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
                            placeholder="Search by name or email…"
                            className="pl-8"
                        />
                    </div>

                    <Select
                        value={filters.role || 'all'}
                        onValueChange={(value) =>
                            applyFilters({ role: value === 'all' ? '' : value })
                        }
                    >
                        <SelectTrigger className="w-36">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="all">All roles</SelectItem>
                                {roles.map((roleName) => (
                                    <SelectItem key={roleName} value={roleName}>
                                        {roleLabel(roleName)}
                                    </SelectItem>
                                ))}
                            </SelectGroup>
                        </SelectContent>
                    </Select>

                    {isParentAdmin && (
                        <Select
                            value={filters.company_id || 'all'}
                            onValueChange={(value) =>
                                applyFilters({
                                    company_id: value === 'all' ? '' : value,
                                })
                            }
                        >
                            <SelectTrigger className="w-44">
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

                <BulkDeleteBar
                    count={selection.selectedIds.length}
                    description="This will permanently delete the selected user accounts. This action cannot be undone."
                    onConfirm={(onFinish) => {
                        router.delete(UserController.bulkDestroy().url, {
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
                                <TableHead>Email</TableHead>
                                <TableHead>Role</TableHead>
                                <TableHead>Status</TableHead>
                                {isParentAdmin && (
                                    <TableHead>Company</TableHead>
                                )}
                                <TableHead className="w-px" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.data.map((user) => {
                                const isSelf = user.id === auth.user.id;
                                const canImpersonate =
                                    !isSelf &&
                                    auth.permissions?.includes(
                                        'impersonate-users',
                                    );

                                return (
                                    <TableRow
                                        key={user.id}
                                        data-state={
                                            selection.isSelected(user.id)
                                                ? 'selected'
                                                : undefined
                                        }
                                    >
                                        <TableCell>
                                            {!isSelf && (
                                                <Checkbox
                                                    checked={selection.isSelected(
                                                        user.id,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        selection.toggleOne(
                                                            user.id,
                                                            checked === true,
                                                        )
                                                    }
                                                    aria-label={`Select ${user.name}`}
                                                />
                                            )}
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {user.name}
                                        </TableCell>
                                        <TableCell>{user.email}</TableCell>
                                        <TableCell>
                                            {user.roles?.map((role) => (
                                                <Badge
                                                    key={role.id}
                                                    variant="outline"
                                                    className={
                                                        ROLE_BADGE_CLASSES[
                                                            role.name
                                                        ]
                                                    }
                                                >
                                                    {roleLabel(role.name)}
                                                </Badge>
                                            ))}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={
                                                    (user.is_active ?? true)
                                                        ? 'border-transparent bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                                        : 'border-transparent bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
                                                }
                                            >
                                                {(user.is_active ?? true)
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </Badge>
                                        </TableCell>
                                        {isParentAdmin && (
                                            <TableCell>
                                                {user.company?.name ?? '—'}
                                            </TableCell>
                                        )}
                                        <TableCell>
                                            <Dialog>
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
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
                                                                setEditingUser(
                                                                    user,
                                                                )
                                                            }
                                                        >
                                                            <Pencil />
                                                            Edit
                                                        </DropdownMenuItem>

                                                        {canImpersonate && (
                                                            <DropdownMenuItem
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={ImpersonateController.start(
                                                                        user.id,
                                                                    )}
                                                                    method="post"
                                                                    as="button"
                                                                    className="w-full"
                                                                    onSuccess={() =>
                                                                        router.flushAll()
                                                                    }
                                                                >
                                                                    <UserCog />
                                                                    Impersonate
                                                                </Link>
                                                            </DropdownMenuItem>
                                                        )}

                                                        {!isSelf && (
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
                                                        )}
                                                    </DropdownMenuContent>
                                                </DropdownMenu>

                                                <DialogContent>
                                                    <DialogTitle>
                                                        Delete {user.name}?
                                                    </DialogTitle>
                                                    <DialogDescription>
                                                        This will permanently
                                                        delete this user
                                                        account. This action
                                                        cannot be undone.
                                                    </DialogDescription>

                                                    <DialogFooter className="gap-2">
                                                        <DialogClose asChild>
                                                            <Button variant="secondary">
                                                                Cancel
                                                            </Button>
                                                        </DialogClose>

                                                        <Form
                                                            {...UserController.destroy.form(
                                                                user.id,
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
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                </div>

                <DataPagination paginator={users} filters={filters} />
            </div>

            <UserFormDialog
                open={createOpen}
                onOpenChange={setCreateOpen}
                roles={roles}
                companies={companies}
            />

            <UserFormDialog
                key={editingUser?.id ?? viewUser?.id}
                open={!!editingUser || !!viewUser}
                onOpenChange={(open) => {
                    if (!open) {
                        setEditingUser(null);

                        if (viewUser) {
                            closeViewUser();
                        }
                    }
                }}
                roles={roles}
                companies={companies}
                user={editingUser ?? viewUser}
            />
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Users',
            href: usersIndex(),
        },
    ],
};
