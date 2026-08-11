import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatJsonValue } from '@/lib/format-json';
import { isLeadDistributionArray } from '@/lib/lead-distributions';
import type { LeadDistribution } from '@/lib/lead-distributions';
import type { Lead } from '@/types';

/**
 * The distribution attempt this rejection modal should explain: the last one
 * that failed and actually recorded a response body, falling back to the
 * last attempt overall so we still show *something* if statuses don't line
 * up 1:1 with the lead's own `rejected` status.
 */
function findRejectedDistribution(
    distributions: LeadDistribution[],
): LeadDistribution | null {
    const rejected = distributions.filter(
        (distribution) =>
            ['rejected', 'failed'].includes(
                (distribution.status ?? '').toLowerCase(),
            ) && 'response' in distribution,
    );

    if (rejected.length > 0) {
        return rejected[rejected.length - 1];
    }

    return distributions.length > 0
        ? distributions[distributions.length - 1]
        : null;
}

export function RejectionDetailsDialog({
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
        ? findRejectedDistribution(distributions)
        : null;

    const advertiserName = distribution?.advertisers?.name ?? 'the advertiser';
    const hasResponse = !!distribution && 'response' in distribution;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle className="text-xl">
                        Rejection Details
                    </DialogTitle>
                    <DialogDescription>
                        {hasDistributions
                            ? `Full error response from ${advertiserName}`
                            : 'No advertiser available'}
                    </DialogDescription>
                </DialogHeader>

                {hasResponse ? (
                    <pre className="max-h-[60vh] overflow-auto rounded-lg border border-zinc-800 bg-zinc-950 p-4 font-mono text-sm wrap-break-word whitespace-pre-wrap text-zinc-100">
                        {formatJsonValue(distribution.response)}
                    </pre>
                ) : hasDistributions ? (
                    <p className="text-sm text-muted-foreground">
                        No error response recorded for this lead.
                    </p>
                ) : null}
            </DialogContent>
        </Dialog>
    );
}
