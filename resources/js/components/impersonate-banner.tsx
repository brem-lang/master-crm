import { Link, router, usePage } from '@inertiajs/react';
import { useLayoutEffect, useRef } from 'react';
import ImpersonateController from '@/actions/App/Http/Controllers/Admin/ImpersonateController';
import { Button } from '@/components/ui/button';
import type { Auth } from '@/types';

export function ImpersonateBanner() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const ref = useRef<HTMLDivElement>(null);

    // The desktop sidebar is viewport-fixed, so it needs this banner's
    // height as a CSS variable to offset itself below it instead of
    // overlapping it (see --impersonate-banner-h usages in ui/sidebar.tsx).
    useLayoutEffect(() => {
        const root = document.documentElement;

        if (!auth.impersonating || !ref.current) {
            root.style.setProperty('--impersonate-banner-h', '0px');

            return;
        }

        const el = ref.current;
        const updateHeight = () => root.style.setProperty('--impersonate-banner-h', `${el.offsetHeight}px`);

        updateHeight();

        const observer = new ResizeObserver(updateHeight);
        observer.observe(el);

        return () => {
            observer.disconnect();
            root.style.setProperty('--impersonate-banner-h', '0px');
        };
    }, [auth.impersonating]);

    if (!auth.impersonating) {
        return null;
    }

    return (
        <div ref={ref} className="flex items-center justify-center gap-4 bg-yellow-500 px-4 py-2 text-sm font-medium text-yellow-950">
            <span>You are impersonating {auth.user.name}.</span>

            <Button variant="secondary" size="sm" asChild>
                <Link
                    href={ImpersonateController.stop()}
                    method="delete"
                    onSuccess={() => router.flushAll()}
                >
                    Stop impersonating
                </Link>
            </Button>
        </div>
    );
}
