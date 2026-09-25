import { LoaderCircle } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: ReactNode;
    onSubmit: () => void;
    processing?: boolean;
    submitLabel?: string;
    children: ReactNode;
    size?: 'md' | 'lg' | 'xl';
}

const sizes = { md: 'sm:max-w-lg', lg: 'sm:max-w-2xl', xl: 'sm:max-w-4xl' };

export function FormDialog({ open, onOpenChange, title, description, onSubmit, processing, submitLabel = 'Simpan', children, size = 'md' }: Props) {
    const submit = (event: FormEvent) => {
        event.preventDefault();
        onSubmit();
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className={cn('max-h-[92svh] gap-0 overflow-hidden p-0', sizes[size])}>
                <form onSubmit={submit} className="flex max-h-[92svh] flex-col">
                    <DialogHeader className="border-b px-6 pt-6 pb-4">
                        <DialogTitle className="text-lg font-bold">{title}</DialogTitle>
                        {description ? <DialogDescription>{description}</DialogDescription> : <DialogDescription className="sr-only">{title}</DialogDescription>}
                    </DialogHeader>
                    <div className="min-h-0 flex-1 overflow-y-auto px-6 py-5">{children}</div>
                    <DialogFooter className="border-t bg-muted/40 px-6 py-4">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={processing}>
                            Batal
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <LoaderCircle className="animate-spin" />}
                            {submitLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
