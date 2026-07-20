import { router, usePage } from '@inertiajs/react';
import { ExternalLink, Search } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { RefreshButton } from '@/components/refresh-button';
import { Badge } from '@/components/ui/badge';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { WebsiteStatusBadge } from '@/components/website-status-badge';
import { useDebouncedCallback } from '@/hooks/use-debounced-callback';
import { index as directoryIndex } from '@/routes/directory';
import type { Company } from '@/types';

type Filters = {
    search: string;
};

type PageProps = {
    companies: Company[];
    filters: Filters;
};

export default function CompanyDirectory() {
    const { companies, filters } = usePage<PageProps>().props;
    const [search, setSearch] = useState(filters.search);

    const debouncedSearch = useDebouncedCallback((value: string) => {
        router.get(
            directoryIndex().url,
            { search: value },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);

    return (
        <div className="space-y-6 p-4">
            <div className="flex items-center justify-between">
                <Heading
                    title="Company Directory"
                    description="Browse companies and visit their websites"
                />
                <RefreshButton />
            </div>

            <div className="relative w-full max-w-xs">
                <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    value={search}
                    onChange={(event) => {
                        setSearch(event.target.value);
                        debouncedSearch(event.target.value);
                    }}
                    placeholder="Search companies…"
                    className="pl-8"
                />
            </div>

            {companies.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No companies found.
                </p>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {companies.map((company) => {
                        const cardBody = (
                            <CardHeader className="flex-row items-center justify-between gap-2">
                                <div className="space-y-1.5">
                                    <CardTitle>{company.name}</CardTitle>
                                    {company.website ? (
                                        <WebsiteStatusBadge
                                            status={company.website_status}
                                        />
                                    ) : (
                                        <Badge variant="outline">
                                            No website
                                        </Badge>
                                    )}
                                </div>
                                {company.website && (
                                    <ExternalLink className="size-4 shrink-0 text-muted-foreground" />
                                )}
                            </CardHeader>
                        );

                        return company.website ? (
                            <a
                                key={company.id}
                                href={company.website}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <Card className="transition-colors hover:bg-accent/50">
                                    {cardBody}
                                </Card>
                            </a>
                        ) : (
                            <Card key={company.id} className="opacity-60">
                                {cardBody}
                            </Card>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

CompanyDirectory.layout = {
    breadcrumbs: [
        {
            title: 'Directory',
            href: directoryIndex(),
        },
    ],
};
