import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { TooltipProvider } from '@/components/ui/tooltip';
import { Toaster } from '@/components/ui/sonner';
import { FlashToaster } from '@/components/flash-toaster';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import PortalLayout from '@/layouts/portal-layout';

const appName = import.meta.env.VITE_APP_NAME || 'SIMUTU';

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    layout: (name, page) => {
        const isStudent = (page?.props as { auth?: { user?: { role?: string } | null } } | undefined)?.auth?.user?.role === 'mahasiswa';
        if (name.startsWith('auth/')) return AuthLayout;
        if (name.startsWith('portal/')) return PortalLayout;
        if (isStudent && (name === 'system/notifications' || name.startsWith('profile/'))) return PortalLayout;
        if (name === 'error') return null;
        return AppLayout;
    },
    withApp: (app) => (
        <TooltipProvider delayDuration={200}>
            {app}
            <FlashToaster />
            <Toaster position="top-right" richColors={false} closeButton />
        </TooltipProvider>
    ),
    progress: { color: 'var(--gold)', delay: 150 },
});
