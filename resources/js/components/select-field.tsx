import { useFormFieldControl, useInFormField } from '@/components/form-field';
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
    /** Nama aksesibel bila tidak berada di dalam FormField berlabel (mis. filter tabel). */
    label?: string;
}

export function SelectField({ value, onChange, options, placeholder = 'Pilih…', allLabel, className, disabled, id, invalid, label }: Props) {
    const field = useFormFieldControl({ id, 'aria-invalid': invalid });
    const inField = useInFormField();
    // Combobox tidak mengambil nama dari teks di dalamnya; filter tanpa label butuh aria-label.
    const accessibleName = label ?? (inField ? undefined : (allLabel ?? placeholder));
    const current = value === null || value === undefined || value === '' ? (allLabel ? 'all' : '') : String(value);

    return (
        <Select value={current} onValueChange={onChange} disabled={disabled}>
            <SelectTrigger {...field} aria-label={accessibleName} className={cn('h-9 w-full min-w-0 bg-card', className)}>
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
