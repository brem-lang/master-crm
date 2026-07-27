import { Head, router, usePage } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    Clock,
    TrendingUp,
    Users,
} from 'lucide-react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Line,
    LineChart,
    Pie,
    PieChart,
    XAxis,
    YAxis,
} from 'recharts';
import { DateRangeFilter } from '@/components/dashboard/date-range-filter';
import { MetricCard } from '@/components/dashboard/metric-card';
import Heading from '@/components/heading';
import { RefreshButton } from '@/components/refresh-button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';
import type { DashboardAnalytics, DashboardFilters, NamedTally } from '@/types';

const leadsChartConfig: ChartConfig = {
    leads: {
        label: 'Leads',
        theme: { light: '#2a78d6', dark: '#3987e5' },
    },
    ftd: {
        label: 'FTD',
        theme: { light: '#008300', dark: '#008300' },
    },
};

const ADVERTISER_COLORS = [
    { light: '#2a78d6', dark: '#3987e5' },
    { light: '#008300', dark: '#008300' },
    { light: '#e87ba4', dark: '#d55181' },
    { light: '#eda100', dark: '#c98500' },
    { light: '#1baf7a', dark: '#199e70' },
];

function advertiserChartConfig(advertisers: NamedTally[]): ChartConfig {
    return advertisers.reduce<ChartConfig>((config, advertiser, index) => {
        config[advertiser.name] = {
            label: advertiser.name,
            theme: ADVERTISER_COLORS[index % ADVERTISER_COLORS.length],
        };

        return config;
    }, {});
}

function TopTable({
    title,
    rows,
    countLabel,
    countKey,
}: {
    title: string;
    rows: NamedTally[];
    countLabel: string;
    countKey: 'leads' | 'sent';
}) {
    return (
        <Card className="gap-3 py-4">
            <CardHeader className="px-4">
                <CardTitle className="text-sm font-medium">{title}</CardTitle>
            </CardHeader>
            <CardContent className="px-4">
                {rows.length === 0 ? (
                    <p className="py-4 text-center text-sm text-muted-foreground">
                        No data yet.
                    </p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>
                                    {title.replace('Top 5 ', '')}
                                </TableHead>
                                <TableHead className="text-right">
                                    {countLabel}
                                </TableHead>
                                <TableHead className="text-right">
                                    FTD
                                </TableHead>
                                <TableHead className="text-right">CR</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.map((row) => (
                                <TableRow key={row.name}>
                                    <TableCell className="font-medium">
                                        {row.name}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {row[countKey]}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {row.ftd}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {row.cr.toFixed(1)}%
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </CardContent>
        </Card>
    );
}

function AnalyticsDashboard({
    analytics,
    companies,
}: {
    analytics: DashboardAnalytics;
    companies?: { id: number; name: string }[] | null;
}) {
    const {
        stats,
        series,
        topCountries,
        topAffiliates,
        topAdvertisers,
        filters,
        affiliateOptions,
        advertiserOptions,
    } = analytics;

    const applyFilters = (next: Partial<DashboardFilters>) => {
        router.get(
            dashboard().url,
            { ...filters, ...next },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const advertiserConfig = advertiserChartConfig(topAdvertisers);

    return (
        <div className="space-y-4 p-4">
            <div className="flex items-center justify-between">
                <Heading
                    title="Dashboard"
                    description="Performance overview and analytics"
                />
                <RefreshButton />
            </div>

            <Card className="gap-0 py-3">
                <CardContent className="flex flex-wrap items-center gap-2 px-4">
                    <DateRangeFilter
                        filters={filters}
                        onChange={applyFilters}
                    />

                    <Select
                        value={filters.advertiser ?? 'all'}
                        onValueChange={(value) =>
                            applyFilters({
                                advertiser: value === 'all' ? null : value,
                            })
                        }
                    >
                        <SelectTrigger className="w-44">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="all">
                                    All Advertisers
                                </SelectItem>
                                {advertiserOptions.map((advertiser) => (
                                    <SelectItem
                                        key={advertiser}
                                        value={advertiser}
                                    >
                                        {advertiser}
                                    </SelectItem>
                                ))}
                            </SelectGroup>
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.affiliate ?? 'all'}
                        onValueChange={(value) =>
                            applyFilters({
                                affiliate: value === 'all' ? null : value,
                            })
                        }
                    >
                        <SelectTrigger className="w-44">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="all">
                                    All Affiliates
                                </SelectItem>
                                {affiliateOptions.map((affiliate) => (
                                    <SelectItem
                                        key={affiliate}
                                        value={affiliate}
                                    >
                                        {affiliate}
                                    </SelectItem>
                                ))}
                            </SelectGroup>
                        </SelectContent>
                    </Select>

                    {companies && (
                        <Select
                            value={
                                filters.company_id
                                    ? String(filters.company_id)
                                    : 'all'
                            }
                            onValueChange={(value) =>
                                applyFilters({
                                    company_id:
                                        value === 'all' ? null : Number(value),
                                })
                            }
                        >
                            <SelectTrigger className="w-44">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="all">
                                        All Companies
                                    </SelectItem>
                                    {companies.map((company) => (
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
                    )}
                </CardContent>
            </Card>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <MetricCard
                    icon={TrendingUp}
                    label="Leads Sent"
                    value={stats.sent}
                    sublabel="Successfully"
                    secondaryValue={stats.failed}
                    secondaryLabel="Failed"
                    secondaryClassName={
                        stats.failed > 0
                            ? 'text-red-600 dark:text-red-400'
                            : undefined
                    }
                />
                <MetricCard
                    icon={Activity}
                    label="Conversions"
                    value={stats.ftd}
                    sublabel="FTD"
                    secondaryValue={`${stats.conversionRate.toFixed(2)}%`}
                    secondaryLabel="CR"
                    secondaryClassName="text-green-600 dark:text-green-400"
                />
                <MetricCard
                    icon={Clock}
                    label="Pending FTDs"
                    value={stats.pendingFtd}
                    sublabel="Awaiting release"
                />
                <MetricCard
                    icon={AlertTriangle}
                    label="Rejection Rate"
                    value={`${stats.rejectionRate.toFixed(2)}%`}
                    sublabel="Of distributions"
                />
                <MetricCard
                    icon={Users}
                    label="Total Leads"
                    value={stats.total}
                    sublabel="Distributed"
                />
            </div>

            <Card className="py-4">
                <CardHeader className="px-4">
                    <CardTitle>Leads and Conversions</CardTitle>
                </CardHeader>
                <CardContent className="px-4">
                    <ChartContainer
                        config={leadsChartConfig}
                        className="aspect-auto h-75 w-full"
                    >
                        <LineChart data={series}>
                            <CartesianGrid vertical={false} />
                            <XAxis
                                dataKey="label"
                                tickLine={false}
                                axisLine={false}
                            />
                            <YAxis
                                tickLine={false}
                                axisLine={false}
                                allowDecimals={false}
                            />
                            <ChartTooltip content={<ChartTooltipContent />} />
                            <ChartLegend content={<ChartLegendContent />} />
                            <Line
                                type="monotone"
                                dataKey="leads"
                                stroke="var(--color-leads)"
                                strokeWidth={2}
                                dot={false}
                            />
                            <Line
                                type="monotone"
                                dataKey="ftd"
                                stroke="var(--color-ftd)"
                                strokeWidth={2}
                                dot={false}
                            />
                        </LineChart>
                    </ChartContainer>
                </CardContent>
            </Card>

            <div className="grid gap-4 lg:grid-cols-3">
                <TopTable
                    title="Top 5 Countries"
                    rows={topCountries}
                    countLabel="Leads"
                    countKey="leads"
                />
                <TopTable
                    title="Top 5 Advertisers"
                    rows={topAdvertisers}
                    countLabel="Sent"
                    countKey="sent"
                />
                <TopTable
                    title="Top 5 Affiliates"
                    rows={topAffiliates}
                    countLabel="Leads"
                    countKey="leads"
                />
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <Card className="py-4">
                    <CardHeader className="px-4">
                        <CardTitle>Leads by Country</CardTitle>
                    </CardHeader>
                    <CardContent className="px-4">
                        {topCountries.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                No data yet.
                            </p>
                        ) : (
                            <ChartContainer
                                config={leadsChartConfig}
                                className="aspect-auto h-62.5 w-full"
                            >
                                <BarChart data={topCountries} layout="vertical">
                                    <CartesianGrid horizontal={false} />
                                    <XAxis
                                        type="number"
                                        tickLine={false}
                                        axisLine={false}
                                        allowDecimals={false}
                                    />
                                    <YAxis
                                        type="category"
                                        dataKey="name"
                                        tickLine={false}
                                        axisLine={false}
                                        width={40}
                                    />
                                    <ChartTooltip
                                        content={<ChartTooltipContent />}
                                    />
                                    <ChartLegend
                                        content={<ChartLegendContent />}
                                    />
                                    <Bar
                                        dataKey="leads"
                                        fill="var(--color-leads)"
                                        radius={4}
                                    />
                                    <Bar
                                        dataKey="ftd"
                                        fill="var(--color-ftd)"
                                        radius={4}
                                    />
                                </BarChart>
                            </ChartContainer>
                        )}
                    </CardContent>
                </Card>

                <Card className="py-4">
                    <CardHeader className="px-4">
                        <CardTitle>Performance by Advertiser</CardTitle>
                    </CardHeader>
                    <CardContent className="px-4">
                        {topAdvertisers.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                No data yet.
                            </p>
                        ) : (
                            <ChartContainer
                                config={advertiserConfig}
                                className="aspect-auto h-62.5 w-full"
                            >
                                <PieChart>
                                    <ChartTooltip
                                        content={<ChartTooltipContent />}
                                    />
                                    <ChartLegend
                                        content={<ChartLegendContent />}
                                    />
                                    <Pie
                                        data={topAdvertisers}
                                        dataKey="sent"
                                        nameKey="name"
                                        innerRadius={50}
                                        outerRadius={90}
                                        paddingAngle={2}
                                    >
                                        {topAdvertisers.map((advertiser) => (
                                            <Cell
                                                key={advertiser.name}
                                                fill={`var(--color-${advertiser.name})`}
                                            />
                                        ))}
                                    </Pie>
                                </PieChart>
                            </ChartContainer>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

type PageProps = {
    analytics: DashboardAnalytics | null;
    companies?: { id: number; name: string }[] | null;
};

export default function Dashboard() {
    const { analytics, companies } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Dashboard" />

            {analytics ? (
                <AnalyticsDashboard analytics={analytics} companies={companies} />
            ) : (
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                        <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                            <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                        </div>
                        <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                            <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                        </div>
                        <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                            <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                        </div>
                    </div>
                    <div className="relative min-h-screen flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                </div>
            )}
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
