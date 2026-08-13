import { Ban, Check } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

type Props = {
    count: number;
    onConfirm: (isActive: boolean, onFinish: () => void) => void;
    /**
     * When true, renders only the action controls (the two status buttons)
     * without the outer wrapper or "N selected" label, so it can be
     * composed alongside other bulk actions in a single row.
     */
    bare?: boolean;
};

function StatusAction({
    count,
    isActive,
    onConfirm,
}: {
    count: number;
    isActive: boolean;
    onConfirm: Props['onConfirm'];
}) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const handleConfirm = () => {
        setProcessing(true);
        onConfirm(isActive, () => {
            setProcessing(false);
            setOpen(false);
        });
    };

    const label = isActive ? 'Set Active' : 'Set Inactive';

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant={isActive ? 'default' : 'outline'} size="sm">
                    {isActive ? <Check /> : <Ban />}
                    {label}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    {label} for {count} selected?
                </DialogTitle>
                <DialogDescription>
                    This notifies each affiliate&apos;s child CRM that it should
                    be marked as {isActive ? 'active' : 'inactive'}. Affiliates
                    already in that state, or ones that fail to update with
                    their child CRM, are skipped.
                </DialogDescription>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Button disabled={processing} onClick={handleConfirm}>
                        {isActive ? <Check /> : <Ban />}
                        {label}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export function BulkAffiliateStatusBar({
    count,
    onConfirm,
    bare = false,
}: Props) {
    if (count === 0) {
        return null;
    }

    const actions = (
        <div className="flex items-center gap-2">
            <StatusAction count={count} isActive={false} onConfirm={onConfirm} />
            <StatusAction count={count} isActive={true} onConfirm={onConfirm} />
        </div>
    );

    if (bare) {
        return actions;
    }

    return (
        <div className="flex items-center justify-between rounded-md border bg-muted/50 px-4 py-2">
            <span className="text-sm text-muted-foreground">
                {count} selected
            </span>

            {actions}
        </div>
    );
}
