import { Form, Head, router, usePage } from '@inertiajs/react';
import {
    AtSign,
    CircleAlert,
    CircleCheck,
    Eye,
    Globe2,
    KeyRound,
    Loader2,
    MoreHorizontal,
    RefreshCw,
    Search,
    Send,
    Sparkles,
    Tag,
    Trash2,
    UserRound,
    Wand2,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import AdvertiserController from '@/actions/App/Http/Controllers/AdvertiserController';
import { BulkDeleteBar } from '@/components/bulk-delete-bar';
import { DataPagination } from '@/components/data-pagination';
import Heading from '@/components/heading';
import { RefreshButton } from '@/components/refresh-button';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { useDebouncedCallback } from '@/hooks/use-debounced-callback';
import { useRowSelection } from '@/hooks/use-row-selection';
import { generateTestData, getCountryList } from '@/lib/countryData';
import { index as advertisersIndex, sendTestLead } from '@/routes/advertisers';
import type { Advertiser, Auth, Company, Paginator } from '@/types';

function xsrfToken(): string {
    return decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );
}

function jsonHeaders(): HeadersInit {
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': xsrfToken(),
    };
}

type SendTestLeadResult = {
    success: boolean;
    message?: string;
    test_mode?: boolean;
    advertiser_name?: string;
    advertiser_response?: unknown;
    lead_id?: string;
};

function statusBadgeClass(isActive: boolean): string {
    return isActive
        ? 'border-transparent bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
        : 'border-transparent bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
}

function metaLabel(key: string): string {
    return key
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function isPlainObject(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
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

/**
 * Flattens one level of nested objects (e.g. a `config` blob holding its own
 * named fields like `crm_type`/`api_url`/`affiliate_token`) so each surfaces
 * as its own labeled row instead of a single raw JSON blob.
 */
function flattenMetaEntries(
    meta: Record<string, unknown>,
): [string, unknown][] {
    return Object.entries(meta).flatMap(([key, value]) => {
        if (isPlainObject(value)) {
            const nested = Object.entries(value);

            return nested.length > 0
                ? nested.map(
                      ([nestedKey, nestedValue]) =>
                          [
                              `${metaLabel(key)}: ${metaLabel(nestedKey)}`,
                              nestedValue,
                          ] as [string, unknown],
                  )
                : [];
        }

        return [[key, value] as [string, unknown]];
    });
}

function AdvertiserDetailsDialog({
    advertiser,
    open,
    onOpenChange,
}: {
    advertiser: Advertiser | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    if (!advertiser) {
        return null;
    }

    const meta = advertiser.meta ?? {};
    const metaEntries = flattenMetaEntries(meta).filter(
        ([, value]) => value !== null && value !== '' && value !== undefined,
    );

    const fields: [string, string][] = [
        ['Type', metaValue(advertiser.advertiser_type)],
        ['URL', metaValue(advertiser.url)],
        ['Daily Cap', metaValue(advertiser.daily_cap)],
        ['Hourly Cap', metaValue(advertiser.hourly_cap)],
        ['Default Deal Type', metaValue(advertiser.default_deal_type)],
        [
            'Synced',
            advertiser.synced_at
                ? new Date(advertiser.synced_at).toLocaleString()
                : '—',
        ],
    ];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{advertiser.name}</DialogTitle>
                    <DialogDescription>
                        {advertiser.company && (
                            <span className="mr-2">
                                {advertiser.company.name}
                            </span>
                        )}
                        <Badge
                            variant="outline"
                            className={statusBadgeClass(advertiser.is_active)}
                        >
                            {advertiser.is_active ? 'Active' : 'Inactive'}
                        </Badge>
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

type TestLeadFormData = {
    firstname: string;
    lastname: string;
    email: string;
    mobile: string;
    country_code: string;
    country: string;
    ip_address: string;
    offer_name: string;
    custom1: string;
    custom2: string;
    custom3: string;
    locale: string;
    password: string;
    currency: string;
};

function blankTestData(): TestLeadFormData {
    return {
        firstname: '',
        lastname: '',
        email: '',
        mobile: '',
        country_code: '',
        country: '',
        ip_address: '',
        offer_name: '',
        custom1: '',
        custom2: '',
        custom3: '',
        locale: typeof navigator !== 'undefined' ? navigator.language : '',
        password: '',
        currency: '',
    };
}

function isValidMobile(value: string): boolean {
    return /^\+?\d{7,15}$/.test(value);
}

function isValidIpv4(value: string): boolean {
    if (!/^(\d{1,3}\.){3}\d{1,3}$/.test(value)) {
        return false;
    }

    return value.split('.').every((octet) => Number(octet) <= 255);
}

function FormSection({
    icon: Icon,
    title,
    children,
}: {
    icon: typeof UserRound;
    title: string;
    children: ReactNode;
}) {
    return (
        <div className="rounded-lg border bg-card/50 p-4">
            <div className="mb-3 flex items-center gap-2">
                <Icon className="size-4 text-muted-foreground" />
                <h4 className="text-sm font-medium">{title}</h4>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">{children}</div>
        </div>
    );
}

function SendTestLeadDialog({
    advertiser,
    open,
    onOpenChange,
}: {
    advertiser: Advertiser | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [mode, setMode] = useState<'auto' | 'manual'>('auto');
    const [countryCode, setCountryCode] = useState('');
    const [data, setData] = useState<TestLeadFormData>(blankTestData());
    const [submitting, setSubmitting] = useState(false);
    const [result, setResult] = useState<SendTestLeadResult | null>(null);

    if (!advertiser) {
        return null;
    }

    const regenerate = (code: string) => {
        setResult(null);
        setCountryCode(code);

        if (!code) {
            setData(blankTestData());

            return;
        }

        const generated = generateTestData(code.toUpperCase());
        setData((previous) => ({ ...previous, ...generated }));
    };

    const updateField = (field: keyof TestLeadFormData, value: string) => {
        setResult(null);
        setData((previous) => ({ ...previous, [field]: value }));
    };

    const switchMode = (nextMode: 'auto' | 'manual') => {
        setResult(null);
        setMode(nextMode);
    };

    const showNotionFields = advertiser.advertiser_type === 'notion';
    const mobileError =
        mode === 'manual' && data.mobile !== '' && !isValidMobile(data.mobile);
    const ipError =
        mode === 'manual' &&
        data.ip_address !== '' &&
        !isValidIpv4(data.ip_address);

    const requiredFieldsFilled =
        data.email !== '' &&
        data.mobile !== '' &&
        data.country_code !== '' &&
        data.ip_address !== '';
    const canSend =
        requiredFieldsFilled &&
        !mobileError &&
        !ipError &&
        !submitting &&
        !result?.success;

    const submit = async () => {
        setSubmitting(true);
        setResult(null);

        try {
            const response = await fetch(sendTestLead(advertiser.id).url, {
                method: 'POST',
                headers: jsonHeaders(),
                body: JSON.stringify(data),
            });
            const body = (await response.json()) as SendTestLeadResult;
            setResult(body);
        } catch {
            setResult({
                success: false,
                message: 'Could not reach the server. Try again.',
            });
        } finally {
            setSubmitting(false);
        }
    };

    const autoFieldClass = (hasError: boolean) =>
        mode === 'auto'
            ? 'bg-muted/40 text-muted-foreground'
            : hasError
              ? 'border-destructive'
              : '';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Send className="size-4 text-muted-foreground" />
                        Send test lead to {advertiser.name}
                    </DialogTitle>
                    <DialogDescription>
                        <Badge
                            variant="outline"
                            className={statusBadgeClass(advertiser.is_active)}
                        >
                            {advertiser.is_active ? 'Active' : 'Inactive'}
                        </Badge>
                    </DialogDescription>
                </DialogHeader>

                <div className="max-h-[70vh] space-y-5 overflow-y-auto pr-1">
                    <div className="inline-flex gap-1 rounded-lg bg-muted p-1">
                        <button
                            type="button"
                            onClick={() => switchMode('auto')}
                            className={`flex items-center gap-1.5 rounded-md px-3.5 py-1.5 text-sm transition-colors ${
                                mode === 'auto'
                                    ? 'bg-background font-medium shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            <Wand2 className="size-3.5" />
                            Auto Generate
                        </button>
                        <button
                            type="button"
                            onClick={() => switchMode('manual')}
                            className={`rounded-md px-3.5 py-1.5 text-sm transition-colors ${
                                mode === 'manual'
                                    ? 'bg-background font-medium shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            Manual Input
                        </button>
                    </div>

                    <FormSection icon={Globe2} title="Location">
                        {mode === 'auto' ? (
                            <div className="col-span-2 flex items-end gap-2">
                                <div className="grid flex-1 gap-2">
                                    <Label htmlFor="test-lead-country">
                                        Country
                                    </Label>
                                    <Select
                                        value={countryCode}
                                        onValueChange={regenerate}
                                    >
                                        <SelectTrigger id="test-lead-country">
                                            <SelectValue placeholder="Select a country…" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                {getCountryList().map(
                                                    (country) => (
                                                        <SelectItem
                                                            key={country.code}
                                                            value={country.code}
                                                        >
                                                            {country.name} (
                                                            {country.code})
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={!countryCode}
                                    onClick={() => regenerate(countryCode)}
                                >
                                    <RefreshCw />
                                    Regenerate
                                </Button>
                            </div>
                        ) : (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="test-lead-country-code">
                                        Country code
                                    </Label>
                                    <Input
                                        id="test-lead-country-code"
                                        value={data.country_code}
                                        placeholder="US"
                                        onChange={(event) =>
                                            updateField(
                                                'country_code',
                                                event.target.value.toUpperCase(),
                                            )
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="test-lead-country-name">
                                        Country
                                    </Label>
                                    <Input
                                        id="test-lead-country-name"
                                        value={data.country}
                                        placeholder="United States"
                                        onChange={(event) =>
                                            updateField(
                                                'country',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </>
                        )}
                        <div className="grid gap-2">
                            <Label htmlFor="test-lead-ip">IP address</Label>
                            <Input
                                id="test-lead-ip"
                                value={data.ip_address}
                                readOnly={mode === 'auto'}
                                className={autoFieldClass(ipError)}
                                onChange={(event) =>
                                    updateField(
                                        'ip_address',
                                        event.target.value,
                                    )
                                }
                            />
                            {ipError && (
                                <p className="text-xs text-destructive">
                                    Enter a valid IPv4 address.
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="test-lead-locale">Locale</Label>
                            <Input
                                id="test-lead-locale"
                                value={data.locale}
                                readOnly
                                className="bg-muted/40 text-muted-foreground"
                            />
                        </div>
                    </FormSection>

                    <FormSection icon={UserRound} title="Contact details">
                        <div className="grid gap-2">
                            <Label htmlFor="test-lead-firstname">
                                First name
                            </Label>
                            <Input
                                id="test-lead-firstname"
                                value={data.firstname}
                                readOnly={mode === 'auto'}
                                className={autoFieldClass(false)}
                                onChange={(event) =>
                                    updateField('firstname', event.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="test-lead-lastname">
                                Last name
                            </Label>
                            <Input
                                id="test-lead-lastname"
                                value={data.lastname}
                                readOnly={mode === 'auto'}
                                className={autoFieldClass(false)}
                                onChange={(event) =>
                                    updateField('lastname', event.target.value)
                                }
                            />
                        </div>
                        <div className="col-span-2 grid gap-2">
                            <Label
                                htmlFor="test-lead-email"
                                className="flex items-center gap-1.5"
                            >
                                <AtSign className="size-3.5" />
                                Email
                            </Label>
                            <Input
                                id="test-lead-email"
                                value={data.email}
                                readOnly={mode === 'auto'}
                                className={autoFieldClass(false)}
                                onChange={(event) =>
                                    updateField('email', event.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="test-lead-mobile">Mobile</Label>
                            <Input
                                id="test-lead-mobile"
                                value={data.mobile}
                                readOnly={mode === 'auto'}
                                className={autoFieldClass(mobileError)}
                                onChange={(event) =>
                                    updateField('mobile', event.target.value)
                                }
                            />
                            {mobileError && (
                                <p className="text-xs text-destructive">
                                    Enter 7–15 digits.
                                </p>
                            )}
                        </div>
                    </FormSection>

                    <FormSection icon={Tag} title="Tracking">
                        <div className="grid gap-2">
                            <Label htmlFor="test-lead-offer">Offer name</Label>
                            <Input
                                id="test-lead-offer"
                                value={data.offer_name}
                                placeholder="Test Lead"
                                onChange={(event) =>
                                    updateField(
                                        'offer_name',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="test-lead-custom1">Custom 1</Label>
                            <Input
                                id="test-lead-custom1"
                                value={data.custom1}
                                onChange={(event) =>
                                    updateField('custom1', event.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="test-lead-custom2">Custom 2</Label>
                            <Input
                                id="test-lead-custom2"
                                value={data.custom2}
                                onChange={(event) =>
                                    updateField('custom2', event.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="test-lead-custom3">Custom 3</Label>
                            <Input
                                id="test-lead-custom3"
                                value={data.custom3}
                                onChange={(event) =>
                                    updateField('custom3', event.target.value)
                                }
                            />
                        </div>
                    </FormSection>

                    {showNotionFields && (
                        <FormSection icon={KeyRound} title="Notion credentials">
                            <div className="grid gap-2">
                                <Label htmlFor="test-lead-password">
                                    Password
                                </Label>
                                <Input
                                    id="test-lead-password"
                                    value={data.password}
                                    onChange={(event) =>
                                        updateField(
                                            'password',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="test-lead-currency">
                                    Currency
                                </Label>
                                <Input
                                    id="test-lead-currency"
                                    value={data.currency}
                                    onChange={(event) =>
                                        updateField(
                                            'currency',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                        </FormSection>
                    )}

                    {mode === 'auto' && (
                        <div className="flex items-start gap-2 rounded-lg border border-dashed p-3 text-sm text-muted-foreground">
                            <Sparkles className="mt-0.5 size-4 shrink-0" />
                            <p>
                                Fields shaded above are auto-generated for the
                                selected country.
                            </p>
                        </div>
                    )}

                    {result && (
                        <div
                            className={`flex items-start gap-2 rounded-lg border p-3 text-sm ${
                                result.success
                                    ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-900/50 dark:bg-green-900/20 dark:text-green-400'
                                    : 'border-destructive/30 bg-destructive/10 text-destructive'
                            }`}
                        >
                            {result.success ? (
                                <CircleCheck className="mt-0.5 size-4 shrink-0" />
                            ) : (
                                <CircleAlert className="mt-0.5 size-4 shrink-0" />
                            )}
                            <div className="space-y-1">
                                <p className="font-medium">
                                    {result.message ??
                                        (result.success
                                            ? 'Test lead sent.'
                                            : 'Something went wrong.')}
                                </p>
                                {result.success && (
                                    <p className="text-xs opacity-80">
                                        {result.advertiser_name && (
                                            <>
                                                Advertiser:{' '}
                                                {result.advertiser_name}
                                                {'  ·  '}
                                            </>
                                        )}
                                        {typeof result.test_mode ===
                                            'boolean' && (
                                            <>
                                                Test mode:{' '}
                                                {result.test_mode
                                                    ? 'on'
                                                    : 'off'}
                                                {'  ·  '}
                                            </>
                                        )}
                                        {result.lead_id && (
                                            <>Lead ID: {result.lead_id}</>
                                        )}
                                    </p>
                                )}
                            </div>
                        </div>
                    )}
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Close
                    </Button>
                    <Button disabled={!canSend} onClick={submit}>
                        {submitting ? (
                            <Loader2 className="animate-spin" />
                        ) : (
                            <Send />
                        )}
                        Send Test Lead
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

type PageProps = {
    auth: Auth;
    stats: { total: number; active: number; inactive: number };
    advertisers: Paginator<Advertiser>;
    companies?: Pick<Company, 'id' | 'name'>[];
    filters: {
        search: string;
        status: string | null;
        company_id: number | null;
    };
};

export default function AdvertisersIndex() {
    const { auth, stats, advertisers, companies, filters } =
        usePage<PageProps>().props;
    const canSendTestLeads = auth.permissions?.includes('send-test-leads');
    const canDeleteAdvertisers = auth.permissions?.includes(
        'delete-advertisers',
    );
    const [search, setSearch] = useState(filters.search);
    const [viewingAdvertiser, setViewingAdvertiser] =
        useState<Advertiser | null>(null);
    const [sendingTestLeadTo, setSendingTestLeadTo] =
        useState<Advertiser | null>(null);
    const selection = useRowSelection(
        advertisers.data,
        advertisers.current_page,
    );

    const applyFilters = (next: Partial<typeof filters>) => {
        router.get(
            advertisersIndex().url,
            {
                ...filters,
                ...next,
                per_page: advertisers.per_page,
                page: 1,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const debouncedSearch = useDebouncedCallback((value: string) => {
        applyFilters({ search: value });
    }, 300);

    return (
        <>
            <Head title="Advertisers" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Advertisers"
                        description="Advertisers synced from your company's CRM"
                    />
                    <RefreshButton />
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard label="Total advertisers" value={stats.total} />
                    <StatCard label="Active" value={stats.active} />
                    <StatCard label="Inactive" value={stats.inactive} />
                </div>

                <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                    <div className="relative w-full sm:max-w-xs">
                        <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(event) => {
                                setSearch(event.target.value);
                                debouncedSearch(event.target.value);
                            }}
                            placeholder="Search by name…"
                            className="pl-8"
                        />
                    </div>

                    <Select
                        value={filters.status || 'all'}
                        onValueChange={(value) =>
                            applyFilters({
                                status: value === 'all' ? null : value,
                            })
                        }
                    >
                        <SelectTrigger className="w-full sm:w-40">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="all">
                                    All statuses
                                </SelectItem>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="inactive">
                                    Inactive
                                </SelectItem>
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
                            <SelectTrigger className="w-full sm:w-48">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="all">
                                        All companies
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
                </div>

                {canDeleteAdvertisers && (
                    <BulkDeleteBar
                        count={selection.selectedIds.length}
                        description="This will permanently delete the selected advertisers. This action cannot be undone."
                        onConfirm={(onFinish) => {
                            router.delete(
                                AdvertiserController.bulkDestroy().url,
                                {
                                    data: { ids: selection.selectedIds },
                                    preserveScroll: true,
                                    onSuccess: () =>
                                        selection.setSelectedIds([]),
                                    onFinish,
                                },
                            );
                        }}
                    />
                )}

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                {canDeleteAdvertisers && (
                                    <TableHead className="w-px">
                                        <Checkbox
                                            checked={
                                                selection.allSelected
                                                    ? true
                                                    : selection.someSelected
                                                      ? 'indeterminate'
                                                      : false
                                            }
                                            onCheckedChange={(checked) =>
                                                selection.toggleAll(
                                                    checked === true,
                                                )
                                            }
                                            aria-label="Select all"
                                        />
                                    </TableHead>
                                )}
                                <TableHead>Name</TableHead>
                                {companies && (
                                    <TableHead className="hidden md:table-cell">
                                        Company
                                    </TableHead>
                                )}
                                <TableHead className="hidden lg:table-cell">
                                    Type
                                </TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="hidden lg:table-cell">
                                    Daily Cap
                                </TableHead>
                                <TableHead className="w-px" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {advertisers.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={
                                            canDeleteAdvertisers ? 7 : 6
                                        }
                                        className="py-8 text-center text-sm text-muted-foreground"
                                    >
                                        No advertisers synced yet.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                advertisers.data.map((advertiser) => (
                                    <TableRow
                                        key={advertiser.id}
                                        data-state={
                                            selection.isSelected(advertiser.id)
                                                ? 'selected'
                                                : undefined
                                        }
                                    >
                                        {canDeleteAdvertisers && (
                                            <TableCell>
                                                <Checkbox
                                                    checked={selection.isSelected(
                                                        advertiser.id,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        selection.toggleOne(
                                                            advertiser.id,
                                                            checked === true,
                                                        )
                                                    }
                                                    aria-label={`Select ${advertiser.name}`}
                                                />
                                            </TableCell>
                                        )}
                                        <TableCell className="font-medium">
                                            {advertiser.name}
                                        </TableCell>
                                        {companies && (
                                            <TableCell className="hidden md:table-cell">
                                                {advertiser.company?.name ??
                                                    '—'}
                                            </TableCell>
                                        )}
                                        <TableCell className="hidden lg:table-cell">
                                            {advertiser.advertiser_type ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={statusBadgeClass(
                                                    advertiser.is_active,
                                                )}
                                            >
                                                {advertiser.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="hidden lg:table-cell">
                                            {advertiser.daily_cap !== null ? (
                                                <Badge variant="secondary">
                                                    {advertiser.daily_cap}
                                                </Badge>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Actions for ${advertiser.name}`}
                                                    >
                                                        <MoreHorizontal className="size-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem
                                                        onSelect={() =>
                                                            setViewingAdvertiser(
                                                                advertiser,
                                                            )
                                                        }
                                                    >
                                                        <Eye />
                                                        View
                                                    </DropdownMenuItem>
                                                    {canSendTestLeads && (
                                                        <DropdownMenuItem
                                                            onSelect={() =>
                                                                setSendingTestLeadTo(
                                                                    advertiser,
                                                                )
                                                            }
                                                        >
                                                            <Send />
                                                            Send Test Leads
                                                        </DropdownMenuItem>
                                                    )}
                                                    {canDeleteAdvertisers && (
                                                        <Dialog>
                                                            <DialogTrigger
                                                                asChild
                                                            >
                                                                <DropdownMenuItem
                                                                    variant="destructive"
                                                                    onSelect={(
                                                                        event,
                                                                    ) =>
                                                                        event.preventDefault()
                                                                    }
                                                                >
                                                                    <Trash2 />
                                                                    Delete
                                                                </DropdownMenuItem>
                                                            </DialogTrigger>

                                                            <DialogContent>
                                                                <DialogTitle>
                                                                    Delete{' '}
                                                                    {
                                                                        advertiser.name
                                                                    }
                                                                    ?
                                                                </DialogTitle>
                                                                <DialogDescription>
                                                                    This will
                                                                    permanently
                                                                    delete this
                                                                    advertiser.
                                                                    This action
                                                                    cannot be
                                                                    undone.
                                                                </DialogDescription>

                                                                <DialogFooter className="gap-2">
                                                                    <DialogClose
                                                                        asChild
                                                                    >
                                                                        <Button variant="secondary">
                                                                            Cancel
                                                                        </Button>
                                                                    </DialogClose>

                                                                    <Form
                                                                        {...AdvertiserController.destroy.form(
                                                                            advertiser.id,
                                                                        )}
                                                                    >
                                                                        {({
                                                                            processing,
                                                                        }) => (
                                                                            <Button
                                                                                variant="destructive"
                                                                                disabled={
                                                                                    processing
                                                                                }
                                                                                type="submit"
                                                                            >
                                                                                <Trash2 />
                                                                                Delete
                                                                            </Button>
                                                                        )}
                                                                    </Form>
                                                                </DialogFooter>
                                                            </DialogContent>
                                                        </Dialog>
                                                    )}
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                <DataPagination paginator={advertisers} filters={filters} />

                <AdvertiserDetailsDialog
                    advertiser={viewingAdvertiser}
                    open={!!viewingAdvertiser}
                    onOpenChange={(open) => !open && setViewingAdvertiser(null)}
                />

                <SendTestLeadDialog
                    key={sendingTestLeadTo?.id ?? 'none'}
                    advertiser={sendingTestLeadTo}
                    open={!!sendingTestLeadTo}
                    onOpenChange={(open) => !open && setSendingTestLeadTo(null)}
                />
            </div>
        </>
    );
}

AdvertisersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Advertisers',
            href: advertisersIndex(),
        },
    ],
};
