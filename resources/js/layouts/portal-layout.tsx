import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Bell } from 'lucide-react';
import type { ReactNode } from 'react';
import { LogoMark } from '@/components/brand/app-logo';
import { UserMenu } from '@/components/layout/user-menu';

/**
 * Tata letak mobile-first untuk responden (mahasiswa/dosen) saat mengisi Monev.
 */
export default function PortalLayout({ children }: { children: ReactNode }) {
    const { auth, app, unreadNotifications } = usePage().props;
    const isStudent = auth.user?.role === 'mahasiswa';

    return (
        <div className="min-h-svh">
            <header className="sticky top-0 z-30 border-b border-white/60 bg-white/35 bg-linear-to-r from-white/60 to-white/25 backdrop-blur-xl">
                <div className="mx-auto flex h-16 max-w-3xl items-center gap-3 px-4">
                    {!isStudent && (
                        <Link href={route('home')} className="-ml-1 rounded-lg p-2 text-muted-foreground hover:bg-white/60" aria-label="Kembali ke dashboard">
                            <ArrowLeft className="size-5" />
                        </Link>
                    )}
                    <Link href={route('portal.home')} className="flex min-w-0 flex-1 items-center gap-2.5">
                        <LogoMark className="size-9" />
                        <div className="min-w-0 leading-tight">
                            <div className="text-sm font-extrabold">SIMUTU</div>
                            <div className="truncate text-[11px] text-muted-foreground">Portal Responden · {app.institution_short}</div>
                        </div>
                    </Link>
                    <Link href={route('notifications.index')} className="relative rounded-xl p-2 text-muted-foreground hover:bg-white/60" aria-label="Notifikasi">
                        <Bell className="size-5" />
                        {unreadNotifications > 0 && (
                            <span className="absolute top-1 right-1 flex size-4 items-center justify-center rounded-full bg-grad-danger text-[11px] font-bold text-white ring-2 ring-white">
                                {unreadNotifications > 9 ? '9+' : unreadNotifications}
                            </span>
                        )}
                    </Link>
                    <UserMenu compact />
                </div>
            </header>
            <main className="mx-auto w-full max-w-3xl px-4 pt-5 pb-16">{children}</main>
        </div>
    );
}
