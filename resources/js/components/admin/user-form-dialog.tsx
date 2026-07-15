import { Form } from '@inertiajs/react';
import { Plus, Save } from 'lucide-react';
import { useState } from 'react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
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
import type { Company, User } from '@/types';

const NO_COMPANY = 'none';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    roles: string[];
    companies: Company[];
    user?: User | null;
};

export function UserFormDialog({
    open,
    onOpenChange,
    roles,
    companies,
    user,
}: Props) {
    const isEdit = !!user;
    const [role, setRole] = useState(
        user?.roles?.[0]?.name ??
            roles.find((roleName) => roleName === 'agent') ??
            roles[0] ??
            '',
    );
    const [companyId, setCompanyId] = useState(
        user?.company_id ? String(user.company_id) : NO_COMPANY,
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {isEdit ? 'Edit user' : 'New user'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEdit
                            ? "Update this user's account details"
                            : 'Create a new user account'}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...(isEdit
                        ? UserController.update.form(user.id)
                        : UserController.store.form())}
                    onSuccess={() => onOpenChange(false)}
                    resetOnSuccess
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={user?.name}
                                    required
                                    autoComplete="name"
                                    placeholder="Full name"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    defaultValue={user?.email}
                                    required
                                    autoComplete="username"
                                    placeholder="Email address"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">
                                    {isEdit ? 'New password' : 'Password'}
                                </Label>
                                <Input
                                    id="password"
                                    type="password"
                                    name="password"
                                    required={!isEdit}
                                    autoComplete="new-password"
                                    placeholder={
                                        isEdit
                                            ? 'Leave blank to keep current password'
                                            : 'Password'
                                    }
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirm password
                                </Label>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    name="password_confirmation"
                                    required={!isEdit}
                                    autoComplete="new-password"
                                    placeholder="Confirm password"
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <div
                                className={
                                    companies.length > 0
                                        ? 'grid grid-cols-2 gap-4'
                                        : 'grid gap-2'
                                }
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="role">Role</Label>
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
                                            id="role"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Select a role" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {roles.map((roleName) => (
                                                <SelectItem
                                                    key={roleName}
                                                    value={roleName}
                                                >
                                                    {roleName}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.role} />
                                </div>

                                {companies.length > 0 && (
                                    <div className="grid gap-2">
                                        <Label htmlFor="company_id">
                                            Company
                                        </Label>
                                        <input
                                            type="hidden"
                                            name="company_id"
                                            value={
                                                companyId === NO_COMPANY
                                                    ? ''
                                                    : companyId
                                            }
                                        />
                                        <Select
                                            value={companyId}
                                            onValueChange={setCompanyId}
                                        >
                                            <SelectTrigger
                                                id="company_id"
                                                className="w-full"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value={NO_COMPANY}>
                                                    No company (Parent Admin)
                                                </SelectItem>
                                                {companies.map((company) => (
                                                    <SelectItem
                                                        key={company.id}
                                                        value={String(
                                                            company.id,
                                                        )}
                                                    >
                                                        {company.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={errors.company_id}
                                        />
                                    </div>
                                )}
                            </div>

                            <DialogFooter>
                                <Button disabled={processing}>
                                    {isEdit ? <Save /> : <Plus />}
                                    {isEdit ? 'Save' : 'Create'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
