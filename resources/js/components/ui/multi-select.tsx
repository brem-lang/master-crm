import { ChevronsUpDown } from 'lucide-react';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { Button } from './button';
import { Checkbox } from './checkbox';

type Option = { value: string; label: string };

type MultiSelectProps = {
    options: Option[];
    selected: string[];
    onChange: (values: string[]) => void;
    placeholder: string;
    className?: string;
    /** When provided, the trigger renders as an icon-only button instead of placeholder text. */
    icon?: React.ReactNode;
    /** When provided (and `icon` isn't), renders a leading icon alongside the placeholder text. */
    leadingIcon?: React.ReactNode;
    /** Adds a "Select all" / "Clear all" row above the option list. */
    withSelectAll?: boolean;
};

export function MultiSelect({
    options,
    selected,
    onChange,
    placeholder,
    className,
    icon,
    leadingIcon,
    withSelectAll,
}: MultiSelectProps) {
    const toggle = (value: string) => {
        onChange(
            selected.includes(value)
                ? selected.filter((v) => v !== value)
                : [...selected, value],
        );
    };
    const allSelected =
        options.length > 0 && selected.length === options.length;

    return (
        <Popover>
            <PopoverTrigger asChild>
                {icon ? (
                    <Button
                        variant="outline"
                        size="icon"
                        role="combobox"
                        aria-label={placeholder}
                        className={cn('shrink-0', className)}
                    >
                        {icon}
                    </Button>
                ) : (
                    <Button
                        variant="outline"
                        role="combobox"
                        className={cn(
                            'w-full justify-between font-normal sm:w-48',
                            className,
                        )}
                    >
                        <span className="flex min-w-0 items-center gap-2">
                            {leadingIcon}
                            <span className="truncate">
                                {selected.length > 0
                                    ? `${placeholder} (${selected.length})`
                                    : placeholder}
                            </span>
                        </span>
                        <ChevronsUpDown className="opacity-50" />
                    </Button>
                )}
            </PopoverTrigger>
            <PopoverContent className="w-56 p-0" align="start">
                <Command>
                    <CommandInput
                        placeholder={`Search ${placeholder.toLowerCase()}…`}
                    />
                    {withSelectAll && (
                        <div className="flex items-center justify-between border-b px-2 py-1.5">
                            <button
                                type="button"
                                onClick={() =>
                                    onChange(
                                        options.map((option) => option.value),
                                    )
                                }
                                disabled={allSelected}
                                className="text-xs text-muted-foreground hover:text-foreground hover:underline disabled:pointer-events-none disabled:opacity-50"
                            >
                                Select all
                            </button>
                            <button
                                type="button"
                                onClick={() => onChange([])}
                                disabled={selected.length === 0}
                                className="text-xs text-muted-foreground hover:text-foreground hover:underline disabled:pointer-events-none disabled:opacity-50"
                            >
                                Clear all
                            </button>
                        </div>
                    )}
                    <CommandList>
                        <CommandEmpty>No results.</CommandEmpty>
                        <CommandGroup>
                            {options.map((option) => (
                                <CommandItem
                                    key={option.value}
                                    value={option.label}
                                    onSelect={() => toggle(option.value)}
                                >
                                    <Checkbox
                                        checked={selected.includes(
                                            option.value,
                                        )}
                                        className="pointer-events-none"
                                    />
                                    {option.label}
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
