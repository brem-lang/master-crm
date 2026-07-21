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

                            <div className="grid gap-2">
                                <Label htmlFor="website">Website</Label>
                                <Input
                                    id="website"
                                    name="website"
                                    type="url"
                                    defaultValue={company?.website ?? ''}
                                    placeholder="https://example.com"
                                />
                                <InputError message={errors.website} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="api_url">Lead API URL</Label>
                                <Input
                                    id="api_url"
                                    name="api_url"
                                    type="url"
                                    defaultValue={company?.api_url ?? ''}
                                    required
                                    placeholder="https://child-crm.example.com/api"
                                />
                                <InputError message={errors.api_url} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="leads_count_url">
                                    Leads Count API URL
                                </Label>
                                <Input
                                    id="leads_count_url"
                                    name="leads_count_url"
                                    type="url"
                                    defaultValue={
                                        company?.leads_count_url ?? ''
                                    }
                                    placeholder="https://child-crm.example.com/api/leads-count"
                                />
                                <InputError message={errors.leads_count_url} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="affiliates_url">
                                    Affiliates API URL
                                </Label>
                                <Input
                                    id="affiliates_url"
                                    name="affiliates_url"
                                    type="url"
                                    defaultValue={company?.affiliates_url ?? ''}
                                    placeholder="https://child-crm.example.com/api/get-all-affiliates"
                                />
                                <InputError message={errors.affiliates_url} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="affiliate_count_api_url">
                                    Affiliate Count API URL
                                </Label>
                                <Input
                                    id="affiliate_count_api_url"
                                    name="affiliate_count_api_url"
                                    type="url"
                                    defaultValue={
                                        company?.affiliate_count_api_url ?? ''
                                    }
                                    placeholder="https://child-crm.example.com/api/count-affiliates"
                                />
                                <InputError
                                    message={errors.affiliate_count_api_url}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="advertisers_url">
                                    Advertisers API URL
                                </Label>
                                <Input
                                    id="advertisers_url"
                                    name="advertisers_url"
                                    type="url"
                                    defaultValue={
                                        company?.advertisers_url ?? ''
                                    }
                                    placeholder="https://child-crm.example.com/api/get-all-advertisers"
                                />
                                <InputError message={errors.advertisers_url} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="advertiser_count_api_url">
                                    Advertiser Count API URL
                                </Label>
                                <Input
                                    id="advertiser_count_api_url"
                                    name="advertiser_count_api_url"
                                    type="url"
                                    defaultValue={
                                        company?.advertiser_count_api_url ?? ''
                                    }
                                    placeholder="https://child-crm.example.com/api/count-advertisers"
                                />
                                <InputError
                                    message={errors.advertiser_count_api_url}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="api_key">API Key</Label>
                                <Input
                                    id="api_key"
                                    name="api_key"
                                    required={!isEdit}
                                    placeholder={
                                        isEdit
                                            ? 'Leave blank to keep current key'
                                            : 'Shared secret used to authenticate'
                                    }
                                />
                                <InputError message={errors.api_key} />
                            </div>

                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="is_active"
                                    name="is_active"
                                    value="1"
                                    defaultChecked={company?.is_active ?? true}
                                />
                                <Label
                                    htmlFor="is_active"
                                    className="font-normal"
                                >
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
