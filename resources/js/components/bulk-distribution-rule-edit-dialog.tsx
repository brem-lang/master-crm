import { router } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useEffect, useState } from 'react';
import { bulkEditOptions } from '@/actions/App/Http/Controllers/DistributionRulesController';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { bulkUpdate } from '@/routes/distribution-rules';

type AdvertiserOption = {
    id: number;
    external_id: string;
    name: string;
};

type CompanyOption = {
    id: number;
    name: string;
};

type Options = {
    advertisers: AdvertiserOption[];
    // Only present (non-null) for users allowed to view multiple companies
    // — used here purely to narrow which company's advertiser list to
    // preview, since the actual bulk update validates each rule's
    // advertiser against its own company.
    companies: CompanyOption[] | null;
};

/**
 * A row in the dialog: a checkbox that decides whether this field is part
 * of the bulk edit at all, plus its own input/select — disabled until
 * enabled. Unlike the single-rule edit dialog (which always submits every
 * field), this only sends the fields the admin actually turned on, so a
 * bulk "deactivate these 5 rules" doesn't also force an advertiser/priority
 * on all of them.
 */
function FieldToggle({
    id,
    label,
    enabled,
    onEnabledChange,
    children,
}: {
    id: string;
    label: string;
    enabled: boolean;
    onEnabledChange: (enabled: boolean) => void;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <div className="flex items-center gap-2">
                <Checkbox
                    id={`${id}-enabled`}
                    checked={enabled}
                    onCheckedChange={(checked) =>
                        onEnabledChange(checked === true)
                    }
                />
                <Label htmlFor={`${id}-enabled`} className="font-normal">
                    {label}
                </Label>
            </div>
            {enabled && children}
        </div>
    );
}

export function BulkDistributionRuleEditDialog({
    count,
    selectedIds,
    onDone,
    bare = false,
}: {
    count: number;
    selectedIds: number[];
    onDone: () => void;
    bare?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [options, setOptions] = useState<Options | null>(null);
    const [loadingOptions, setLoadingOptions] = useState(false);
    const [companyId, setCompanyId] = useState<number | null>(null);
    const [processing, setProcessing] = useState(false);

    const [advertiserEnabled, setAdvertiserEnabled] = useState(false);
    const [advertiserId, setAdvertiserId] = useState('');
    const [countryEnabled, setCountryEnabled] = useState(false);
    const [countryCode, setCountryCode] = useState('');
    const [priorityEnabled, setPriorityEnabled] = useState(false);
    const [priority, setPriority] = useState('0');
    const [weightEnabled, setWeightEnabled] = useState(false);
    const [weight, setWeight] = useState('0');
    const [priorityTypeEnabled, setPriorityTypeEnabled] = useState(false);
    const [priorityType, setPriorityType] = useState('primary');
    const [activeEnabled, setActiveEnabled] = useState(false);
    const [isActive, setIsActive] = useState('active');

    useEffect(() => {
        if (!open) {
            return;
        }

        let cancelled = false;

        (async () => {
            setLoadingOptions(true);

            try {
                const response = await fetch(
                    bulkEditOptions({
                        query: companyId ? { company_id: companyId } : {},
                    }).url,
                    { headers: { Accept: 'application/json' } },
                );
                const body = (await response.json()) as Options;

                if (!cancelled) {
                    setOptions(body);
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
    }, [open, companyId]);

    if (count === 0) {
        return null;
    }

    const canSend =
        !processing &&
        (advertiserEnabled ||
            countryEnabled ||
            priorityEnabled ||
            weightEnabled ||
            priorityTypeEnabled ||
            activeEnabled) &&
        (!advertiserEnabled || !!advertiserId);

    const handleConfirm = () => {
        setProcessing(true);

        router.patch(
            bulkUpdate().url,
            {
                ids: selectedIds,
                ...(advertiserEnabled && { advertiser_id: advertiserId }),
                ...(countryEnabled && { country_code: countryCode || null }),
                ...(priorityEnabled && { priority: Number(priority) }),
                ...(weightEnabled && { weight: Number(weight) }),
                ...(priorityTypeEnabled && { priority_type: priorityType }),
                ...(activeEnabled && {
                    is_active: isActive === 'active' ? '1' : '0',
                }),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setOpen(false);
                    onDone();
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    const dialog = (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <Pencil />
                    Edit selected
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit {count} selected</DialogTitle>
                    <DialogDescription>
                        Turn on only the fields you want to change — anything
                        left off keeps each rule's current value.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    {options?.companies && (
                        <div className="grid gap-2">
                            <Label
                                htmlFor="bulk-rule-company"
                                className="flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                Preview advertisers for company
                            </Label>
                            <Select
                                value={companyId ? String(companyId) : ''}
                                onValueChange={(value) => {
                                    setAdvertiserId('');
                                    setCompanyId(Number(value));
                                }}
                                disabled={loadingOptions}
                            >
                                <SelectTrigger id="bulk-rule-company">
                                    <SelectValue placeholder="Select a company…" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        {options.companies.map((company) => (
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
                        </div>
                    )}

                    <FieldToggle
                        id="bulk-rule-advertiser"
                        label="Advertiser"
                        enabled={advertiserEnabled}
                        onEnabledChange={setAdvertiserEnabled}
                    >
                        <Select
                            value={advertiserId}
                            onValueChange={setAdvertiserId}
                            disabled={
                                loadingOptions || !options?.advertisers.length
                            }
                        >
                            <SelectTrigger id="bulk-rule-advertiser-value">
                                <SelectValue
                                    placeholder={
                                        loadingOptions
                                            ? 'Loading…'
                                            : options?.advertisers.length
                                              ? 'Select an advertiser…'
                                              : 'No active advertisers for this company'
                                    }
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    {options?.advertisers.map((advertiser) => (
                                        <SelectItem
                                            key={advertiser.id}
                                            value={advertiser.external_id}
                                        >
                                            {advertiser.name}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </FieldToggle>

                    <FieldToggle
                        id="bulk-rule-country"
                        label="Country code"
                        enabled={countryEnabled}
                        onEnabledChange={setCountryEnabled}
                    >
                        <Input
                            id="bulk-rule-country-value"
                            value={countryCode}
                            onChange={(event) =>
                                setCountryCode(
                                    event.target.value.toUpperCase(),
                                )
                            }
                            maxLength={2}
                            placeholder="US (leave blank to clear)"
                        />
                    </FieldToggle>

                    <div className="grid grid-cols-2 gap-4">
                        <FieldToggle
                            id="bulk-rule-priority"
                            label="Priority"
                            enabled={priorityEnabled}
                            onEnabledChange={setPriorityEnabled}
                        >
                            <Input
                                id="bulk-rule-priority-value"
                                type="number"
                                min={0}
                                value={priority}
                                onChange={(event) =>
                                    setPriority(event.target.value)
                                }
                            />
                        </FieldToggle>

                        <FieldToggle
                            id="bulk-rule-weight"
                            label="Weight"
                            enabled={weightEnabled}
                            onEnabledChange={setWeightEnabled}
                        >
                            <Input
                                id="bulk-rule-weight-value"
                                type="number"
                                min={0}
                                value={weight}
                                onChange={(event) =>
                                    setWeight(event.target.value)
                                }
                            />
                        </FieldToggle>
                    </div>

                    <FieldToggle
                        id="bulk-rule-priority-type"
                        label="Tier"
                        enabled={priorityTypeEnabled}
                        onEnabledChange={setPriorityTypeEnabled}
                    >
                        <Select
                            value={priorityType}
                            onValueChange={setPriorityType}
                        >
                            <SelectTrigger id="bulk-rule-priority-type-value">
                                <SelectValue />
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
                    </FieldToggle>

                    <FieldToggle
                        id="bulk-rule-active"
                        label="Active status"
                        enabled={activeEnabled}
                        onEnabledChange={setActiveEnabled}
                    >
                        <Select value={isActive} onValueChange={setIsActive}>
                            <SelectTrigger id="bulk-rule-active-value">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="active">
                                        Active
                                    </SelectItem>
                                    <SelectItem value="inactive">
                                        Inactive
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </FieldToggle>
                </div>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Button disabled={!canSend} onClick={handleConfirm}>
                        <Pencil />
                        Update {count}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );

    if (bare) {
        return dialog;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-2 rounded-md border bg-muted/50 px-4 py-2">
            <span className="text-sm text-muted-foreground">
                {count} selected
            </span>

            {dialog}
        </div>
    );
}
