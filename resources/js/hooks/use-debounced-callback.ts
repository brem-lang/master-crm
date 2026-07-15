import { useEffect, useMemo, useRef } from 'react';

export function useDebouncedCallback<Args extends unknown[]>(
    callback: (...args: Args) => void,
    delay: number,
) {
    const callbackRef = useRef(callback);
    const timeoutRef = useRef<ReturnType<typeof setTimeout>>(undefined);

    useEffect(() => {
        callbackRef.current = callback;
    });

    useEffect(() => {
        return () => clearTimeout(timeoutRef.current);
    }, []);

    return useMemo(() => {
        return (...args: Args) => {
            clearTimeout(timeoutRef.current);
            timeoutRef.current = setTimeout(
                () => callbackRef.current(...args),
                delay,
            );
        };
    }, [delay]);
}
