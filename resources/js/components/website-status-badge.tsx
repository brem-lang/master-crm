import { cn } from '@/lib/utils';
import type { Company } from '@/types';

export function WebsiteStatusBadge({
    status,
}: {
    status: Company['website_status'];
}) {
    const label =
        status === 'online'
            ? 'Online'
            : status === 'offline'
              ? 'Offline'
              : 'Checking…';

    return (
        <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
            <span
                className={cn('size-2 rounded-full', {
                    'bg-green-500': status === 'online',
                    'bg-red-500': status === 'offline',
                    'bg-muted-foreground/40': !status,
                })}
            />
            {label}
        </div>
    );
}
