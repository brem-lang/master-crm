import { Form } from '@inertiajs/react';
import { Plus, Trash2, UserPlus, X } from 'lucide-react';
import { useState } from 'react';
import CompanyUserController from '@/actions/App/Http/Controllers/Admin/CompanyUserController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ROLE_BADGE_CLASSES, roleLabel } from '@/lib/role-badge';
import type { Company, User } from '@/types';

const ASSIGNABLE_ROLES = ['child-admin', 'sales-rep'];

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    company: Company | null;
    users: User[];
};

export function CompanyUsersDialog({
    open,
    onOpenChange,
    company,
    users,
}: Props) {
    const [addOpen, setAddOpen] = useState(false);
    const [role, setRole] = useState(ASSIGNABLE_ROLES[1]);

    if (!company) {
        return null;
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                onOpenChange(next);

                if (!next) {
                    setAddOpen(false);
                }
            }}
        >
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Users in {company.name}</DialogTitle>
                    <DialogDescription>
                        View, add, and remove users assigned to this company.
                    </DialogDescription>
                </DialogHeader>

                <div className="max-h-80 space-y-2 overflow-y-auto">
                    {users.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No users are assigned to this company yet.
                        </p>
                    )}

                    {users.map((user) => (
                        <div
                            key={user.id}
                            className="flex items-center justify-between gap-2 rounded-md border p-3"
                        >
                            <div className="min-w-0">
                                <p className="truncate text-sm font-medium">
                                    {user.name}
                                </p>
                                <p className="truncate text-xs text-muted-foreground">
                                    {user.email}
                                </p>
                            </div>

                            <div className="flex items-center gap-2">
                                {user.roles?.map((userRole) => (
                                    <Badge
                                        key={userRole.id}
                                        variant="outline"
                                        className={
                                            ROLE_BADGE_CLASSES[userRole.name]
                                        }
                                    >
                                        {roleLabel(userRole.name)}
                                    </Badge>
                                ))}

                                <Dialog>
                                    <DialogTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Remove ${user.name} from ${company.name}`}
                                        >
                                            <X className="size-4" />
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogTitle>
                                            Remove {user.name} from{' '}
                                            {company.name}?
                                        </DialogTitle>
                                        <DialogDescription>
                                            This unassigns the user from this
                                            company. Their account is kept and
                                            they can be reassigned later.
                                        </DialogDescription>

                                        <DialogFooter className="gap-2">
                                            <DialogClose asChild>
                                                <Button variant="secondary">
                                                    Cancel
                                                </Button>
                                            </DialogClose>

                                            <Form
                                                {...CompanyUserController.destroy.form(
                                                    {
                                                        company: company.id,
                                                        user: user.id,
                                                    },
                                                )}
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        variant="destructive"
                                                        disabled={processing}
                                                        type="submit"
                                                    >
                                                        <Trash2 />
                                                        Remove
                                                    </Button>
                                                )}
                                            </Form>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>
                            </div>
                        </div>
                    ))}
                </div>

                {addOpen ? (
                    <Form
                        {...CompanyUserController.store.form(company.id)}
                        onSuccess={() => setAddOpen(false)}
                        resetOnSuccess
                        className="space-y-4 border-t pt-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="new-user-name">Name</Label>
                                    <Input
                                        id="new-user-name"
                                        name="name"
                                        required
                                        autoComplete="name"
                                        placeholder="Full name"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="new-user-email">
                                        Email address
                                    </Label>
                                    <Input
                                        id="new-user-email"
                                        type="email"
                                        name="email"
                                        required
                                        autoComplete="username"
                                        placeholder="Email address"
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="new-user-password">
                                        Password
                                    </Label>
                                    <Input
                                        id="new-user-password"
                                        type="password"
                                        name="password"
                                        required
                                        autoComplete="new-password"
                                        placeholder="Password"
                                    />
                                    <InputError message={errors.password} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="new-user-password_confirmation">
                                        Confirm password
                                    </Label>
                                    <Input
                                        id="new-user-password_confirmation"
                                        type="password"
                                        name="password_confirmation"
                                        required
                                        autoComplete="new-password"
                                        placeholder="Confirm password"
                                    />
                                    <InputError
                                        message={errors.password_confirmation}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="new-user-role">Role</Label>
                                    <input
                                        type="hidden"
                                        name="role"
                                        value={role}
                                    />
                                    <Select
                                        value={role}
                                        onValueChange={setRole}
                                    >
                                        <SelectTrigger
                                            id="new-user-role"
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {ASSIGNABLE_ROLES.map(
                                                (roleName) => (
                                                    <SelectItem
                                                        key={roleName}
                                                        value={roleName}
                                                    >
                                                        {roleLabel(roleName)}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.role} />
                                </div>

                                <DialogFooter className="gap-2">
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={() => setAddOpen(false)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button disabled={processing} type="submit">
                                        <Plus />
                                        Create
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                ) : (
                    <DialogFooter>
                        <Button onClick={() => setAddOpen(true)}>
                            <UserPlus />
                            New user
                        </Button>
                    </DialogFooter>
                )}
            </DialogContent>
        </Dialog>
    );
}
