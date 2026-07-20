import { usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { relativeTime } from '@/lib/relative-time';
import {
    clear as clearAllNotifications,
    index as notificationsIndex,
    read as markNotificationAsRead,
    readAll as markAllNotificationsAsRead,
} from '@/routes/notifications';
import type { JobRunNotification } from '@/types';

function xsrfToken(): string {
    return decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );
}

function jsonHeaders(): HeadersInit {
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': xsrfToken(),
    };
}

function statusBadgeClass(success: boolean): string {
    return success
        ? 'border-transparent bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
        : 'border-transparent bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
}

export function NotificationsBell() {
    const { notifications } = usePage<{
        notifications: { unread_count: number };
    }>().props;
    const [unreadCount, setUnreadCount] = useState(notifications.unread_count);
    const [items, setItems] = useState<JobRunNotification[] | null>(null);

    // The layout persists across Inertia navigations, so this component never
    // remounts — resync local state whenever the server sends a fresh count
    // (React's "adjust state during render" pattern, not an effect, so it
    // doesn't cost an extra render pass).
    const [syncedCount, setSyncedCount] = useState(notifications.unread_count);

    if (notifications.unread_count !== syncedCount) {
        setSyncedCount(notifications.unread_count);
        setUnreadCount(notifications.unread_count);
    }

    const loadNotifications = async () => {
        const response = await fetch(notificationsIndex().url, {
            headers: { Accept: 'application/json' },
        });
        const body = await response.json();
        setItems(body.notifications);
    };

    const markAsRead = async (notification: JobRunNotification) => {
        if (notification.read_at) {
            return;
        }

        await fetch(markNotificationAsRead(notification.id).url, {
            method: 'POST',
            headers: jsonHeaders(),
        });

        setItems(
            (current) =>
                current?.map((item) =>
                    item.id === notification.id
                        ? { ...item, read_at: new Date().toISOString() }
                        : item,
                ) ?? null,
        );
        setUnreadCount((count) => Math.max(0, count - 1));
    };

    const markAllAsRead = async () => {
        await fetch(markAllNotificationsAsRead().url, {
            method: 'POST',
            headers: jsonHeaders(),
        });

        setItems(
            (current) =>
                current?.map((item) => ({
                    ...item,
                    read_at: item.read_at ?? new Date().toISOString(),
                })) ?? null,
        );
        setUnreadCount(0);
    };

    const clearAll = async () => {
        await fetch(clearAllNotifications().url, {
            method: 'DELETE',
            headers: jsonHeaders(),
        });

        setItems([]);
        setUnreadCount(0);
    };

    return (
        <DropdownMenu
            onOpenChange={(open) => {
                if (open) {
                    loadNotifications();
                }
            }}
        >
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="relative">
                    <Bell className="size-4" />
                    {unreadCount > 0 && (
                        <Badge
                            variant="destructive"
                            className="absolute -top-1 -right-1 flex size-4 items-center justify-center rounded-full p-0 text-[10px]"
                        >
                            {unreadCount > 9 ? '9+' : unreadCount}
                        </Badge>
                    )}
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" className="w-80">
                <div className="flex items-center justify-between px-2 py-1.5">
                    <DropdownMenuLabel className="p-0">
                        Notifications
                    </DropdownMenuLabel>
                    <div className="flex items-center gap-3">
                        {unreadCount > 0 && (
                            <button
                                type="button"
                                className="text-xs text-muted-foreground hover:text-foreground"
                                onClick={markAllAsRead}
                            >
                                Mark all read
                            </button>
                        )}
                        {items !== null && items.length > 0 && (
                            <button
                                type="button"
                                className="text-xs text-muted-foreground hover:text-foreground"
                                onClick={clearAll}
                            >
                                Clear all
                            </button>
                        )}
                    </div>
                </div>

                {items === null ? (
                    <p className="px-2 py-4 text-center text-sm text-muted-foreground">
                        Loading…
                    </p>
                ) : items.length === 0 ? (
                    <p className="px-2 py-4 text-center text-sm text-muted-foreground">
                        No notifications yet.
                    </p>
                ) : (
                    items.map((item) => (
                        <DropdownMenuItem
                            key={item.id}
                            className="flex flex-col items-start gap-1 whitespace-normal"
                            onSelect={(event) => {
                                event.preventDefault();
                                markAsRead(item);
                            }}
                        >
                            <div className="flex w-full items-center justify-between gap-2">
                                <span className="font-medium">
                                    {item.data.company_name ?? 'Company'}
                                </span>
                                <Badge
                                    variant="outline"
                                    className={statusBadgeClass(
                                        item.data.success,
                                    )}
                                >
                                    {item.data.success ? 'Success' : 'Failed'}
                                </Badge>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {item.data.message}
                            </p>
                            <span className="text-xs text-muted-foreground">
                                {relativeTime(item.created_at)}
                                {!item.read_at && ' · unread'}
                            </span>
                        </DropdownMenuItem>
                    ))
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
