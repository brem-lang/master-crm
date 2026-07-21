import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Lead } from '@/types';

type LeadDistribution = {
    status?: string;
    sent_at?: string;
    advertisers?: { name?: string };
    request_payload?: unknown;
    response?: unknown;
    [key: string]: unknown;
};

function statusBadgeClass(status: string): string {
    switch (status.toLowerCase()) {
        case 'rejected':
        case 'failed':
            return 'border-transparent bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
        case 'converted':
        case 'sent':
            return 'border-transparent bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
        case 'contacted':
            return 'border-transparent bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400';
        case 'new':
            return 'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400';
        default:
            return 'border-transparent bg-muted text-muted-foreground';
    }
}

function metaLabel(key: string): string {
    return key
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function metaValue(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

function isLeadDistributionArray(value: unknown): value is LeadDistribution[] {
    return Array.isArray(value);
}

function JsonBlock({ label, value }: { label: string; value: unknown }) {
    return (
        <div>
            <p className="mb-1 text-xs font-medium text-muted-foreground">
                {label}
            </p>
            <pre className="max-h-48 overflow-auto rounded-md bg-muted p-2 text-xs wrap-break-word whitespace-pre-wrap">
                {JSON.stringify(value, null, 2)}
            </pre>
        </div>
    );
}

function DistributionCard({
    distribution,
    index,
}: {
    distribution: LeadDistribution;
    index: number;
}) {
    return (
        <div className="space-y-3 rounded-md border p-3">
            <div className="flex items-center justify-between gap-2">
                <span className="text-sm font-medium">
                    {distribution.advertisers?.name ??
                        `Advertiser ${index + 1}`}
                </span>
                <div className="flex items-center gap-2">
                    {distribution.sent_at && (
                        <span className="text-xs text-muted-foreground">
                            {new Date(distribution.sent_at).toLocaleString()}
                        </span>
                    )}
                    {distribution.status && (
                        <Badge
                            variant="outline"
                            className={statusBadgeClass(distribution.status)}
                        >
                            {distribution.status}
                        </Badge>
                    )}
                </div>
            </div>

            {'request_payload' in distribution && (
                <JsonBlock
                    label="Request Payload"
                    value={distribution.request_payload}
                />
            )}

            {'response' in distribution && (
                <JsonBlock label="Response" value={distribution.response} />
            )}
        </div>
    );
}

export function LeadDetailsDialog({
    lead,
    open,
    onOpenChange,
}: {
    lead: Lead | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    if (!lead) {
        return null;
    }

    const fields: [string, string][] = [
        ['Email', metaValue(lead.email)],
        ['Mobile', metaValue(lead.mobile)],
        ['Country', metaValue(lead.country_code)],
        ['IP address', metaValue(lead.ip_address)],
        ['Affiliate', metaValue(lead.affiliate_name)],
        ['Offer', metaValue(lead.offer_name)],
        ['FTD', lead.is_ftd ? 'Yes' : 'No'],
        [
            'Created',
            lead.lead_created_at
                ? new Date(lead.lead_created_at).toLocaleString()
                : '—',
        ],
    ];

    const meta = lead.meta ?? {};
    const { lead_distributions: distributions, ...restMeta } = meta;

    const metaEntries = Object.entries(restMeta).filter(
        ([, value]) => value !== null && value !== '' && value !== undefined,
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle className="text-xl">
                        {[lead.first_name, lead.last_name]
                            .filter(Boolean)
                            .join(' ') || 'Lead details'}
                    </DialogTitle>
                    <DialogDescription>
                        {lead.status ? (
                            <Badge
                                variant="outline"
                                className={statusBadgeClass(lead.status)}
                            >
                                {lead.status}
                            </Badge>
                        ) : null}
                    </DialogDescription>
                </DialogHeader>

                <div className="max-h-[70vh] space-y-6 overflow-y-auto">
                    <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
                        {fields.map(([label, value]) => (
                            <div key={label}>
                                <dt className="text-muted-foreground">
                                    {label}
                                </dt>
                                <dd className="font-medium wrap-break-word">
                                    {value}
                                </dd>
                            </div>
                        ))}
                    </dl>

                    {isLeadDistributionArray(distributions) &&
                        distributions.length > 0 && (
                            <div>
                                <p className="mb-2 text-sm font-medium text-muted-foreground">
                                    Lead Distributions
                                </p>
                                <div className="space-y-3">
                                    {distributions.map(
                                        (distribution, index) => (
                                            <DistributionCard
                                                key={index}
                                                distribution={distribution}
                                                index={index}
                                            />
                                        ),
                                    )}
                                </div>
                            </div>
                        )}

                    {metaEntries.length > 0 && (
                        <div>
                            <p className="mb-2 text-sm font-medium text-muted-foreground">
                                Additional details
                            </p>
                            <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
                                {metaEntries.map(([key, value]) => (
                                    <div key={key}>
                                        <dt className="text-muted-foreground">
                                            {metaLabel(key)}
                                        </dt>
                                        <dd className="font-medium wrap-break-word">
                                            {metaValue(value)}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
