import type { ReactNode } from 'react';
import { Switch } from '@/components/ui/switch';

export function SwitchField({ checked, onChange, label, description, id }: { checked: boolean; onChange: (value: boolean) => void; label: ReactNode; description?: ReactNode; id?: string }) {
    return (
        <label htmlFor={id} className="flex cursor-pointer items-start justify-between gap-4 rounded-xl border bg-muted/30 px-4 py-3">
            <div>
                <div className="text-[13px] font-semibold">{label}</div>
                {description && <div className="mt-0.5 text-xs text-muted-foreground">{description}</div>}
            </div>
            <Switch id={id} checked={checked} onCheckedChange={onChange} />
        </label>
    );
}
