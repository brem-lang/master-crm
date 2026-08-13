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
import { bulkUpdate } from '@/routes/advertisers';

/**
 * A row in the dialog: a checkbox that decides whether this field is part
 * of the bulk edit at all, plus its own input — disabled until enabled.
 * Only the fields the admin turns on are sent, so a bulk "deactivate these
 * 5 advertisers" doesn't also force a name/url/cap on all of them.
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

export function BulkAdvertiserEditDialog({
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
    const [urlEnabled, setUrlEnabled] = useState(false);
    const [url, setUrl] = useState('');
    const [dailyCapEnabled, setDailyCapEnabled] = useState(false);
    const [dailyCap, setDailyCap] = useState('');
    const [hourlyCapEnabled, setHourlyCapEnabled] = useState(false);
    const [hourlyCap, setHourlyCap] = useState('');
    const [activeEnabled, setActiveEnabled] = useState(false);
    const [isActive, setIsActive] = useState('active');

    if (count === 0) {
        return null;
    }

    const anyEnabled =
        nameEnabled ||
        urlEnabled ||
        dailyCapEnabled ||
        hourlyCapEnabled ||
        activeEnabled;
    const canSend = !processing && anyEnabled;

    const handleConfirm = () => {
        setProcessing(true);

        router.patch(
            bulkUpdate().url,
            {
                ids: selectedIds,
                ...(nameEnabled && { name }),
                ...(urlEnabled && { url }),
                ...(dailyCapEnabled && {
                    daily_cap: dailyCap === '' ? null : Number(dailyCap),
                }),
                ...(hourlyCapEnabled && {
                    hourly_cap: hourlyCap === '' ? null : Number(hourlyCap),
                }),
                ...(activeEnabled && {
                    is_active: isActive === 'active' ? '1' : '0',
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
                        left off keeps each advertiser's current value.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    <FieldToggle
                        id="bulk-advertiser-name"
                        label="Name"
                        enabled={nameEnabled}
                        onEnabledChange={setNameEnabled}
                    >
                        <Input
                            id="bulk-advertiser-name-value"
                            value={name}
                            onChange={(event) => setName(event.target.value)}
                        />
                    </FieldToggle>

                    <FieldToggle
                        id="bulk-advertiser-url"
                        label="URL"
                        enabled={urlEnabled}
                        onEnabledChange={setUrlEnabled}
                    >
                        <Input
                            id="bulk-advertiser-url-value"
                            type="url"
                            value={url}
                            onChange={(event) => setUrl(event.target.value)}
                        />
                    </FieldToggle>

                    <div className="grid grid-cols-2 gap-4">
                        <FieldToggle
                            id="bulk-advertiser-daily-cap"
                            label="Daily cap"
                            enabled={dailyCapEnabled}
                            onEnabledChange={setDailyCapEnabled}
                        >
                            <Input
                                id="bulk-advertiser-daily-cap-value"
                                type="number"
                                min={0}
                                value={dailyCap}
                                onChange={(event) =>
                                    setDailyCap(event.target.value)
                                }
                                placeholder="Leave blank to clear"
                            />
                        </FieldToggle>

                        <FieldToggle
                            id="bulk-advertiser-hourly-cap"
                            label="Hourly cap"
                            enabled={hourlyCapEnabled}
                            onEnabledChange={setHourlyCapEnabled}
                        >
                            <Input
                                id="bulk-advertiser-hourly-cap-value"
                                type="number"
                                min={0}
                                value={hourlyCap}
                                onChange={(event) =>
                                    setHourlyCap(event.target.value)
                                }
                                placeholder="Leave blank to clear"
                            />
                        </FieldToggle>
                    </div>

                    <FieldToggle
                        id="bulk-advertiser-active"
                        label="Active status"
                        enabled={activeEnabled}
                        onEnabledChange={setActiveEnabled}
                    >
                        <Select value={isActive} onValueChange={setIsActive}>
                            <SelectTrigger id="bulk-advertiser-active-value">
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
