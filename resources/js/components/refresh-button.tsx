import { router } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export function RefreshButton() {
    const [refreshing, setRefreshing] = useState(false);

    return (
        <Button
            variant="outline"
            disabled={refreshing}
            onClick={() => {
                setRefreshing(true);
                router.reload({
                    onFinish: () => setRefreshing(false),
                });
            }}
        >
            <RefreshCw className={cn(refreshing && 'animate-spin')} />
            Refresh
        </Button>
    );
}
