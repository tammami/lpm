import { Search, X } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export function SearchInput({
    value,
    onChange,
    placeholder = 'Cari…',
    className,
}: {
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    className?: string;
}) {
    return (
        <div className={cn('relative w-full sm:max-w-xs', className)}>
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} className="h-9 pr-9 pl-9" />
            {value && (
                <button type="button" onClick={() => onChange('')} className="absolute top-1/2 right-1 flex size-7 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground hover:bg-white/70 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none" aria-label="Hapus pencarian">
                    <X className="size-3.5" />
                </button>
            )}
        </div>
    );
}
