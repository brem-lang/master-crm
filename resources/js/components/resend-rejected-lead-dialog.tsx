import { router } from '@inertiajs/react';
import {
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
    rejectedResendOptions,
    resendRejected,
} from '@/actions/App/Http/Controllers/LeadsController';
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

type ResendOptions = {
    affiliates: DirectoryOption[];
    advertisers: DirectoryOption[];
};

type ResendResult = {
    success?: boolean;
    message?: string;
    rejection?: { code?: string; message?: string; details?: string };
    external_lead_id?: string;
    request_id?: string;
};

function FormSection({
    icon: Icon,
    title,
    children,
}: {
    icon: typeof Users;
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

export function ResendRejectedLeadDialog({
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
    const [affiliateId, setAffiliateId] = useState('');
    const [advertiserId, setAdvertiserId] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [result, setResult] = useState<ResendResult | null>(null);

    useEffect(() => {
        if (!open || !lead) {
            return;
        }

        let cancelled = false;

        (async () => {
            setLoadingOptions(true);

            try {
                const response = await fetch(
                    rejectedResendOptions(lead.id).url,
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
                            'Could not load affiliates/advertisers for this lead.',
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
        // time it opens, so there's no stale state to reset here.
    }, [open, lead]);

    if (!lead) {
        return null;
    }

    const leadName =
        [lead.first_name, lead.last_name].filter(Boolean).join(' ') ||
        'This lead';
    const canSend = !!affiliateId && !!advertiserId && !submitting;

    const submit = async () => {
        setSubmitting(true);
        setResult(null);

        try {
            const response = await fetch(resendRejected(lead.id).url, {
                method: 'PATCH',
                headers: jsonHeaders(),
                body: JSON.stringify({
                    affiliate_id: affiliateId,
                    advertiser_id: advertiserId,
                }),
            });
            const body = (await response.json()) as ResendResult;
            setResult({ success: response.ok, ...body });

            // The lead is deleted server-side once the child CRM confirms
            // the resend — refresh the table so it drops off immediately
            // instead of lingering until the next full page load.
            if (response.ok) {
                router.reload({ only: ['leads', 'byStatus'] });
            }
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
                        Resend rejected lead
                    </DialogTitle>
                    <DialogDescription>
                        Route {leadName} to a different affiliate and
                        advertiser within the same company.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    <div className="rounded-lg border bg-muted/30 p-4">
                        <p className="font-medium">{leadName}</p>
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

                    <FormSection icon={Send} title="Routing">
                        <div className="grid gap-2">
                            <Label
                                htmlFor="resend-rejected-affiliate"
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
                                <SelectTrigger id="resend-rejected-affiliate">
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
                                htmlFor="resend-rejected-advertiser"
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
                                <SelectTrigger id="resend-rejected-advertiser">
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
                                {result.rejection?.message ??
                                    result.message ??
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
