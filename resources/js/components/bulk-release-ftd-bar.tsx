import { BadgeCheck } from 'lucide-react';
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
    onConfirm: (onFinish: () => void) => void;
};

export function BulkReleaseFtdBar({ count, onConfirm }: Props) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    if (count === 0) {
        return null;
    }

    const handleConfirm = () => {
        setProcessing(true);
        onConfirm(() => {
            setProcessing(false);
            setOpen(false);
        });
    };

    return (
        <div className="flex items-center justify-between rounded-md border bg-muted/50 px-4 py-2">
            <span className="text-sm text-muted-foreground">
                {count} selected
            </span>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogTrigger asChild>
                    <Button size="sm">
                        <BadgeCheck />
                        Release FTD
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogTitle>Release {count} FTDs?</DialogTitle>
                    <DialogDescription>
                        This marks the selected leads' FTDs as released with
                        their child CRM. Already-released or ineligible leads
                        are skipped. This action cannot be undone.
                    </DialogDescription>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="secondary">Cancel</Button>
                        </DialogClose>

                        <Button disabled={processing} onClick={handleConfirm}>
                            <BadgeCheck />
                            Release FTD
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
