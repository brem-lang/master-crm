import { Check, ChevronsUpDown } from 'lucide-react';
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

type Option = { value: string; label: string };

type MultiSelectProps = {
    options: Option[];
    selected: string[];
    onChange: (values: string[]) => void;
    placeholder: string;
    className?: string;
};

export function MultiSelect({
    options,
    selected,
    onChange,
    placeholder,
    className,
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
                                    <Check
                                        className={cn(
                                            'size-4',
                                            selected.includes(option.value)
                                                ? 'opacity-100'
                                                : 'opacity-0',
                                        )}
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
