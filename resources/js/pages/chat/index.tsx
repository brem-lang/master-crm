import { Head, usePage } from '@inertiajs/react';
import { AlertCircle, Loader2, Send } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { relativeTime } from '@/lib/relative-time';
import { messages as chatMessages, store as chatStore } from '@/routes/chat';
import type { Auth, ChatMessage } from '@/types';

type PageProps = {
    company: { id: number; name: string };
    messages: ChatMessage[];
};

/**
 * How often an open chat window polls for new messages — matches
 * ChatController::SYNC_THROTTLE_SECONDS server-side, so a poll almost never
 * arrives faster than the child CRM pull it triggers can actually run.
 */
const POLL_INTERVAL_MS = 4000;

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

/**
 * Any message still `pending` needs to be re-fetched on the next poll so its
 * status can resolve to delivered/failed — everything before it is already
 * final, so only that tail (rather than the whole thread) needs re-checking.
 */
function pollCursor(messages: ChatMessage[]): number {
    const firstPending = messages.find(
        (message) => message.status === 'pending',
    );

    if (firstPending) {
        return firstPending.id - 1;
    }

    return messages.length > 0 ? messages[messages.length - 1].id : 0;
}

function mergeMessages(
    current: ChatMessage[],
    incoming: ChatMessage[],
): ChatMessage[] {
    if (incoming.length === 0) {
        return current;
    }

    const byId = new Map(current.map((message) => [message.id, message]));
    incoming.forEach((message) => byId.set(message.id, message));

    return Array.from(byId.values()).sort((a, b) => a.id - b.id);
}

function statusLabel(message: ChatMessage): string | null {
    switch (message.status) {
        case 'pending':
            return 'Sending…';
        case 'failed':
            return message.failure_reason
                ? `Failed — ${message.failure_reason}`
                : 'Failed to send';
        default:
            return null;
    }
}

export default function ChatIndex({
    company,
    messages: initialMessages,
}: PageProps) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [messages, setMessages] = useState<ChatMessage[]>(initialMessages);
    const [body, setBody] = useState('');
    const [sending, setSending] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const scrollRef = useRef<HTMLDivElement>(null);
    const messagesRef = useRef(messages);

    useEffect(() => {
        messagesRef.current = messages;
        scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight });
    }, [messages]);

    useEffect(() => {
        let cancelled = false;

        const poll = async () => {
            try {
                const afterId = pollCursor(messagesRef.current);
                const response = await fetch(
                    `${chatMessages(company).url}?after_id=${afterId}`,
                    {
                        headers: { Accept: 'application/json' },
                    },
                );

                if (!response.ok || cancelled) {
                    return;
                }

                const body: { data: ChatMessage[] } = await response.json();

                if (!cancelled) {
                    setMessages((current) => mergeMessages(current, body.data));
                }
            } catch {
                // A single missed poll isn't worth surfacing — the next tick tries again.
            }
        };

        // Skip polling entirely while the tab is backgrounded — no point
        // hitting the endpoint (and triggering a child-CRM pull) for a thread
        // nobody is looking at. The visibilitychange listener below catches
        // up immediately on refocus instead of waiting for the next tick.
        const pollIfVisible = () => {
            if (!document.hidden) {
                poll();
            }
        };

        const interval = setInterval(pollIfVisible, POLL_INTERVAL_MS);
        document.addEventListener('visibilitychange', pollIfVisible);

        return () => {
            cancelled = true;
            clearInterval(interval);
            document.removeEventListener('visibilitychange', pollIfVisible);
        };
    }, [company]);

    const send = async () => {
        const trimmed = body.trim();

        if (!trimmed || sending) {
            return;
        }

        setSending(true);
        setError(null);

        try {
            const response = await fetch(chatStore(company).url, {
                method: 'POST',
                headers: jsonHeaders(),
                body: JSON.stringify({ body: trimmed }),
            });

            if (!response.ok) {
                const problem = await response.json().catch(() => null);
                setError(
                    problem?.message ??
                        "Couldn't send that message. Try again.",
                );

                return;
            }

            const { data }: { data: ChatMessage } = await response.json();
            setMessages((current) => mergeMessages(current, [data]));
            setBody('');
        } catch {
            setError(
                "Couldn't reach the server. Check your connection and try again.",
            );
        } finally {
            setSending(false);
        }
    };

    return (
        <>
            <Head title={`Chat — ${company.name}`} />

            <div className="flex h-[calc(100vh-var(--header-height,4rem))] flex-col gap-4 p-4">
                <Heading
                    title={`Chat with ${company.name}`}
                    description="Messages here sync with this company's own child CRM."
                />

                <div
                    ref={scrollRef}
                    className="flex-1 space-y-3 overflow-y-auto rounded-md border p-4"
                >
                    {messages.length === 0 ? (
                        <p className="py-8 text-center text-sm text-muted-foreground">
                            No messages yet — say hello.
                        </p>
                    ) : (
                        messages.map((message) => {
                            const isMine =
                                message.sender_user_id === auth.user.id;
                            const label = statusLabel(message);

                            return (
                                <div
                                    key={message.id}
                                    className={`flex ${isMine ? 'justify-end' : 'justify-start'}`}
                                >
                                    <div className="max-w-[75%] space-y-1">
                                        <div
                                            className={
                                                isMine
                                                    ? 'rounded-lg bg-primary px-3 py-2 text-sm text-primary-foreground'
                                                    : 'rounded-lg bg-muted px-3 py-2 text-sm'
                                            }
                                        >
                                            {message.body}
                                        </div>
                                        <div className="flex items-center gap-2 px-1 text-xs text-muted-foreground">
                                            <span>
                                                {message.sender_name ??
                                                    (message.sender_type ===
                                                    'child'
                                                        ? company.name
                                                        : 'Master CRM')}
                                            </span>
                                            <span>·</span>
                                            <span>
                                                {relativeTime(
                                                    message.occurred_at,
                                                )}
                                            </span>
                                            {message.sender_type ===
                                                'child' && (
                                                <Badge
                                                    variant="outline"
                                                    className="h-4 px-1 text-[10px]"
                                                >
                                                    via child CRM
                                                </Badge>
                                            )}
                                            {label && (
                                                <span
                                                    className={
                                                        message.status ===
                                                        'failed'
                                                            ? 'text-destructive'
                                                            : ''
                                                    }
                                                >
                                                    {label}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            );
                        })
                    )}
                </div>

                {error && (
                    <div className="flex items-center gap-2 rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                        <AlertCircle className="size-4 shrink-0" />
                        {error}
                    </div>
                )}

                <form
                    className="flex items-end gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        send();
                    }}
                >
                    <Textarea
                        value={body}
                        onChange={(event) => setBody(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter' && !event.shiftKey) {
                                event.preventDefault();
                                send();
                            }
                        }}
                        placeholder="Write a message…"
                        className="min-h-11 flex-1 resize-none"
                        maxLength={4000}
                        disabled={sending}
                    />
                    <Button type="submit" disabled={sending || !body.trim()}>
                        {sending ? (
                            <Loader2 className="animate-spin" />
                        ) : (
                            <Send />
                        )}
                        Send
                    </Button>
                </form>
            </div>
        </>
    );
}

// The last breadcrumb is always rendered as plain (non-link) text — see
// Breadcrumbs.tsx — so there's no real destination to point this at.
ChatIndex.layout = {
    breadcrumbs: [{ title: 'Chat', href: '#' }],
};
