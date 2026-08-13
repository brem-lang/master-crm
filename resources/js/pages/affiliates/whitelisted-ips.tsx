import { Head, router, usePage } from '@inertiajs/react';
import { CircleAlert, ShieldCheck } from 'lucide-react';
import Heading from '@/components/heading';
import { RefreshButton } from '@/components/refresh-button';
import { Badge } from '@/components/ui/badge';
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
import { whitelistedIps as whitelistedIpsIndex } from '@/routes/affiliates';
import type { Company } from '@/types';

type WhitelistedAffiliate = {
    affiliate_id: string;
    name: string;
    ip_whitelist_required: boolean;
    allowed_ips: string[] | null;
};

type PageProps = {
    affiliates: WhitelistedAffiliate[];
    error: string | null;
    companies?: Pick<Company, 'id' | 'name'>[];
    filters: {
        company_id: number | null;
    };
};

export default function AffiliateWhitelistedIps() {
    const { affiliates, error, companies, filters } =
        usePage<PageProps>().props;

    const applyFilters = (next: Partial<typeof filters>) => {
        router.get(
            whitelistedIpsIndex().url,
            { ...filters, ...next },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Affiliate IP Whitelist" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        className="mb-0"
                        title="Affiliate IP Whitelist"
                        description="Affiliates with IP-whitelist enforcement turned on, fetched live from the child CRM"
                    />
                    <RefreshButton />
                </div>

                {companies && (
                    <Select
                        value={
                            filters.company_id
                                ? String(filters.company_id)
                                : ''
                        }
                        onValueChange={(value) =>
                            applyFilters({ company_id: Number(value) })
                        }
                    >
                        <SelectTrigger className="w-full sm:w-64">
                            <SelectValue placeholder="Select a company…" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
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

                {error && (
                    <div className="flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
                        <CircleAlert className="mt-0.5 size-4 shrink-0" />
                        <p>{error}</p>
                    </div>
                )}

                {!error && companies && !filters.company_id ? (
                    <div className="rounded-md border p-8 text-center text-sm text-muted-foreground">
                        Select a company to fetch its IP whitelist live from
                        its CRM.
                    </div>
                ) : (
                    <div className="rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Affiliate</TableHead>
                                    <TableHead>Enforced</TableHead>
                                    <TableHead>Allowed IPs</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {affiliates.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={3}
                                            className="py-8 text-center text-sm text-muted-foreground"
                                        >
                                            No affiliates with IP-whitelist
                                            enforcement on for this company.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    affiliates.map((affiliate) => (
                                        <TableRow key={affiliate.affiliate_id}>
                                            <TableCell className="font-medium">
                                                {affiliate.name}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant="outline"
                                                    className="gap-1 border-transparent bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400"
                                                >
                                                    <ShieldCheck className="size-3" />
                                                    Enforced
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="font-mono text-xs">
                                                {affiliate.allowed_ips
                                                    ?.length
                                                    ? affiliate.allowed_ips.join(
                                                          ', ',
                                                      )
                                                    : '—'}
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>
        </>
    );
}

AffiliateWhitelistedIps.layout = {
    breadcrumbs: [
        {
            title: 'Affiliate IP Whitelist',
            href: whitelistedIpsIndex(),
        },
    ],
};
