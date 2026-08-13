import { router } from '@inertiajs/react';
import { Building2, Megaphone, Send, Users } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    bulkResend,
    bulkResendOptions,
} from '@/actions/App/Http/Controllers/LeadsController';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type DirectoryOption = {
    id: number;
    external_id: string;
    name: string;
};

type CompanyOption = {
    id: number;
    name: string;
};

type ResendOptions = {
    affiliates: DirectoryOption[];
    advertisers: DirectoryOption[];
    // Only present (non-null) for users allowed to target a company other
    // than their own — a parent admin with `view-all-customers`.
    companies: CompanyOption[] | null;
};

type Props = {
    count: number;
    selectedIds: number[];
    onDone: () => void;
    /**
     * When true, renders only the action control (dialog trigger button)
     * without the outer wrapper or "N selected" label, so it can be
     * composed alongside other bulk actions in a single row.
     */
    bare?: boolean;
};

export function BulkResendDialog({ count, selectedIds, onDone, bare = false }: Props) {
    const [open, setOpen] = useState(false);
    const [options, setOptions] = useState<ResendOptions | null>(null);
    const [loadingOptions, setLoadingOptions] = useState(false);
    const [companyId, setCompanyId] = useState<number | null>(null);
    const [affiliateId, setAffiliateId] = useState('');
    const [advertiserId, setAdvertiserId] = useState('');
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }

        let cancelled = false;

        (async () => {
            setLoadingOptions(true);

            try {
                const response = await fetch(
                    bulkResendOptions({
                        query: companyId ? { company_id: companyId } : {},
                    }).url,
                    { headers: { Accept: 'application/json' } },
                );
                const body = (await response.json()) as ResendOptions;

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

    const canSend = !!affiliateId && !!advertiserId && !processing;

    const handleConfirm = () => {
        setProcessing(true);

        router.patch(
            bulkResend().url,
            {
                ids: selectedIds,
                company_id: companyId,
                affiliate_id: affiliateId,
                advertiser_id: advertiserId,
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
                    <Send />
                    Resend selected
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Send className="size-4 text-muted-foreground" />
                        Resend {count} selected
                    </DialogTitle>
                    <DialogDescription>
                        Route the selected leads to a company, affiliate, and
                        advertiser of your choice.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    {options?.companies && (
                        <div className="grid gap-2">
                            <Label
                                htmlFor="bulk-resend-company"
                                className="flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <Building2 className="size-3.5" />
                                Destination company
                            </Label>
                            <Select
                                value={companyId ? String(companyId) : ''}
                                onValueChange={(value) => {
                                    setAffiliateId('');
                                    setAdvertiserId('');
                                    setCompanyId(Number(value));
                                }}
                                disabled={loadingOptions}
                            >
                                <SelectTrigger id="bulk-resend-company">
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

                    <div className="grid gap-2">
                        <Label
                            htmlFor="bulk-resend-affiliate"
                            className="flex items-center gap-1.5 text-xs text-muted-foreground"
                        >
                            <Users className="size-3.5" />
                            Affiliate
                        </Label>
                        <Select
                            value={affiliateId}
                            onValueChange={setAffiliateId}
                            disabled={
                                loadingOptions || !options?.affiliates.length
                            }
                        >
                            <SelectTrigger id="bulk-resend-affiliate">
                                <SelectValue
                                    placeholder={
                                        loadingOptions
                                            ? 'Loading…'
                                            : options?.affiliates.length
                                              ? 'Select an affiliate…'
                                              : 'No active affiliates for this company'
                                    }
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    {options?.affiliates.map((affiliate) => (
                                        <SelectItem
                                            key={affiliate.id}
                                            value={affiliate.external_id}
                                        >
                                            {affiliate.name}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid gap-2">
                        <Label
                            htmlFor="bulk-resend-advertiser"
                            className="flex items-center gap-1.5 text-xs text-muted-foreground"
                        >
                            <Megaphone className="size-3.5" />
                            Advertiser
                        </Label>
                        <Select
                            value={advertiserId}
                            onValueChange={setAdvertiserId}
                            disabled={
                                loadingOptions || !options?.advertisers.length
                            }
                        >
                            <SelectTrigger id="bulk-resend-advertiser">
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
                    </div>
                </div>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Button disabled={!canSend} onClick={handleConfirm}>
                        <Send />
                        Resend
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
