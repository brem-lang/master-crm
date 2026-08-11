import { CircleAlert, CircleCheck, Loader2, Send } from 'lucide-react';
import { useEffect, useState } from 'react';
import { resend as resendLead, resendOptions } from '@/actions/App/Http/Controllers/LeadsController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
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
import type { Lead } from '@/types';

function xsrfToken(): string {
    return decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );
}

function jsonHeaders(): HeadersInit {
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': xsrfToken(),
    };
}

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
    // than the lead's own — a parent admin with `view-all-customers`.
    companies: CompanyOption[] | null;
};

type ResendResult = {
    success?: boolean;
    message?: string;
    lead_id?: string;
    request_id?: string;
};

export function ResendLeadDialog({
    lead,
    open,
    onOpenChange,
}: {
    lead: Lead | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [options, setOptions] = useState<ResendOptions | null>(null);
    const [loadingOptions, setLoadingOptions] = useState(false);
    const [companyId, setCompanyId] = useState<number | null>(
        () => lead?.company_id ?? null,
    );
    const [affiliateId, setAffiliateId] = useState('');
    const [advertiserId, setAdvertiserId] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [result, setResult] = useState<ResendResult | null>(null);

    useEffect(() => {
        if (!open || !lead || !companyId) {
            return;
        }

        let cancelled = false;

        (async () => {
            setLoadingOptions(true);

            try {
                const response = await fetch(
                    resendOptions(lead.id, { query: { company_id: companyId } })
                        .url,
                    { headers: jsonHeaders() },
                );
                const body = (await response.json()) as ResendOptions;

                if (!cancelled) {
                    setOptions(body);
                }
            } catch {
                if (!cancelled) {
                    setResult({
                        success: false,
                        message: 'Could not load affiliates/advertisers for this company.',
                    });
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
        // The parent remounts this component (via a `key` on the lead id) every
        // time it opens, so there's no stale state to reset here — only the
        // fetch itself needs to run per open, and again whenever the admin
        // switches the target company.
    }, [open, lead, companyId]);

    if (!lead) {
        return null;
    }

    const switchCompany = (nextCompanyId: number) => {
        setResult(null);
        setAffiliateId('');
        setAdvertiserId('');
        setCompanyId(nextCompanyId);
    };

    const canSend = !!affiliateId && !!advertiserId && !submitting;

    const submit = async () => {
        setSubmitting(true);
        setResult(null);

        try {
            const response = await fetch(resendLead(lead.id).url, {
                method: 'PATCH',
                headers: jsonHeaders(),
                body: JSON.stringify({
                    company_id: companyId,
                    affiliate_id: affiliateId,
                    advertiser_id: advertiserId,
                }),
            });
            const body = (await response.json()) as ResendResult;
            setResult({ success: response.ok, ...body });
        } catch {
            setResult({
                success: false,
                message: 'Could not reach the server. Try again.',
            });
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Send className="size-4 text-muted-foreground" />
                        Resend lead
                    </DialogTitle>
                    <DialogDescription>
                        {[lead.first_name, lead.last_name]
                            .filter(Boolean)
                            .join(' ') || lead.email}{' '}
                        will be resent to the company, affiliate, and advertiser
                        you choose below.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    {options?.companies && (
                        <div className="grid gap-2">
                            <Label htmlFor="resend-company">Company</Label>
                            <Select
                                value={companyId ? String(companyId) : ''}
                                onValueChange={(value) =>
                                    switchCompany(Number(value))
                                }
                                disabled={loadingOptions}
                            >
                                <SelectTrigger id="resend-company">
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
                        <Label htmlFor="resend-affiliate">Affiliate</Label>
                        <Select
                            value={affiliateId}
                            onValueChange={(value) => {
                                setResult(null);
                                setAffiliateId(value);
                            }}
                            disabled={loadingOptions || !options?.affiliates.length}
                        >
                            <SelectTrigger id="resend-affiliate">
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
                        <Label htmlFor="resend-advertiser">Advertiser</Label>
                        <Select
                            value={advertiserId}
                            onValueChange={(value) => {
                                setResult(null);
                                setAdvertiserId(value);
                            }}
                            disabled={loadingOptions || !options?.advertisers.length}
                        >
                            <SelectTrigger id="resend-advertiser">
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

                    {result && (
                        <div
                            className={`flex items-start gap-2 rounded-lg border p-3 text-sm ${
                                result.success
                                    ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-900/50 dark:bg-green-900/20 dark:text-green-400'
                                    : 'border-destructive/30 bg-destructive/10 text-destructive'
                            }`}
                        >
                            {result.success ? (
                                <CircleCheck className="mt-0.5 size-4 shrink-0" />
                            ) : (
                                <CircleAlert className="mt-0.5 size-4 shrink-0" />
                            )}
                            <p className="font-medium">
                                {result.message ??
                                    (result.success
                                        ? 'Lead resent.'
                                        : 'Something went wrong.')}
                            </p>
                        </div>
                    )}
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Close
                    </Button>
                    <Button disabled={!canSend} onClick={submit}>
                        {submitting ? (
                            <Loader2 className="animate-spin" />
                        ) : (
                            <Send />
                        )}
                        Resend
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
