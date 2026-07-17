import type { LucideIcon } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

type Props = {
    icon: LucideIcon;
    label: string;
    value: string | number;
    sublabel: string;
    secondaryValue?: string | number;
    secondaryLabel?: string;
    secondaryClassName?: string;
};

export function MetricCard({
    icon: Icon,
    label,
    value,
    sublabel,
    secondaryValue,
    secondaryLabel,
    secondaryClassName,
}: Props) {
    return (
        <Card className="gap-3 py-4">
            <CardHeader className="px-4">
                <CardTitle className="flex items-center gap-1.5 text-sm font-medium text-muted-foreground">
                    <Icon className="size-4" />
                    {label}
                </CardTitle>
            </CardHeader>
            <CardContent className="flex items-end justify-between px-4">
                <div>
                    <p className="text-2xl font-semibold tabular-nums">
                        {value}
                    </p>
                    <p className="text-xs text-muted-foreground">{sublabel}</p>
                </div>
                {secondaryValue !== undefined && (
                    <div className="text-right">
                        <p
                            className={cn(
                                'text-2xl font-semibold tabular-nums',
                                secondaryClassName,
                            )}
                        >
                            {secondaryValue}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {secondaryLabel}
                        </p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
