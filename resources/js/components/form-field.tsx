import type { ReactNode } from 'react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

interface Props {
    label?: ReactNode;
    htmlFor?: string;
    error?: string;
    hint?: ReactNode;
    required?: boolean;
    className?: string;
    children: ReactNode;
    action?: ReactNode;
}

export function FormField({ label, htmlFor, error, hint, required, className, children, action }: Props) {
    return (
        <div className={cn('flex flex-col gap-1.5', className)}>
            {(label || action) && (
                <div className="flex items-center justify-between gap-2">
                    {label && (
                        <Label htmlFor={htmlFor} className="text-[13px] font-semibold text-foreground/85">
                            {label}
                            {required && <span className="text-destructive">*</span>}
                        </Label>
                    )}
                    {action}
                </div>
            )}
            {children}
            {error ? (
                <p className="text-xs font-medium text-destructive">{error}</p>
            ) : (
                hint && <p className="text-xs text-muted-foreground">{hint}</p>
            )}
        </div>
    );
}
