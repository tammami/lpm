import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * Indikator alur kerja horizontal (tahap selesai, aktif, berikutnya).
 */
export function Stepper({ steps, current, tone = 'primary' }: { steps: { value: string; label: string }[]; current: string; tone?: 'primary' | 'danger' }) {
    const index = steps.findIndex((s) => s.value === current);

    return (
        <ol className="flex w-full items-start overflow-x-auto pb-1">
            {steps.map((step, i) => {
                const done = i < index;
                const active = i === index;
                return (
                    <li key={step.value} className="flex min-w-24 flex-1 flex-col items-center gap-1.5 text-center">
                        <div className="flex w-full items-center">
                            <span className={cn('h-0.5 flex-1', i === 0 ? 'bg-transparent' : done || active ? 'bg-grad-good' : 'bg-white/70')} />
                            <span
                                className={cn(
                                    'flex size-8 shrink-0 items-center justify-center rounded-full border-2 text-xs font-bold',
                                    done && 'border-transparent bg-grad-primary text-primary-foreground shadow-primary',
                                    active && (tone === 'danger' ? 'border-destructive bg-danger-soft text-danger-foreground' : 'border-primary bg-white text-primary shadow-card ring-4 ring-primary/15'),
                                    !done && !active && 'border-white/85 bg-white/60 text-muted-foreground',
                                )}
                            >
                                {done ? <Check className="size-4" /> : i + 1}
                            </span>
                            <span className={cn('h-0.5 flex-1', i === steps.length - 1 ? 'bg-transparent' : done ? 'bg-grad-good' : 'bg-white/70')} />
                        </div>
                        <span className={cn('px-1 text-[11px] leading-tight font-semibold', active ? 'text-foreground' : 'text-muted-foreground')}>{step.label}</span>
                    </li>
                );
            })}
        </ol>
    );
}
