import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';
import { useDebouncedCallback } from '@/hooks/use-debounced-callback';
import { index as companiesIndex } from '@/routes/companies';
import { index as leadsIndex } from '@/routes/leads';
import { index as searchIndex } from '@/routes/search';
import { index as usersIndex } from '@/routes/users';
import type { GlobalSearchResults } from '@/types';

const EMPTY_RESULTS: GlobalSearchResults = {
    companies: [],
    users: [],
    leads: [],
};

export function GlobalSearch() {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<GlobalSearchResults | null>(null);
    const [open, setOpen] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);

    const search = useDebouncedCallback(async (value: string) => {
        if (value.trim().length < 2) {
            setResults(null);

            return;
        }

        const response = await fetch(searchIndex({ query: { q: value } }).url, {
            headers: { Accept: 'application/json' },
        });

        setResults(await response.json());
    }, 250);

    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (!containerRef.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', handleClickOutside);

        return () =>
            document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const close = () => {
        setOpen(false);
        setQuery('');
        setResults(null);
    };

    const { companies, users, leads } = results ?? EMPTY_RESULTS;
    const hasResults =
        companies.length > 0 || users.length > 0 || leads.length > 0;

    return (
        <div ref={containerRef} className="relative w-full max-w-xs">
            <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
                value={query}
                onChange={(event) => {
                    setQuery(event.target.value);
                    setOpen(true);
                    search(event.target.value);
                }}
                onFocus={() => setOpen(true)}
                onKeyDown={(event) => event.key === 'Escape' && close()}
                placeholder="Search companies, users, leads…"
                className="pl-8"
            />

            {open && query.trim().length >= 2 && (
                <div className="absolute top-full right-0 z-50 mt-1 w-80 rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                    {results === null ? (
                        <p className="px-2 py-4 text-center text-sm text-muted-foreground">
                            Searching…
                        </p>
                    ) : !hasResults ? (
                        <p className="px-2 py-4 text-center text-sm text-muted-foreground">
                            No matches found.
                        </p>
                    ) : (
                        <>
                            {companies.length > 0 && (
                                <div className="py-1">
                                    <p className="px-2 py-1 text-xs font-medium text-muted-foreground">
                                        Companies
                                    </p>
                                    {companies.map((company) => (
                                        <button
                                            key={`company-${company.id}`}
                                            type="button"
                                            className="w-full rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent"
                                            onClick={() => {
                                                close();
                                                router.get(
                                                    companiesIndex().url,
                                                    {
                                                        view_company:
                                                            company.id,
                                                    },
                                                );
                                            }}
                                        >
                                            <div className="font-medium">
                                                {company.name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {company.slug}
                                            </div>
                                        </button>
                                    ))}
                                </div>
                            )}

                            {users.length > 0 && (
                                <div className="py-1">
                                    <p className="px-2 py-1 text-xs font-medium text-muted-foreground">
                                        Users
                                    </p>
                                    {users.map((user) => (
                                        <button
                                            key={`user-${user.id}`}
                                            type="button"
                                            className="w-full rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent"
                                            onClick={() => {
                                                close();
                                                router.get(usersIndex().url, {
                                                    view_user: user.id,
                                                });
                                            }}
                                        >
                                            <div className="font-medium">
                                                {user.name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {user.email}
                                                {user.company &&
                                                    ` · ${user.company.name}`}
                                            </div>
                                        </button>
                                    ))}
                                </div>
                            )}

                            {leads.length > 0 && (
                                <div className="py-1">
                                    <p className="px-2 py-1 text-xs font-medium text-muted-foreground">
                                        Leads
                                    </p>
                                    {leads.map((lead) => (
                                        <button
                                            key={`lead-${lead.id}`}
                                            type="button"
                                            className="w-full rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent"
                                            onClick={() => {
                                                close();
                                                router.get(leadsIndex().url, {
                                                    view_lead: lead.id,
                                                    company_id: lead.company_id,
                                                });
                                            }}
                                        >
                                            <div className="font-medium">
                                                {[
                                                    lead.first_name,
                                                    lead.last_name,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' ') || '—'}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {lead.email}
                                                {lead.company &&
                                                    ` · ${lead.company.name}`}
                                            </div>
                                        </button>
                                    ))}
                                </div>
                            )}
                        </>
                    )}
                </div>
            )}
        </div>
    );
}
