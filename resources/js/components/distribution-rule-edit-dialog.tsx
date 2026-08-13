import { Form } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { useEffect, useState } from 'react';
import DistributionRulesController, {
    editOptions,
} from '@/actions/App/Http/Controllers/DistributionRulesController';
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
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { DistributionRule } from '@/types';

type AdvertiserOption = {
    id: number;
    external_id: string;
    name: string;
};

export function DistributionRuleEditDialog({
    rule,
    open,
    onOpenChange,
}: {
    rule: DistributionRule | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [advertisers, setAdvertisers] = useState<AdvertiserOption[]>([]);
    const [loadingOptions, setLoadingOptions] = useState(false);
    // The parent remounts this component (via a `key` on the rule id) every
    // time a different rule is opened, so these only need a lazy initializer
    // — no effect-driven sync required.
    const [advertiserId, setAdvertiserId] = useState(
        () => rule?.advertiser_id ?? '',
    );
    const [priorityType, setPriorityType] = useState(
        () => rule?.priority_type ?? '',
    );
    const [isActive, setIsActive] = useState(() => rule?.is_active ?? true);

    useEffect(() => {
        if (!open || !rule) {
            return;
        }

        let cancelled = false;

        (async () => {
            setLoadingOptions(true);

            try {
                const response = await fetch(editOptions(rule.id).url, {
                    headers: { Accept: 'application/json' },
                });
                const body = (await response.json()) as {
                    advertisers: AdvertiserOption[];
                };

                if (!cancelled) {
                    setAdvertisers(body.advertisers);
                }
            } finally {
                if (!cancelled) {
                    setLoadingOptions(false);
                }
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [open, rule]);

    if (!rule) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit distribution rule</DialogTitle>
                    <DialogDescription>
                        Changes are pushed to {rule.company?.name ?? 'the'}
                        's CRM before being saved here.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...DistributionRulesController.update.form(rule.id)}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="space-y-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="rule-advertiser">
                                        Advertiser
                                    </Label>
                                    <Select
                                        value={advertiserId}
                                        onValueChange={setAdvertiserId}
                                        disabled={
                                            loadingOptions ||
                                            !advertisers.length
                                        }
                                    >
                                        <SelectTrigger id="rule-advertiser">
                                            <SelectValue
                                                placeholder={
                                                    loadingOptions
                                                        ? 'Loading…'
                                                        : advertisers.length
                                                          ? 'Select an advertiser…'
                                                          : 'No active advertisers for this company'
                                                }
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                {advertisers.map(
                                                    (advertiser) => (
                                                        <SelectItem
                                                            key={advertiser.id}
                                                            value={
                                                                advertiser.external_id
                                                            }
                                                        >
                                                            {advertiser.name}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                    <input
                                        type="hidden"
                                        name="advertiser_id"
                                        value={advertiserId}
                                    />
                                    <InputError
                                        message={errors.advertiser_id}
                                    />
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="rule-country_code">
                                            Country code
                                        </Label>
                                        <Input
                                            id="rule-country_code"
                                            name="country_code"
                                            defaultValue={
                                                rule.country_code ?? ''
                                            }
                                            maxLength={2}
                                            placeholder="US"
                                            className="uppercase"
                                        />
                                        <InputError
                                            message={errors.country_code}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="rule-priority_type">
                                            Tier
                                        </Label>
                                        <Select
                                            value={priorityType}
                                            onValueChange={setPriorityType}
                                        >
                                            <SelectTrigger id="rule-priority_type">
                                                <SelectValue placeholder="Select a tier…" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    <SelectItem value="primary">
                                                        Primary
                                                    </SelectItem>
                                                    <SelectItem value="fallback">
                                                        Fallback
                                                    </SelectItem>
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <input
                                            type="hidden"
                                            name="priority_type"
                                            value={priorityType}
                                        />
                                        <InputError
                                            message={errors.priority_type}
                                        />
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="rule-priority">
                                            Priority
                                        </Label>
                                        <Input
                                            id="rule-priority"
                                            name="priority"
                                            type="number"
                                            min={0}
                                            defaultValue={rule.priority ?? 0}
                                        />
                                        <InputError
                                            message={errors.priority}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="rule-weight">
                                            Weight
                                        </Label>
                                        <Input
                                            id="rule-weight"
                                            name="weight"
                                            type="number"
                                            min={0}
                                            defaultValue={rule.weight ?? 0}
                                        />
                                        <InputError message={errors.weight} />
                                    </div>
                                </div>

                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="rule-is_active"
                                        name="is_active"
                                        value="1"
                                        checked={isActive}
                                        onCheckedChange={(checked) =>
                                            setIsActive(checked === true)
                                        }
                                    />
                                    <Label
                                        htmlFor="rule-is_active"
                                        className="font-normal"
                                    >
                                        Active
                                    </Label>
                                    <InputError message={errors.is_active} />
                                </div>
                            </div>

                            <DialogFooter className="pt-4">
                                <Button
                                    type="submit"
                                    disabled={processing || !advertiserId}
                                >
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
