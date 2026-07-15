import { Form } from '@inertiajs/react';
import { Plus, Save } from 'lucide-react';
import CompanyController from '@/actions/App/Http/Controllers/Admin/CompanyController';
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
import type { Company } from '@/types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    company?: Company | null;
};

export function CompanyFormDialog({ open, onOpenChange, company }: Props) {
    const isEdit = !!company;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {isEdit ? 'Edit company' : 'New company'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEdit
                            ? "Update this company's details"
                            : 'Create a new child company'}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...(isEdit
                        ? CompanyController.update.form(company.id)
                        : CompanyController.store.form())}
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
                                    defaultValue={company?.name}
                                    required
                                    placeholder="Company name"
                                />
                                <InputError message={errors.name} />
                            </div>

                            {isEdit && (
                                <div className="grid gap-2">
                                    <Label htmlFor="slug">Slug</Label>
                                    <Input
                                        id="slug"
                                        defaultValue={company.slug}
                                        disabled
                                    />
                                </div>
                            )}

                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="is_active"
                                    name="is_active"
                                    value="1"
                                    defaultChecked={
                                        company?.is_active ?? true
                                    }
                                />
                                <Label htmlFor="is_active" className="font-normal">
                                    Active
                                </Label>
                                <InputError message={errors.is_active} />
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
