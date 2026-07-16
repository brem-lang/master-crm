import { Form, Head, router, usePage } from '@inertiajs/react';
import { MoreHorizontal, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import { RoleFormDialog } from '@/components/admin/role-form-dialog';
import { DataPagination } from '@/components/data-pagination';
import Heading from '@/components/heading';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { index as rolesIndex } from '@/routes/roles';
import type { Paginator, Role } from '@/types';

const BUILT_IN_ROLES = ['parent-admin', 'child-admin', 'sales-rep'];

type Filters = {
    search: string;
    permission: string;
};

type Stats = {
    total: number;
    permissions: number;
};

type PageProps = {
    roles: Paginator<Role>;
    permissions: string[];
    filters: Filters;
    stats: Stats;
};

export default function RolesIndex() {
    const { roles, permissions, filters, stats } = usePage<PageProps>().props;
    const [editingRole, setEditingRole] = useState<Role | null>(null);
    const [createOpen, setCreateOpen] = useState(false);
    const [search, setSearch] = useState(filters.search);

    const applyFilters = (next: Partial<Filters>) => {
        router.get(
            rolesIndex().url,
            {
                ...filters,
                ...next,
                per_page: roles.per_page,
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
            <Head title="Roles & Permissions" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Roles & Permissions"
                        description="Manage roles and their assigned permissions"
                    />

                    <Button onClick={() => setCreateOpen(true)}>
                        <Plus />
                        New role
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <StatCard label="Total roles" value={stats.total} />
                    <StatCard
                        label="Total permissions"
                        value={stats.permissions}
                    />
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
                            placeholder="Search by role name…"
                            className="pl-8"
                        />
                    </div>

                    <Select
                        value={filters.permission || 'all'}
                        onValueChange={(value) =>
                            applyFilters({
                                permission: value === 'all' ? '' : value,
                            })
                        }
                    >
                        <SelectTrigger className="w-48">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="all">
                                    All permissions
                                </SelectItem>
                                {permissions.map((permission) => (
                                    <SelectItem
                                        key={permission}
                                        value={permission}
                                    >
                                        {permission}
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
                                <TableHead>Name</TableHead>
                                <TableHead>Permissions</TableHead>
                                <TableHead className="w-px" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {roles.data.map((role) => {
                                const isBuiltIn = BUILT_IN_ROLES.includes(
                                    role.name,
                                );

                                return (
                                    <TableRow key={role.id}>
                                        <TableCell className="font-medium">
                                            {role.name}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                {role.permissions?.map(
                                                    (permission) => (
                                                        <Badge
                                                            key={permission.id}
                                                            variant="secondary"
                                                        >
                                                            {permission.name}
                                                        </Badge>
                                                    ),
                                                )}
                                            </div>
                                        </TableCell>
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
                                                                setEditingRole(
                                                                    role,
                                                                )
                                                            }
                                                        >
                                                            <Pencil />
                                                            Edit
                                                        </DropdownMenuItem>

                                                        {!isBuiltIn && (
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
                                                        Delete {role.name}?
                                                    </DialogTitle>
                                                    <DialogDescription>
                                                        This will permanently
                                                        delete this role. This
                                                        action cannot be undone.
                                                    </DialogDescription>

                                                    <DialogFooter className="gap-2">
                                                        <DialogClose asChild>
                                                            <Button variant="secondary">
                                                                Cancel
                                                            </Button>
                                                        </DialogClose>

                                                        <Form
                                                            {...RoleController.destroy.form(
                                                                role.id,
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

                <DataPagination paginator={roles} filters={filters} />
            </div>

            <RoleFormDialog
                open={createOpen}
                onOpenChange={setCreateOpen}
                permissions={permissions}
            />

            <RoleFormDialog
                key={editingRole?.id}
                open={!!editingRole}
                onOpenChange={(open) => !open && setEditingRole(null)}
                permissions={permissions}
                role={editingRole}
            />
        </>
    );
}

RolesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Roles & Permissions',
            href: rolesIndex(),
        },
    ],
};
