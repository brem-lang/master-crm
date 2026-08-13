import { router } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useState } from 'react';
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
import { bulkUpdate } from '@/routes/affiliates';

function parseList(value: string): string[] {
    return value
        .split(',')
        .map((entry) => entry.trim())
        .filter(Boolean);
}

/**
 * A row in the dialog: a checkbox that decides whether this field is part
 * of the bulk edit at all, plus its own input — disabled until enabled.
 * Only the fields the admin turns on are sent, so a bulk "require IP
 * whitelisting on these 5 affiliates" doesn't also force a name/callback
 * URL on all of them.
 */
function FieldToggle({
    id,
    label,
    enabled,
    onEnabledChange,
    children,
}: {
    id: string;
    label: string;
    enabled: boolean;
    onEnabledChange: (enabled: boolean) => void;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <div className="flex items-center gap-2">
                <Checkbox
                    id={`${id}-enabled`}
                    checked={enabled}
                    onCheckedChange={(checked) =>
                        onEnabledChange(checked === true)
                    }
                />
                <Label htmlFor={`${id}-enabled`} className="font-normal">
                    {label}
                </Label>
            </div>
            {enabled && children}
        </div>
    );
}

export function BulkAffiliateEditDialog({
    count,
    selectedIds,
    onDone,
    bare = false,
}: {
    count: number;
    selectedIds: number[];
    onDone: () => void;
    bare?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const [nameEnabled, setNameEnabled] = useState(false);
    const [name, setName] = useState('');
    const [callbackUrlEnabled, setCallbackUrlEnabled] = useState(false);
    const [callbackUrl, setCallbackUrl] = useState('');
    const [countriesEnabled, setCountriesEnabled] = useState(false);
    const [countries, setCountries] = useState('');
    const [ipsEnabled, setIpsEnabled] = useState(false);
    const [ips, setIps] = useState('');
    const [activeEnabled, setActiveEnabled] = useState(false);
    const [isActive, setIsActive] = useState('active');
    const [testModeEnabled, setTestModeEnabled] = useState(false);
    const [testMode, setTestMode] = useState('off');
    const [whitelistEnabled, setWhitelistEnabled] = useState(false);
    const [whitelistRequired, setWhitelistRequired] = useState('off');

    if (count === 0) {
        return null;
    }

    const anyEnabled =
        nameEnabled ||
        callbackUrlEnabled ||
        countriesEnabled ||
        ipsEnabled ||
        activeEnabled ||
        testModeEnabled ||
        whitelistEnabled;
    const canSend = !processing && anyEnabled;

    const handleConfirm = () => {
        setProcessing(true);

        router.patch(
            bulkUpdate().url,
            {
                ids: selectedIds,
                ...(nameEnabled && { name }),
                ...(callbackUrlEnabled && { callback_url: callbackUrl }),
                ...(countriesEnabled && {
                    allowed_countries: parseList(countries).map((code) =>
                        code.toUpperCase(),
                    ),
                }),
                ...(ipsEnabled && { allowed_ips: parseList(ips) }),
                ...(activeEnabled && {
                    is_active: isActive === 'active' ? '1' : '0',
                }),
                ...(testModeEnabled && {
                    test_mode: testMode === 'on' ? '1' : '0',
                }),
                ...(whitelistEnabled && {
                    ip_whitelist_required:
                        whitelistRequired === 'on' ? '1' : '0',
                }),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setOpen(false);
                    onDone();
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    const dialog = (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <Pencil />
                    Edit selected
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit {count} selected</DialogTitle>
                    <DialogDescription>
                        Turn on only the fields you want to change — anything
                        left off keeps each affiliate's current value.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    <FieldToggle
                        id="bulk-affiliate-name"
                        label="Name"
                        enabled={nameEnabled}
                        onEnabledChange={setNameEnabled}
                    >
                        <Input
                            id="bulk-affiliate-name-value"
                            value={name}
                            onChange={(event) => setName(event.target.value)}
                        />
                    </FieldToggle>

                    <FieldToggle
                        id="bulk-affiliate-callback-url"
                        label="Callback URL"
                        enabled={callbackUrlEnabled}
                        onEnabledChange={setCallbackUrlEnabled}
                    >
                        <Input
                            id="bulk-affiliate-callback-url-value"
                            type="url"
                            value={callbackUrl}
                            onChange={(event) =>
                                setCallbackUrl(event.target.value)
                            }
                        />
                    </FieldToggle>

                    <FieldToggle
                        id="bulk-affiliate-countries"
                        label="Allowed countries"
                        enabled={countriesEnabled}
                        onEnabledChange={setCountriesEnabled}
                    >
                        <Input
                            id="bulk-affiliate-countries-value"
                            value={countries}
                            onChange={(event) =>
                                setCountries(event.target.value)
                            }
                            placeholder="US, CA, GB (blank = all countries)"
                        />
                    </FieldToggle>

                    <FieldToggle
                        id="bulk-affiliate-ips"
                        label="Allowed IPs"
                        enabled={ipsEnabled}
                        onEnabledChange={setIpsEnabled}
                    >
                        <Input
                            id="bulk-affiliate-ips-value"
                            value={ips}
                            onChange={(event) => setIps(event.target.value)}
                            placeholder="203.0.113.10, 203.0.113.11"
                        />
                    </FieldToggle>

                    <FieldToggle
                        id="bulk-affiliate-active"
                        label="Active status"
                        enabled={activeEnabled}
                        onEnabledChange={setActiveEnabled}
                    >
                        <Select value={isActive} onValueChange={setIsActive}>
                            <SelectTrigger id="bulk-affiliate-active-value">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="active">
                                        Active
                                    </SelectItem>
                                    <SelectItem value="inactive">
                                        Inactive
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </FieldToggle>

                    <FieldToggle
                        id="bulk-affiliate-test-mode"
                        label="Test mode"
                        enabled={testModeEnabled}
                        onEnabledChange={setTestModeEnabled}
                    >
                        <Select value={testMode} onValueChange={setTestMode}>
                            <SelectTrigger id="bulk-affiliate-test-mode-value">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="on">On</SelectItem>
                                    <SelectItem value="off">Off</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </FieldToggle>

                    <FieldToggle
                        id="bulk-affiliate-whitelist"
                        label="Require IP whitelist"
                        enabled={whitelistEnabled}
                        onEnabledChange={setWhitelistEnabled}
                    >
                        <Select
                            value={whitelistRequired}
                            onValueChange={setWhitelistRequired}
                        >
                            <SelectTrigger id="bulk-affiliate-whitelist-value">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="on">On</SelectItem>
                                    <SelectItem value="off">Off</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </FieldToggle>
                </div>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Button disabled={!canSend} onClick={handleConfirm}>
                        <Pencil />
                        Update {count}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );

    if (bare) {
        return dialog;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-2 rounded-md border bg-muted/50 px-4 py-2">
            <span className="text-sm text-muted-foreground">
                {count} selected
            </span>

            {dialog}
        </div>
    );
}
