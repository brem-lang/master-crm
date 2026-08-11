import {
    Building2,
    CircleAlert,
    CircleCheck,
    Loader2,
    Mail,
    Megaphone,
    Phone,
    Send,
    Users,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import {
    resend as resendLead,
    resendOptions,
} from '@/actions/App/Http/Controllers/LeadsController';
import { Badge } from '@/components/ui/badge';
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

function FormSection({
    icon: Icon,
    title,
    children,
}: {
    icon: typeof Building2;
    title: string;
    children: ReactNode;
}) {
    return (
        <div className="rounded-lg border bg-card/50 p-4">
            <div className="mb-3 flex items-center gap-2">
                <Icon className="size-4 text-muted-foreground" />
                <h4 className="text-sm font-medium">{title}</h4>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">{children}</div>
        </div>
    );
}

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
                        message:
                            'Could not load affiliates/advertisers for this company.',
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

    const leadName =
        [lead.first_name, lead.last_name].filter(Boolean).join(' ') ||
        'This lead';
    const selectedCompanyName =
        options?.companies?.find((company) => company.id === companyId)?.name ??
        lead.company?.name;
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
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Send className="size-4 text-muted-foreground" />
                        Resend lead
                    </DialogTitle>
                    <DialogDescription>
                        Route {leadName} to a company, affiliate, and advertiser
                        of your choice.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    <div className="rounded-lg border bg-muted/30 p-4">
                        <div className="flex items-center justify-between gap-2">
                            <p className="font-medium">{leadName}</p>
                            {selectedCompanyName && (
                                <Badge variant="outline">
                                    {selectedCompanyName}
                                </Badge>
                            )}
                        </div>
                        <div className="mt-2 flex flex-col gap-1 text-sm text-muted-foreground">
                            {lead.email && (
                                <span className="flex items-center gap-1.5">
                                    <Mail className="size-3.5 shrink-0" />
                                    {lead.email}
                                </span>
                            )}
                            {lead.mobile && (
                                <span className="flex items-center gap-1.5">
                                    <Phone className="size-3.5 shrink-0" />
                                    {lead.mobile}
                                    {lead.country_code &&
                                        ` (${lead.country_code})`}
                                </span>
                            )}
                        </div>
                    </div>

                    {options?.companies && (
                        <FormSection
                            icon={Building2}
                            title="Destination company"
                        >
                            <Select
                                value={companyId ? String(companyId) : ''}
                                onValueChange={(value) =>
                                    switchCompany(Number(value))
                                }
                                disabled={loadingOptions}
                            >
                                <SelectTrigger
                                    id="resend-company"
                                    className="sm:col-span-2"
                                >
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
                        </FormSection>
                    )}

                    <FormSection icon={Send} title="Routing">
                        <div className="grid gap-2">
                            <Label
                                htmlFor="resend-affiliate"
                                className="flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <Users className="size-3.5" />
                                Affiliate
                            </Label>
                            <Select
                                value={affiliateId}
                                onValueChange={(value) => {
                                    setResult(null);
                                    setAffiliateId(value);
                                }}
                                disabled={
                                    loadingOptions ||
                                    !options?.affiliates.length
                                }
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
                                        {options?.affiliates.map(
                                            (affiliate) => (
                                                <SelectItem
                                                    key={affiliate.id}
                                                    value={
                                                        affiliate.external_id
                                                    }
                                                >
                                                    {affiliate.name}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-2">
                            <Label
                                htmlFor="resend-advertiser"
                                className="flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <Megaphone className="size-3.5" />
                                Advertiser
                            </Label>
                            <Select
                                value={advertiserId}
                                onValueChange={(value) => {
                                    setResult(null);
                                    setAdvertiserId(value);
                                }}
                                disabled={
                                    loadingOptions ||
                                    !options?.advertisers.length
                                }
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
                                        {options?.advertisers.map(
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
                        </div>
                    </FormSection>

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
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
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
