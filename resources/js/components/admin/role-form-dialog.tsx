import { Form } from '@inertiajs/react';
import { Plus, Save } from 'lucide-react';
import { useState } from 'react';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { PermissionOption, Role } from '@/types';

const BUILT_IN_ROLES = ['parent-admin', 'child-admin', 'sales-rep'];

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    permissions: PermissionOption[];
    role?: Role | null;
};

export function RoleFormDialog({
    open,
    onOpenChange,
    permissions,
    role,
}: Props) {
    const isEdit = !!role;
    const isBuiltIn = !!role && BUILT_IN_ROLES.includes(role.name);
    const [selected, setSelected] = useState<string[]>(
        role?.permissions?.map((permission) => permission.name) ?? [],
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {isEdit ? 'Edit role' : 'New role'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEdit
                            ? "Update this role's permissions"
                            : 'Create a new role and assign permissions'}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...(isEdit
                        ? RoleController.update.form(role.id)
                        : RoleController.store.form())}
                    onSuccess={() => onOpenChange(false)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={role?.name}
                                    required
                                    readOnly={isBuiltIn}
                                    className={
                                        isBuiltIn
                                            ? 'cursor-not-allowed bg-muted text-muted-foreground'
                                            : undefined
                                    }
                                    placeholder="Role name"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label>Permissions</Label>
                                <div className="space-y-2 rounded-md border p-3">
                                    {permissions.map(
                                        ({ name, description }) => (
                                            <div
                                                key={name}
                                                className="flex items-center gap-2"
                                            >
                                                <Checkbox
                                                    id={`permission-${name}`}
                                                    checked={selected.includes(
                                                        name,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        setSelected(
                                                            (current) =>
                                                                checked
                                                                    ? [
                                                                          ...current,
                                                                          name,
                                                                      ]
                                                                    : current.filter(
                                                                          (
                                                                              selectedName,
                                                                          ) =>
                                                                              selectedName !==
                                                                              name,
                                                                      ),
                                                        )
                                                    }
                                                />
                                                {selected.includes(name) && (
                                                    <input
                                                        type="hidden"
                                                        name="permissions[]"
                                                        value={name}
                                                    />
                                                )}
                                                {description ? (
                                                    <Tooltip>
                                                        <TooltipTrigger
                                                            asChild
                                                        >
                                                            <Label
                                                                htmlFor={`permission-${name}`}
                                                                className="font-normal"
                                                            >
                                                                {name}
                                                            </Label>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            {description}
                                                        </TooltipContent>
                                                    </Tooltip>
                                                ) : (
                                                    <Label
                                                        htmlFor={`permission-${name}`}
                                                        className="font-normal"
                                                    >
                                                        {name}
                                                    </Label>
                                                )}
                                            </div>
                                        ),
                                    )}
                                </div>
                                <InputError message={errors.permissions} />
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
