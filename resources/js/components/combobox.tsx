import { Check, ChevronsUpDown, X } from 'lucide-react';
import { useState } from 'react';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import type { Option } from '@/types';

interface BaseProps {
    options: Option[];
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    className?: string;
    invalid?: boolean;
}

export function Combobox({
    value,
    onChange,
    options,
    placeholder = 'Pilih…',
    searchPlaceholder = 'Cari…',
    emptyText = 'Tidak ditemukan.',
    className,
    invalid,
}: BaseProps & { value: string | number | null | undefined; onChange: (value: string) => void }) {
    const [open, setOpen] = useState(false);
    const selected = options.find((option) => String(option.value) === String(value ?? ''));

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    aria-invalid={invalid}
                    className={cn(
                        'flex h-9 w-full items-center justify-between gap-2 rounded-md border border-input bg-card px-3 text-left text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive',
                        className,
                    )}
                >
                    <span className={cn('truncate', !selected && 'text-muted-foreground')}>{selected?.label ?? placeholder}</span>
                    <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
                </button>
            </PopoverTrigger>
            <PopoverContent className="w-[--radix-popover-trigger-width] min-w-72 p-0" align="start">
                <Command>
                    <CommandInput placeholder={searchPlaceholder} />
                    <CommandList>
                        <CommandEmpty>{emptyText}</CommandEmpty>
                        <CommandGroup>
                            {options.map((option) => (
                                <CommandItem
                                    key={String(option.value)}
                                    value={`${option.label} ${option.value}`}
                                    onSelect={() => {
                                        onChange(String(option.value));
                                        setOpen(false);
                                    }}
                                >
                                    <Check className={cn('size-4', String(option.value) === String(value) ? 'opacity-100' : 'opacity-0')} />
                                    <span className="truncate">{option.label}</span>
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}

export function MultiCombobox({
    values,
    onChange,
    options,
    placeholder = 'Pilih…',
    searchPlaceholder = 'Cari…',
    emptyText = 'Tidak ditemukan.',
    className,
    firstLabel,
}: BaseProps & { values: (string | number)[]; onChange: (values: string[]) => void; firstLabel?: string }) {
    const [open, setOpen] = useState(false);
    const current = values.map(String);
    const selected = options.filter((option) => current.includes(String(option.value)));

    const toggle = (value: string) => onChange(current.includes(value) ? current.filter((v) => v !== value) : [...current, value]);

    return (
        <div className={cn('flex flex-col gap-2', className)}>
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <button type="button" className="flex h-9 w-full items-center justify-between gap-2 rounded-md border border-input bg-card px-3 text-left text-sm shadow-xs">
                        <span className="text-muted-foreground">{selected.length ? `${selected.length} dipilih` : placeholder}</span>
                        <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
                    </button>
                </PopoverTrigger>
                <PopoverContent className="w-[--radix-popover-trigger-width] min-w-72 p-0" align="start">
                    <Command>
                        <CommandInput placeholder={searchPlaceholder} />
                        <CommandList>
                            <CommandEmpty>{emptyText}</CommandEmpty>
                            <CommandGroup>
                                {options.map((option) => {
                                    const active = current.includes(String(option.value));
                                    return (
                                        <CommandItem key={String(option.value)} value={`${option.label} ${option.value}`} onSelect={() => toggle(String(option.value))}>
                                            <span className={cn('flex size-4 items-center justify-center rounded border', active ? 'border-primary bg-primary text-primary-foreground' : 'border-input')}>
                                                {active && <Check className="size-3" />}
                                            </span>
                                            <span className="truncate">{option.label}</span>
                                        </CommandItem>
                                    );
                                })}
                            </CommandGroup>
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>
            {selected.length > 0 && (
                <div className="flex flex-wrap gap-1.5">
                    {selected.map((option, index) => (
                        <span key={String(option.value)} className="inline-flex items-center gap-1 rounded-md bg-secondary px-2 py-1 text-xs font-medium text-secondary-foreground">
                            {index === 0 && firstLabel && <span className="rounded bg-primary px-1 text-[11px] text-primary-foreground">{firstLabel}</span>}
                            {option.label}
                            <button type="button" onClick={() => toggle(String(option.value))} className="opacity-60 hover:opacity-100" aria-label={`Hapus ${option.label}`}>
                                <X className="size-3" />
                            </button>
                        </span>
                    ))}
                </div>
            )}
        </div>
    );
}
