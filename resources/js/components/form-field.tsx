import { type AriaAttributes, createContext, type ReactNode, useCallback, useContext, useEffect, useId, useMemo, useRef } from 'react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

interface FormFieldContextValue {
    /** id kontrol pertama di dalam field; label menunjuk ke id ini. */
    claimId: (consumerKey: string) => string | undefined;
    releaseId: (consumerKey: string) => void;
    describedBy?: string;
    invalid: boolean;
    required: boolean;
}

const FormFieldContext = createContext<FormFieldContextValue | null>(null);

/**
 * Atribut aksesibilitas untuk kontrol di dalam FormField: id (agar <label for> terhubung),
 * aria-describedby (petunjuk/error), aria-invalid, dan aria-required.
 * Hanya kontrol pertama yang menerima id label, sehingga field berisi beberapa kontrol tetap valid.
 */
type ControlProps = Pick<AriaAttributes, 'aria-describedby' | 'aria-invalid' | 'aria-required'> & { id?: string };

export function useFormFieldControl(props: ControlProps = {}) {
    const field = useContext(FormFieldContext);
    const key = useId();
    // Lepaskan id saat kontrol pemiliknya dilepas (mis. pindah tab), agar kontrol berikutnya bisa memakainya.
    useEffect(() => () => field?.releaseId(key), [field, key]);
    if (!field) return {};

    return {
        id: props.id ?? field.claimId(key),
        'aria-describedby': props['aria-describedby'] ?? field.describedBy,
        'aria-invalid': props['aria-invalid'] ?? (field.invalid || undefined),
        'aria-required': props['aria-required'] ?? (field.required || undefined),
    };
}

export function useInFormField() {
    return useContext(FormFieldContext) !== null;
}

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

export function FormField({ label, htmlFor, error, hint, required = false, className, children, action }: Props) {
    const generated = useId();
    const id = htmlFor ?? `field-${generated}`;
    const messageId = `${id}-message`;
    const owner = useRef<string | null>(null);

    const claimId = useCallback(
        (consumerKey: string) => {
            if (owner.current === null) owner.current = consumerKey;
            return owner.current === consumerKey ? id : undefined;
        },
        [id],
    );
    const releaseId = useCallback((consumerKey: string) => {
        if (owner.current === consumerKey) owner.current = null;
    }, []);

    const message = error || hint;
    const value = useMemo<FormFieldContextValue>(
        () => ({ claimId, releaseId, describedBy: message ? messageId : undefined, invalid: !!error, required }),
        [claimId, releaseId, message, messageId, error, required],
    );

    return (
        <div className={cn('flex flex-col gap-1.5', className)}>
            {(label || action) && (
                <div className="flex items-center justify-between gap-2">
                    {label && (
                        <Label htmlFor={id} className="text-[13px] font-semibold text-foreground/85">
                            {label}
                            {required && (
                                <span className="text-danger-foreground" aria-hidden="true">
                                    *
                                </span>
                            )}
                        </Label>
                    )}
                    {action}
                </div>
            )}
            <FormFieldContext.Provider value={value}>{children}</FormFieldContext.Provider>
            {error ? (
                <p id={messageId} role="alert" className="text-xs font-medium text-danger-foreground">
                    {error}
                </p>
            ) : (
                hint && (
                    <p id={messageId} className="text-xs text-muted-foreground">
                        {hint}
                    </p>
                )
            )}
        </div>
    );
}
