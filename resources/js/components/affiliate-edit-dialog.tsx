import { Form } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { useState } from 'react';
import AffiliateController from '@/actions/App/Http/Controllers/AffiliateController';
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
import type { Affiliate } from '@/types';

function toStringList(value: unknown): string {
    return Array.isArray(value) ? value.join(', ') : '';
}

function parseList(value: string): string[] {
    return value
        .split(',')
        .map((entry) => entry.trim())
        .filter(Boolean);
}

export function AffiliateEditDialog({
    affiliate,
    open,
    onOpenChange,
}: {
    affiliate: Affiliate | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [allowedCountries, setAllowedCountries] = useState(() =>
        toStringList(affiliate?.meta?.allowed_countries),
    );
    const [allowedIps, setAllowedIps] = useState(() =>
        toStringList(affiliate?.meta?.allowed_ips),
    );

    if (!affiliate) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit affiliate</DialogTitle>
                    <DialogDescription>
                        Changes are pushed to {affiliate.company?.name ?? 'the'}
                        's CRM before being saved here.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...AffiliateController.update.form(affiliate.id)}
                    transform={(data) => ({
                        ...data,
                        allowed_countries: parseList(
                            String(data.allowed_countries ?? ''),
                        ).map((code) => code.toUpperCase()),
                        allowed_ips: parseList(String(data.allowed_ips ?? '')),
                    })}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="space-y-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="affiliate-name">
                                        Name
                                    </Label>
                                    <Input
                                        id="affiliate-name"
                                        name="name"
                                        defaultValue={affiliate.name}
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="affiliate-callback_url">
                                        Callback URL
                                    </Label>
                                    <Input
                                        id="affiliate-callback_url"
                                        name="callback_url"
                                        type="url"
                                        defaultValue={
                                            (affiliate.meta
                                                ?.callback_url as string) ?? ''
                                        }
                                    />
                                    <InputError
                                        message={errors.callback_url}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="affiliate-allowed_countries">
                                        Allowed countries
                                    </Label>
                                    <Input
                                        id="affiliate-allowed_countries"
                                        name="allowed_countries"
                                        value={allowedCountries}
                                        onChange={(event) =>
                                            setAllowedCountries(
                                                event.target.value,
                                            )
                                        }
                                        placeholder="US, CA, GB (blank = all countries)"
                                    />
                                    <InputError
                                        message={errors.allowed_countries}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="affiliate-allowed_ips">
                                        Allowed IPs
                                    </Label>
                                    <Input
                                        id="affiliate-allowed_ips"
                                        name="allowed_ips"
                                        value={allowedIps}
                                        onChange={(event) =>
                                            setAllowedIps(event.target.value)
                                        }
                                        placeholder="203.0.113.10, 203.0.113.11"
                                    />
                                    <InputError
                                        message={errors.allowed_ips}
                                    />
                                </div>

                                <div className="flex flex-col gap-3">
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id="affiliate-is_active"
                                            name="is_active"
                                            value="1"
                                            defaultChecked={
                                                affiliate.is_active
                                            }
                                        />
                                        <Label
                                            htmlFor="affiliate-is_active"
                                            className="font-normal"
                                        >
                                            Active
                                        </Label>
                                        <InputError
                                            message={errors.is_active}
                                        />
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id="affiliate-test_mode"
                                            name="test_mode"
                                            value="1"
                                            defaultChecked={Boolean(
                                                affiliate.meta?.test_mode,
                                            )}
                                        />
                                        <Label
                                            htmlFor="affiliate-test_mode"
                                            className="font-normal"
                                        >
                                            Test mode
                                        </Label>
                                        <InputError
                                            message={errors.test_mode}
                                        />
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id="affiliate-ip_whitelist_required"
                                            name="ip_whitelist_required"
                                            value="1"
                                            defaultChecked={Boolean(
                                                affiliate.meta
                                                    ?.ip_whitelist_required,
                                            )}
                                        />
                                        <Label
                                            htmlFor="affiliate-ip_whitelist_required"
                                            className="font-normal"
                                        >
                                            Require IP whitelist
                                        </Label>
                                        <InputError
                                            message={
                                                errors.ip_whitelist_required
                                            }
                                        />
                                    </div>
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
