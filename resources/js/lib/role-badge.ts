export const ROLE_BADGE_CLASSES: Record<string, string> = {
    'parent-admin':
        'border-transparent bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
    'child-admin':
        'border-transparent bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    'sales-rep':
        'border-transparent bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
};

export const ROLE_LABELS: Record<string, string> = {
    'parent-admin': 'Parent Admin',
    'child-admin': 'Child Admin',
    'sales-rep': 'Sales Rep',
};

export function roleLabel(name: string): string {
    return ROLE_LABELS[name] ?? name;
}
