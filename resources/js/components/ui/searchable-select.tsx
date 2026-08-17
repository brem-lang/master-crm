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

type SearchableSelectProps = {
    id?: string;
    options: Option[];
    value: string | null;
    onChange: (value: string | null) => void;
    placeholder: string;
    allLabel?: string;
    /** Hide the "All …" clear option, for required (non-filter) selects. */
    hideAll?: boolean;
    className?: string;
};

export function SearchableSelect({
    id,
    options,
    value,
    onChange,
    placeholder,
    allLabel,
    hideAll = false,
    className,
}: SearchableSelectProps) {
    const selectedLabel = options.find((option) => option.value === value)?.label;

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    variant="outline"
                    role="combobox"
                    className={cn(
                        'w-full justify-between font-normal sm:w-48',
                        className,
                    )}
                >
                    <span className="truncate">
                        {selectedLabel ?? placeholder}
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
                            {!hideAll && (
                                <CommandItem
                                    value={
                                        allLabel ??
                                        `All ${placeholder.toLowerCase()}`
                                    }
                                    onSelect={() => onChange(null)}
                                >
                                    <Check
                                        className={cn(
                                            'size-4',
                                            !value
                                                ? 'opacity-100'
                                                : 'opacity-0',
                                        )}
                                    />
                                    {allLabel ??
                                        `All ${placeholder.toLowerCase()}`}
                                </CommandItem>
                            )}
                            {options.map((option) => (
                                <CommandItem
                                    key={option.value}
                                    value={option.label}
                                    onSelect={() =>
                                        onChange(
                                            option.value === value
                                                ? null
                                                : option.value,
                                        )
                                    }
                                >
                                    <Check
                                        className={cn(
                                            'size-4',
                                            value === option.value
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
