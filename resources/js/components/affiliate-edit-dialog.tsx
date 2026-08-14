import { Form } from '@inertiajs/react';
import {
    ClipboardList,
    Globe,
    Plus,
    Save,
    Search,
    ShieldCheck,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import AffiliateController from '@/actions/App/Http/Controllers/AffiliateController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { MultiSelect } from '@/components/ui/multi-select';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { COUNTRIES } from '@/lib/countries';
import type { Affiliate } from '@/types';

function toList(value: unknown): string[] {
    return Array.isArray(value) ? value.map(String) : [];
}

/** Splits on commas, whitespace, or newlines — friendly to pasted lists either way. */
function parseIps(value: string): string[] {
    return value
        .split(/[\s,]+/)
        .map((entry) => entry.trim())
        .filter(Boolean);
}

function dedupe(values: string[]): string[] {
    return [...new Set(values)];
}

export function AffiliateEditDialog({
    affiliate,
    open,
    onOpenChange,
}: {
    affiliate: Affiliate | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [allowedCountries, setAllowedCountries] = useState(() =>
        toList(affiliate?.meta?.allowed_countries),
    );
    const [isActive, setIsActive] = useState(
        () => affiliate?.is_active ?? true,
    );
    const [testMode, setTestMode] = useState(() =>
        Boolean(affiliate?.meta?.test_mode),
    );
    const [ipWhitelistRequired, setIpWhitelistRequired] = useState(() =>
        Boolean(affiliate?.meta?.ip_whitelist_required),
    );
    const [allowedIps, setAllowedIps] = useState(() =>
        toList(affiliate?.meta?.allowed_ips),
    );
    const [ipSearch, setIpSearch] = useState('');
    const [newIp, setNewIp] = useState('');
    const [bulkText, setBulkText] = useState('');
    const [bulkOpen, setBulkOpen] = useState(false);

    const filteredIps = useMemo(() => {
        const term = ipSearch.trim().toLowerCase();

        return term
            ? allowedIps.filter((ip) => ip.toLowerCase().includes(term))
            : allowedIps;
    }, [allowedIps, ipSearch]);

    if (!affiliate) {
        return null;
    }

    const addIp = () => {
        const [ip] = parseIps(newIp);

        if (!ip) {
            return;
        }

        setAllowedIps((current) => dedupe([...current, ip]));
        setNewIp('');
    };

    const applyBulkAdd = () => {
        setAllowedIps((current) => dedupe([...current, ...parseIps(bulkText)]));
        setBulkText('');
        setBulkOpen(false);
    };

    const removeIp = (ip: string) => {
        setAllowedIps((current) => current.filter((entry) => entry !== ip));
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit affiliate</DialogTitle>
                    <DialogDescription>
                        Changes are pushed to {affiliate.company?.name ?? 'the'}
                        's CRM before being saved here.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...AffiliateController.update.form(affiliate.id)}
                    transform={(data) => ({
                        ...data,
                        allowed_countries: allowedCountries,
                        allowed_ips: allowedIps,
                        is_active: isActive,
                        test_mode: testMode,
                        ip_whitelist_required: ipWhitelistRequired,
                    })}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="space-y-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="affiliate-name">Name</Label>
                                    <Input
                                        id="affiliate-name"
                                        name="name"
                                        defaultValue={affiliate.name}
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label>Allowed Countries</Label>
                                    <MultiSelect
                                        placeholder="All Countries"
                                        leadingIcon={
                                            <Globe className="size-4 text-muted-foreground" />
                                        }
                                        selected={allowedCountries}
                                        onChange={setAllowedCountries}
                                        options={COUNTRIES.map((country) => ({
                                            value: country.code,
                                            label: country.name,
                                        }))}
                                        className="w-full"
                                        withSelectAll
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Which countries this affiliate can send
                                        leads from
                                    </p>
                                    <InputError
                                        message={errors.allowed_countries}
                                    />
                                </div>

                                <div className="flex items-center justify-between rounded-lg border p-3">
                                    <Label
                                        htmlFor="affiliate-is_active"
                                        className="font-medium"
                                    >
                                        Active
                                    </Label>
                                    <Switch
                                        id="affiliate-is_active"
                                        checked={isActive}
                                        onCheckedChange={setIsActive}
                                    />
                                </div>
                                <InputError message={errors.is_active} />

                                <div className="flex items-center justify-between rounded-lg border p-3">
                                    <div className="space-y-0.5">
                                        <Label
                                            htmlFor="affiliate-test_mode"
                                            className="font-medium"
                                        >
                                            Test Mode
                                        </Label>
                                        <p className="text-xs text-muted-foreground">
                                            Routes all leads to Mock Advertiser
                                            for integration testing
                                        </p>
                                    </div>
                                    <Switch
                                        id="affiliate-test_mode"
                                        checked={testMode}
                                        onCheckedChange={setTestMode}
                                    />
                                </div>
                                <InputError message={errors.test_mode} />

                                <div className="grid gap-2">
                                    <Label htmlFor="affiliate-callback_url">
                                        Callback URL
                                    </Label>
                                    <Input
                                        id="affiliate-callback_url"
                                        name="callback_url"
                                        type="url"
                                        placeholder="https://your-endpoint.com/callback"
                                        defaultValue={
                                            (affiliate.meta
                                                ?.callback_url as string) ?? ''
                                        }
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Autologin URLs will be sent here after
                                        successful distribution
                                    </p>
                                    <InputError message={errors.callback_url} />
                                </div>

                                <div className="space-y-3 rounded-lg border p-4">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <ShieldCheck className="size-4 text-muted-foreground" />
                                            <Label
                                                htmlFor="affiliate-ip_whitelist_required"
                                                className="font-medium"
                                            >
                                                IP Whitelist Required
                                            </Label>
                                            <Badge variant="secondary">
                                                {allowedIps.length}
                                            </Badge>
                                        </div>
                                        <Switch
                                            id="affiliate-ip_whitelist_required"
                                            checked={ipWhitelistRequired}
                                            onCheckedChange={
                                                setIpWhitelistRequired
                                            }
                                        />
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        Only listed IPs can submit leads for
                                        this affiliate.
                                    </p>

                                    {ipWhitelistRequired && (
                                        <div className="space-y-3">
                                            <div className="relative">
                                                <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                                                <Input
                                                    value={ipSearch}
                                                    onChange={(event) =>
                                                        setIpSearch(
                                                            event.target.value,
                                                        )
                                                    }
                                                    placeholder="Search IPs..."
                                                    className="pl-8"
                                                />
                                            </div>

                                            <div className="flex gap-2">
                                                <Input
                                                    value={newIp}
                                                    onChange={(event) =>
                                                        setNewIp(
                                                            event.target.value,
                                                        )
                                                    }
                                                    onKeyDown={(event) => {
                                                        if (
                                                            event.key ===
                                                            'Enter'
                                                        ) {
                                                            event.preventDefault();
                                                            addIp();
                                                        }
                                                    }}
                                                    placeholder="e.g. 192.168.1.1"
                                                    className="flex-1"
                                                />
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={addIp}
                                                >
                                                    <Plus />
                                                    Add
                                                </Button>
                                                <Popover
                                                    open={bulkOpen}
                                                    onOpenChange={setBulkOpen}
                                                >
                                                    <PopoverTrigger asChild>
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                        >
                                                            <ClipboardList />
                                                            Bulk Add
                                                        </Button>
                                                    </PopoverTrigger>
                                                    <PopoverContent align="end">
                                                        <div className="space-y-2">
                                                            <Label htmlFor="affiliate-bulk-ips">
                                                                Paste IPs
                                                            </Label>
                                                            <Textarea
                                                                id="affiliate-bulk-ips"
                                                                value={bulkText}
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    setBulkText(
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    )
                                                                }
                                                                placeholder={
                                                                    'One per line, or comma separated'
                                                                }
                                                                rows={5}
                                                            />
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                className="w-full"
                                                                onClick={
                                                                    applyBulkAdd
                                                                }
                                                            >
                                                                Add IPs
                                                            </Button>
                                                        </div>
                                                    </PopoverContent>
                                                </Popover>
                                            </div>

                                            {allowedIps.length > 0 && (
                                                <div className="rounded-md border">
                                                    <Table>
                                                        <TableHeader>
                                                            <TableRow>
                                                                <TableHead className="w-8">
                                                                    #
                                                                </TableHead>
                                                                <TableHead>
                                                                    IP Address
                                                                </TableHead>
                                                                <TableHead className="w-8" />
                                                            </TableRow>
                                                        </TableHeader>
                                                        <TableBody>
                                                            {filteredIps.map(
                                                                (ip, index) => (
                                                                    <TableRow
                                                                        key={ip}
                                                                    >
                                                                        <TableCell className="text-muted-foreground">
                                                                            {index +
                                                                                1}
                                                                        </TableCell>
                                                                        <TableCell className="font-mono">
                                                                            {ip}
                                                                        </TableCell>
                                                                        <TableCell>
                                                                            <Button
                                                                                type="button"
                                                                                variant="ghost"
                                                                                size="icon"
                                                                                onClick={() =>
                                                                                    removeIp(
                                                                                        ip,
                                                                                    )
                                                                                }
                                                                            >
                                                                                <Trash2 className="text-destructive" />
                                                                            </Button>
                                                                        </TableCell>
                                                                    </TableRow>
                                                                ),
                                                            )}
                                                        </TableBody>
                                                    </Table>
                                                    <div className="flex items-center justify-between border-t px-3 py-2 text-xs">
                                                        <span className="text-muted-foreground">
                                                            {allowedIps.length}{' '}
                                                            IPs total
                                                        </span>
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                setAllowedIps(
                                                                    [],
                                                                )
                                                            }
                                                            className="text-destructive hover:underline"
                                                        >
                                                            Clear All
                                                        </button>
                                                    </div>
                                                </div>
                                            )}

                                            <Badge variant="secondary">
                                                {allowedIps.length} IPs
                                                whitelisted
                                            </Badge>
                                        </div>
                                    )}

                                    <InputError
                                        message={
                                            errors.allowed_ips ??
                                            errors.ip_whitelist_required
                                        }
                                    />
                                </div>
                            </div>

                            <DialogFooter className="gap-2 pt-4">
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    <Save />
                                    Save Changes
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
