import { usePage } from '@inertiajs/react';

/**
 * Cek permission pengguna aktif. Superadmin memiliki wildcard "*".
 */
export function useCan() {
    const { auth } = usePage().props;
    const permissions = auth.user?.permissions ?? [];

    return (...required: string[]) =>
        permissions.includes('*') || required.some((permission) => permissions.includes(permission));
}

export function useAuthUser() {
    return usePage().props.auth.user;
}
