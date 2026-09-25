import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

const toasters = {
    success: toast.success,
    error: toast.error,
    info: toast.info,
    warning: toast.warning,
};

/**
 * Tampilkan flash data `toast` dari server sebagai notifikasi.
 */
export function FlashToaster() {
    useEffect(() => {
        return router.on('flash', (event) => {
            const data = event.detail.flash.toast;
            if (data) toasters[data.type](data.message);
        });
    }, []);

    return null;
}
