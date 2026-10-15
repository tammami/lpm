import { Link, usePage } from '@inertiajs/react';
import { LogIn } from 'lucide-react';
import type { ReactNode } from 'react';
import { LogoMark } from '@/components/brand/app-logo';
import { Button } from '@/components/ui/button';

/**
 * Tata letak halaman publik (belum login), dipakai Pusat Panduan untuk tamu.
 */
export default function PublicLayout({ children }: { children: ReactNode }) {
    const { app } = usePage().props;

    return (
        <div className="min-h-svh">
            <header className="sticky top-0 z-30 border-b border-white/60 bg-white/35 bg-linear-to-r from-white/60 to-white/25 backdrop-blur-xl">
                <div className="mx-auto flex h-16 max-w-6xl items-center gap-3 px-4 md:px-6">
                    <Link href={route('login')} className="flex min-w-0 flex-1 items-center gap-2.5">
                        <LogoMark className="size-9" label={`Lambang ${app.institution}`} />
                        <div className="min-w-0 leading-tight">
                            <div className="text-sm font-extrabold">SIMUTU</div>
                            <div className="truncate text-[11px] text-muted-foreground">LPM {app.institution_short}</div>
                        </div>
                    </Link>
                    <Button asChild>
                        <Link href={route('login')}>
                            <LogIn /> Masuk
                        </Link>
                    </Button>
                </div>
            </header>
            <main className="mx-auto w-full max-w-6xl px-4 py-6 md:px-6 lg:py-8">{children}</main>
        </div>
    );
}
