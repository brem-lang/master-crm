/**
 * One entry in `Lead.meta.lead_distributions` — a push attempt to an
 * advertiser. Shape is loosely defined: most of it is opaque JSON that
 * either this app wrote (on resend) or a child CRM sent as-is during sync.
 */
export type LeadDistribution = {
    status?: string;
    sent_at?: string;
    advertisers?: { name?: string };
    request_payload?: unknown;
    request_url?: string;
    response?: unknown;
    [key: string]: unknown;
};

export function isLeadDistributionArray(
    value: unknown,
): value is LeadDistribution[] {
    return Array.isArray(value);
}

/**
 * The most recent send attempt for a lead, regardless of outcome.
 */
export function getLatestDistribution(
    distributions: LeadDistribution[],
): LeadDistribution | null {
    return distributions.length > 0
        ? distributions[distributions.length - 1]
        : null;
}
