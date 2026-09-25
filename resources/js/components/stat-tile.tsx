import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

interface Props {
    label: string;
    value: ReactNode;
    icon?: LucideIcon;
    hint?: ReactNode;
    tone?: 'primary' | 'gold' | 'info' | 'danger' | 'neutral';
    className?: string;
    children?: ReactNode;
}

const iconTones = {
    primary: 'bg-grad-teal text-white shadow-chip-teal',
    gold: 'bg-grad-orange text-white shadow-chip-orange',
    info: 'bg-grad-violet text-white shadow-chip-violet',
    danger: 'bg-grad-pink text-white shadow-chip-pink',
    neutral: 'bg-grad-sky text-white shadow-chip-sky',
};

/**
 * Tile KPI: label (sentence case), nilai besar, keterangan opsional.
 */
export function StatTile({ label, value, icon: Icon, hint, tone = 'primary', className, children }: Props) {
    return (
        <div className={cn('flex flex-col gap-3 rounded-2xl border border-white/75 surface-glass p-5', className)}>
            <div className="flex items-start justify-between gap-3">
                <span className="text-[13px] font-semibold text-muted-foreground">{label}</span>
                {Icon && (
                    <span className={cn('flex size-9 shrink-0 items-center justify-center rounded-xl', iconTones[tone])}>
                        <Icon className="size-[18px]" />
                    </span>
                )}
            </div>
            <div className="text-[28px] leading-none font-extrabold tracking-tight">{value}</div>
            {hint && <div className="text-xs text-muted-foreground">{hint}</div>}
            {children}
        </div>
    );
}
