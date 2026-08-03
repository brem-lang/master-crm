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
};

export function MultiSelect({
    options,
    selected,
    onChange,
    placeholder,
    className,
    icon,
}: MultiSelectProps) {
    const toggle = (value: string) => {
        onChange(
            selected.includes(value)
                ? selected.filter((v) => v !== value)
                : [...selected, value],
        );
    };

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
                        <span className="truncate">
                            {selected.length > 0
                                ? `${placeholder} (${selected.length})`
                                : placeholder}
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
