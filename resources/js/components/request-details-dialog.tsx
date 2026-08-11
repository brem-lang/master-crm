import { Check, Copy } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useClipboard } from '@/hooks/use-clipboard';
import { formatJsonValue } from '@/lib/format-json';
import {
    getLatestDistribution,
    isLeadDistributionArray,
} from '@/lib/lead-distributions';
import type { Lead } from '@/types';

/**
 * Only the request header we actually control and are willing to show. The
 * real outbound request also carries `Api-Key`/`Authorization` secrets
 * (see MakesChildCrmRequests.php) — those must never reach this UI, so this
 * is a hardcoded constant rather than anything read off the lead/distribution.
 */
const DISPLAYED_HEADERS = { 'Content-Type': 'application/json' };

function darkBoxClass(extra = ''): string {
    return `overflow-auto rounded-lg border border-zinc-800 bg-zinc-950 p-4 font-mono text-sm text-zinc-100 wrap-break-word whitespace-pre-wrap ${extra}`;
}

function CopyableUrl({ url }: { url: string }) {
    const [copiedText, copy] = useClipboard();
    const isCopied = copiedText === url;

    return (
        <div
            className={`${darkBoxClass()} flex items-center justify-between gap-3`}
        >
            <span className="break-all">{url}</span>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-6 shrink-0 text-zinc-100 hover:bg-zinc-800 hover:text-zinc-100"
                aria-label="Copy target URL"
                onClick={() => copy(url)}
            >
                {isCopied ? (
                    <Check className="size-3.5" />
                ) : (
                    <Copy className="size-3.5" />
                )}
            </Button>
        </div>
    );
}

function Section({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <p className="mb-1 text-xs font-medium text-muted-foreground">
                {label}
            </p>
            {children}
        </div>
    );
}

export function RequestDetailsDialog({
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

    const distributions = lead.meta?.lead_distributions;
    const hasDistributions =
        isLeadDistributionArray(distributions) && distributions.length > 0;
    const distribution = hasDistributions
        ? getLatestDistribution(distributions)
        : null;

    const advertiserName = distribution?.advertisers?.name ?? 'the advertiser';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="text-xl">
                        Full Request Details
                    </DialogTitle>
                    <DialogDescription>
                        {hasDistributions
                            ? `Complete request sent to ${advertiserName}`
                            : 'No advertiser available'}
                    </DialogDescription>
                </DialogHeader>

                {hasDistributions && (
                    <div className="max-h-[70vh] space-y-4 overflow-y-auto">
                        <Section label="Target URL">
                            {distribution?.request_url ? (
                                <CopyableUrl url={distribution.request_url} />
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Target URL not recorded for this
                                    distribution.
                                </p>
                            )}
                        </Section>

                        <Section label="Headers">
                            <pre className={darkBoxClass()}>
                                {formatJsonValue(DISPLAYED_HEADERS)}
                            </pre>
                        </Section>

                        <Section label="Request Payload">
                            {distribution &&
                            'request_payload' in distribution ? (
                                <pre className={darkBoxClass('max-h-64')}>
                                    {formatJsonValue(
                                        distribution.request_payload,
                                    )}
                                </pre>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    No request payload recorded for this
                                    distribution.
                                </p>
                            )}
                        </Section>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
