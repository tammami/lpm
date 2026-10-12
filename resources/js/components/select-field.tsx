import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn, groupOptions } from '@/lib/utils';
import type { Option } from '@/types';

interface Props {
    value: string | number | null | undefined;
    onChange: (value: string) => void;
    /** Opsi dengan `group` ditampilkan berkelompok di bawah judulnya. */
    options: (Option & { group?: string })[];
    placeholder?: string;
    /** Tambahkan opsi "semua" (nilai "all") untuk filter. */
    allLabel?: string;
    className?: string;
    disabled?: boolean;
    id?: string;
    invalid?: boolean;
}

export function SelectField({ value, onChange, options, placeholder = 'Pilih…', allLabel, className, disabled, id, invalid }: Props) {
    const current = value === null || value === undefined || value === '' ? (allLabel ? 'all' : '') : String(value);

    return (
        <Select value={current} onValueChange={onChange} disabled={disabled}>
            <SelectTrigger id={id} className={cn('h-9 w-full bg-card', className)} aria-invalid={invalid}>
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                {allLabel && <SelectItem value="all">{allLabel}</SelectItem>}
                {groupOptions(options).map(({ group, items }) => {
                    const rendered = items.map((option) => (
                        <SelectItem key={String(option.value)} value={String(option.value)}>
                            {option.label}
                        </SelectItem>
                    ));

                    return group ? (
                        <SelectGroup key={group}>
                            <SelectLabel>{group}</SelectLabel>
                            {rendered}
                        </SelectGroup>
                    ) : (
                        rendered
                    );
                })}
            </SelectContent>
        </Select>
    );
}
