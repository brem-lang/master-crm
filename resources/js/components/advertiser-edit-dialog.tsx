import { Form } from '@inertiajs/react';
import { Save } from 'lucide-react';
import AdvertiserController from '@/actions/App/Http/Controllers/AdvertiserController';
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
import type { Advertiser } from '@/types';

export function AdvertiserEditDialog({
    advertiser,
    open,
    onOpenChange,
}: {
    advertiser: Advertiser | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    if (!advertiser) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit advertiser</DialogTitle>
                    <DialogDescription>
                        Changes are pushed to {advertiser.company?.name ?? 'the'}
                        's CRM before being saved here.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...AdvertiserController.update.form(advertiser.id)}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="space-y-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="advertiser-name">
                                        Name
                                    </Label>
                                    <Input
                                        id="advertiser-name"
                                        name="name"
                                        defaultValue={advertiser.name}
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="advertiser-url">
                                        URL
                                    </Label>
                                    <Input
                                        id="advertiser-url"
                                        name="url"
                                        type="url"
                                        defaultValue={advertiser.url ?? ''}
                                    />
                                    <InputError message={errors.url} />
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="advertiser-daily_cap">
                                            Daily cap
                                        </Label>
                                        <Input
                                            id="advertiser-daily_cap"
                                            name="daily_cap"
                                            type="number"
                                            min={0}
                                            defaultValue={
                                                advertiser.daily_cap ?? ''
                                            }
                                            placeholder="No limit"
                                        />
                                        <InputError
                                            message={errors.daily_cap}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="advertiser-hourly_cap">
                                            Hourly cap
                                        </Label>
                                        <Input
                                            id="advertiser-hourly_cap"
                                            name="hourly_cap"
                                            type="number"
                                            min={0}
                                            defaultValue={
                                                advertiser.hourly_cap ?? ''
                                            }
                                            placeholder="No limit"
                                        />
                                        <InputError
                                            message={errors.hourly_cap}
                                        />
                                    </div>
                                </div>

                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="advertiser-is_active"
                                        name="is_active"
                                        value="1"
                                        defaultChecked={advertiser.is_active}
                                    />
                                    <Label
                                        htmlFor="advertiser-is_active"
                                        className="font-normal"
                                    >
                                        Active
                                    </Label>
                                    <InputError message={errors.is_active} />
                                </div>
                            </div>

                            <DialogFooter className="pt-4">
                                <Button type="submit" disabled={processing}>
                                    <Save />
                                    Save
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
