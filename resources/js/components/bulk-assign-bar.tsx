import { UserCheck } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
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
};

const UNASSIGNED = 'unassigned';

export function BulkAssignBar({ count, reps, onAssign }: Props) {
    const [value, setValue] = useState(UNASSIGNED);
    const [processing, setProcessing] = useState(false);

    if (count === 0) {
        return null;
    }

    const handleApply = () => {
        setProcessing(true);
        onAssign(value === UNASSIGNED ? null : Number(value), () =>
            setProcessing(false),
        );
    };

    return (
        <div className="flex flex-wrap items-center justify-between gap-2 rounded-md border bg-muted/50 px-4 py-2">
            <span className="text-sm text-muted-foreground">
                {count} selected
            </span>

            <div className="flex items-center gap-2">
                <Select value={value} onValueChange={setValue}>
                    <SelectTrigger className="w-48">
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

                <Button size="sm" disabled={processing} onClick={handleApply}>
                    <UserCheck />
                    Assign selected
                </Button>
            </div>
        </div>
    );
}
