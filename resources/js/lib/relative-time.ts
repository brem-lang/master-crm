export function relativeTime(value: string): string {
    const diffSeconds = Math.round(
        (new Date(value).getTime() - Date.now()) / 1000,
    );
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['year', 60 * 60 * 24 * 365],
        ['month', 60 * 60 * 24 * 30],
        ['day', 60 * 60 * 24],
        ['hour', 60 * 60],
        ['minute', 60],
    ];

    for (const [unit, secondsInUnit] of units) {
        if (Math.abs(diffSeconds) >= secondsInUnit) {
            return new Intl.RelativeTimeFormat('en', {
                numeric: 'auto',
            }).format(Math.round(diffSeconds / secondsInUnit), unit);
        }
    }

    return 'just now';
}
