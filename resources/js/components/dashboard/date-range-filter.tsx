import { CalendarDays } from 'lucide-react';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type { DashboardFilters } from '@/types';

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

type Props = {
    filters: DashboardFilters;
    onChange: (next: Partial<DashboardFilters>) => void;
};

export function DateRangeFilter({ filters, onChange }: Props) {
    return (
        <>
            <ToggleGroup
                type="single"
                variant="outline"
                value={filters.range}
                onValueChange={(value) => value && onChange({ range: value })}
            >
                {RANGE_OPTIONS.map((option) => (
                    <ToggleGroupItem key={option.value} value={option.value}>
                        {option.label}
                    </ToggleGroupItem>
                ))}
            </ToggleGroup>

            <div className="flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm">
                <CalendarDays className="size-4 text-muted-foreground" />
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
                <span className="text-muted-foreground">→</span>
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
            </div>
        </>
    );
}
