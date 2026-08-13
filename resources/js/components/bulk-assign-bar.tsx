import { UserCheck } from 'lucide-react';
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
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Rep = {
    id: number;
    name: string;
};

type Props = {
    count: number;
    reps: Rep[];
    onAssign: (assignedTo: number | null, onFinish: () => void) => void;
    /**
     * When true, renders only the action control (dialog trigger button)
     * without the outer wrapper or "N selected" label, so it can be
     * composed alongside other bulk actions in a single row.
     */
    bare?: boolean;
};

const UNASSIGNED = 'unassigned';

export function BulkAssignBar({ count, reps, onAssign, bare = false }: Props) {
    const [open, setOpen] = useState(false);
    const [value, setValue] = useState(UNASSIGNED);
    const [processing, setProcessing] = useState(false);

    if (count === 0) {
        return null;
    }

    const handleApply = () => {
        setProcessing(true);
        onAssign(value === UNASSIGNED ? null : Number(value), () => {
            setProcessing(false);
            setOpen(false);
        });
    };

    const dialog = (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <UserCheck />
                    Assign selected
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Assign {count} selected?</DialogTitle>
                <DialogDescription>
                    Choose a sales rep to assign the selected leads to, or
                    unassign them.
                </DialogDescription>

                <Select value={value} onValueChange={setValue}>
                    <SelectTrigger className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem value={UNASSIGNED}>Unassign</SelectItem>
                            {reps.map((rep) => (
                                <SelectItem key={rep.id} value={String(rep.id)}>
                                    {rep.name}
                                </SelectItem>
                            ))}
                        </SelectGroup>
                    </SelectContent>
                </Select>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Button disabled={processing} onClick={handleApply}>
                        <UserCheck />
                        Assign
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
