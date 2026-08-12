import {
    DndContext,
    KeyboardSensor,
    PointerSensor,
    closestCenter,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import type { DragEndEvent } from '@dnd-kit/core';
import {
    SortableContext,
    arrayMove,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Columns3, GripVertical, Search } from 'lucide-react';
import { useState } from 'react';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { Button } from './ui/button';
import { Checkbox } from './ui/checkbox';
import { Input } from './ui/input';

type Column = { key: string; label: string };

type ColumnsMenuProps = {
    columns: Column[];
    order: string[];
    hidden: string[];
    onChange: (order: string[], hidden: string[]) => void;
};

function SortableColumnRow({
    column,
    checked,
    onToggle,
}: {
    column: Column;
    checked: boolean;
    onToggle: () => void;
}) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id: column.key });

    return (
        <div
            ref={setNodeRef}
            style={{
                transform: CSS.Transform.toString(transform),
                transition,
            }}
            className={cn(
                'flex items-center gap-2 rounded-sm px-2 py-1.5 text-sm',
                isDragging && 'z-10 bg-accent',
            )}
        >
            <button
                type="button"
                className="shrink-0 cursor-grab touch-none text-muted-foreground active:cursor-grabbing"
                aria-label={`Reorder ${column.label}`}
                {...attributes}
                {...listeners}
            >
                <GripVertical className="size-4" />
            </button>
            <label className="flex flex-1 cursor-pointer items-center gap-2">
                <Checkbox checked={checked} onCheckedChange={onToggle} />
                {column.label}
            </label>
        </div>
    );
}

/**
 * Column show/hide + drag-to-reorder control for the leads table.
 * `order` and `hidden` are lifted state so the parent can persist both
 * together (e.g. to the server) after a change.
 */
export function ColumnsMenu({
    columns,
    order,
    hidden,
    onChange,
}: ColumnsMenuProps) {
    const [search, setSearch] = useState('');
    const sensors = useSensors(
        useSensor(PointerSensor),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    const columnsByKey = new Map(columns.map((column) => [column.key, column]));
    const orderedColumns = order
        .map((key) => columnsByKey.get(key))
        .filter((column): column is Column => column !== undefined);

    // Filtering only affects what's rendered — dragging always reorders
    // against the full `order` array (via indexOf by id below), so it
    // still behaves correctly while a search term is active.
    const visibleColumns = search
        ? orderedColumns.filter((column) =>
              column.label.toLowerCase().includes(search.toLowerCase()),
          )
        : orderedColumns;

    const handleDragEnd = (event: DragEndEvent) => {
        const { active, over } = event;

        if (!over || active.id === over.id) {
            return;
        }

        const oldIndex = order.indexOf(String(active.id));
        const newIndex = order.indexOf(String(over.id));

        onChange(arrayMove(order, oldIndex, newIndex), hidden);
    };

    const toggleColumn = (key: string) => {
        onChange(
            order,
            hidden.includes(key)
                ? hidden.filter((k) => k !== key)
                : [...hidden, key],
        );
    };

    return (
        <Popover onOpenChange={(open) => !open && setSearch('')}>
            <PopoverTrigger asChild>
                <Button variant="outline" className="shrink-0">
                    <Columns3 className="size-4" />
                    Columns
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-64 p-1" align="start">
                <div className="relative p-1">
                    <Search className="absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search columns…"
                        className="h-8 pl-8"
                    />
                </div>
                <div className="max-h-72 overflow-y-auto">
                    <DndContext
                        sensors={sensors}
                        collisionDetection={closestCenter}
                        onDragEnd={handleDragEnd}
                    >
                        <SortableContext
                            items={visibleColumns.map((column) => column.key)}
                            strategy={verticalListSortingStrategy}
                        >
                            {visibleColumns.length > 0 ? (
                                visibleColumns.map((column) => (
                                    <SortableColumnRow
                                        key={column.key}
                                        column={column}
                                        checked={!hidden.includes(column.key)}
                                        onToggle={() =>
                                            toggleColumn(column.key)
                                        }
                                    />
                                ))
                            ) : (
                                <p className="p-2 text-sm text-muted-foreground">
                                    No columns found.
                                </p>
                            )}
                        </SortableContext>
                    </DndContext>
                </div>
            </PopoverContent>
        </Popover>
    );
}
