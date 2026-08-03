import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';

const RANGE_OPTIONS: { value: string; label: string }[] = [
    { value: 'today', label: 'Today' },
    { value: 'yesterday', label: 'Yesterday' },
    { value: 'week', label: 'This Week' },
    { value: 'last_week', label: 'Last Week' },
    { value: 'month', label: 'This Month' },
    { value: 'last_month', label: 'Last Month' },
    { value: 'all', label: 'All' },
    { value: 'custom', label: 'Custom' },
];

type DateRangeFilterValue = {
    range: string;
    from: string | null;
    to: string | null;
};

type Props = {
    filters: DateRangeFilterValue;
    onChange: (next: Partial<DateRangeFilterValue>) => void;
};

function daysBetween(from: string, to: string): number {
    const start = new Date(`${from}T00:00:00`);
    const end = new Date(`${to}T00:00:00`);

    return Math.max(
        1,
        Math.round((end.getTime() - start.getTime()) / 86_400_000) + 1,
    );
}

function shiftDate(date: string, days: number): string {
    const next = new Date(`${date}T00:00:00`);
    next.setDate(next.getDate() + days);

    return next.toISOString().slice(0, 10);
}

export function DateRangeFilter({ filters, onChange }: Props) {
    const canShift = filters.range !== 'all' && !!filters.from && !!filters.to;
    const span = canShift
        ? daysBetween(filters.from as string, filters.to as string)
        : 0;

    const shift = (direction: 1 | -1) => {
        if (!canShift) {
            return;
        }

        onChange({
            range: 'custom',
            from: shiftDate(filters.from as string, direction * span),
            to: shiftDate(filters.to as string, direction * span),
        });
    };

    return (
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex flex-wrap items-center gap-5">
                {RANGE_OPTIONS.map((option) => (
                    <button
                        key={option.value}
                        type="button"
                        onClick={() => onChange({ range: option.value })}
                        className={cn(
                            'text-sm transition-colors',
                            filters.range === option.value
                                ? 'font-semibold text-foreground underline underline-offset-4'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {option.label}
                    </button>
                ))}
            </div>

            <div className="flex flex-nowrap items-center gap-2">
                <label className="flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm">
                    <CalendarDays className="size-4 shrink-0 text-muted-foreground" />
                    <span className="shrink-0 text-muted-foreground">
                        From:
                    </span>
                    <input
                        type="date"
                        value={filters.from ?? ''}
                        onChange={(event) =>
                            onChange({
                                range: 'custom',
                                from: event.target.value,
                            })
                        }
                        className="bg-transparent outline-none"
                    />
                </label>

                <label className="flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm">
                    <CalendarDays className="size-4 shrink-0 text-muted-foreground" />
                    <span className="shrink-0 text-muted-foreground">To:</span>
                    <input
                        type="date"
                        value={filters.to ?? ''}
                        onChange={(event) =>
                            onChange({
                                range: 'custom',
                                to: event.target.value,
                            })
                        }
                        className="bg-transparent outline-none"
                    />
                </label>

                <div className="flex shrink-0 items-center gap-1 rounded-full border px-2 py-1.5">
                    <button
                        type="button"
                        onClick={() => shift(-1)}
                        disabled={!canShift}
                        aria-label="Shift range back"
                        className="rounded-full p-1 text-muted-foreground hover:bg-accent hover:text-foreground disabled:pointer-events-none disabled:opacity-50"
                    >
                        <ChevronLeft className="size-4" />
                    </button>
                    <span className="px-1 text-xs text-muted-foreground">
                        {canShift ? `${span}d` : '—'}
                    </span>
                    <button
                        type="button"
                        onClick={() => shift(1)}
                        disabled={!canShift}
                        aria-label="Shift range forward"
                        className="rounded-full p-1 text-muted-foreground hover:bg-accent hover:text-foreground disabled:pointer-events-none disabled:opacity-50"
                    >
                        <ChevronRight className="size-4" />
                    </button>
                </div>
            </div>
        </div>
    );
}
