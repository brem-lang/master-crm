import { useState } from 'react';

export function useRowSelection<T extends { id: number }>(
    rows: T[],
    resetKey?: unknown,
) {
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [prevResetKey, setPrevResetKey] = useState(resetKey);

    if (prevResetKey !== resetKey) {
        setPrevResetKey(resetKey);
        setSelectedIds([]);
    }

    const isSelected = (id: number) => selectedIds.includes(id);
    const allSelected =
        rows.length > 0 && rows.every((row) => selectedIds.includes(row.id));
    const someSelected = selectedIds.length > 0 && !allSelected;

    const toggleAll = (checked: boolean) => {
        setSelectedIds(checked ? rows.map((row) => row.id) : []);
    };

    const toggleOne = (id: number, checked: boolean) => {
        setSelectedIds((current) =>
            checked
                ? [...current, id]
                : current.filter((existing) => existing !== id),
        );
    };

    return {
        selectedIds,
        setSelectedIds,
        isSelected,
        allSelected,
        someSelected,
        toggleAll,
        toggleOne,
    };
}
