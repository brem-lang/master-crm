export type DashboardStats = {
    total: number;
    rejected: number;
    rejectionRate: number;
    ftd: number;
    conversionRate: number;
    pendingFtd: number;
    sent: number;
    failed: number;
};

export type SeriesPoint = {
    label: string;
    leads: number;
    ftd: number;
};

export type NamedTally = {
    name: string;
    leads?: number;
    sent?: number;
    ftd: number;
    cr: number;
};

export type DashboardFilters = {
    range: string;
    from: string | null;
    to: string | null;
    affiliate: string | null;
    advertiser: string | null;
};

export type DashboardAnalytics = {
    stats: DashboardStats;
    series: SeriesPoint[];
    topCountries: NamedTally[];
    topAffiliates: NamedTally[];
    topAdvertisers: NamedTally[];
    filters: DashboardFilters;
    affiliateOptions: string[];
    advertiserOptions: string[];
};
