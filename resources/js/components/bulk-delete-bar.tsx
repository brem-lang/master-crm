import { Trash2 } from 'lucide-react';
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
    description: string;
    onConfirm: (onFinish: () => void) => void;
};

export function BulkDeleteBar({ count, description, onConfirm }: Props) {
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
                    <Button variant="destructive" size="sm">
                        <Trash2 />
                        Delete selected
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogTitle>Delete {count} selected?</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="secondary">Cancel</Button>
                        </DialogClose>

                        <Button
                            variant="destructive"
                            disabled={processing}
                            onClick={handleConfirm}
                        >
                            <Trash2 />
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
