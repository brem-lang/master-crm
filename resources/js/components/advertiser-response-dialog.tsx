import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatJsonValue } from '@/lib/format-json';
import {
    getLatestDistribution,
    isLeadDistributionArray,
} from '@/lib/lead-distributions';
import type { Lead } from '@/types';

export function AdvertiserResponseDialog({
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
    const hasResponse = !!distribution && 'response' in distribution;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle className="text-xl">
                        Advertiser Response
                    </DialogTitle>
                    <DialogDescription>
                        {hasDistributions
                            ? `Response from ${advertiserName}`
                            : 'No advertiser available'}
                    </DialogDescription>
                </DialogHeader>

                {hasResponse ? (
                    <pre className="max-h-[60vh] overflow-auto rounded-lg border border-zinc-800 bg-zinc-950 p-4 font-mono text-sm wrap-break-word whitespace-pre-wrap text-zinc-100">
                        {formatJsonValue(distribution.response)}
                    </pre>
                ) : hasDistributions ? (
                    <p className="text-sm text-muted-foreground">
                        No response recorded for this lead.
                    </p>
                ) : null}
            </DialogContent>
        </Dialog>
    );
}
