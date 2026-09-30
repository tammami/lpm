import { router } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { type ReactNode, useState } from 'react';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';

interface Props {
    trigger: ReactNode;
    title: string;
    description?: ReactNode;
    confirmLabel?: string;
    /** URL tujuan; jika diisi, dialog mengirim request dengan method yang ditentukan. */
    href?: string;
    method?: 'delete' | 'post' | 'put' | 'patch';
    data?: Record<string, unknown>;
    destructive?: boolean;
    onConfirm?: () => void;
}

export function ConfirmDialog({ trigger, title, description, confirmLabel = 'Ya, lanjutkan', href, method = 'delete', data, destructive = true, onConfirm }: Props) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const confirm = () => {
        if (onConfirm) {
            onConfirm();
            setOpen(false);
            return;
        }
        if (!href) return;
        router.visit(href, {
            method,
            data: data as never,
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setOpen(false);
            },
        });
    };

    return (
        <AlertDialog open={open} onOpenChange={setOpen}>
            <AlertDialogTrigger asChild>{trigger}</AlertDialogTrigger>
            <AlertDialogContent
                onCloseAutoFocus={() => {
                    // Dialog yang dibuka dari menu (mis. "Aksi › Hapus") menahan menu tetap terbuka; tutup juga
                    // menunya setelah dialog hilang agar halaman tidak tertahan satu klik.
                    if (document.querySelector('[role="menu"]')) document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
                }}
            >
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    {description && <AlertDialogDescription>{description}</AlertDialogDescription>}
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={processing}>Batal</AlertDialogCancel>
                    <Button variant={destructive ? 'destructive' : 'default'} onClick={confirm} disabled={processing}>
                        {processing && <LoaderCircle className="animate-spin" />}
                        {confirmLabel}
                    </Button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
