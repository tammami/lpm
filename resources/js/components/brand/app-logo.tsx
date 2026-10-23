import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';

/**
 * Lambang IAIA NU Lombok Timur.
 * - `mark`: emblem tanpa lingkaran teks untuk ukuran kecil (sidebar, favicon, portal) — teks segel tak terbaca < 48px.
 * - `seal`: segel lengkap untuk ukuran besar (login, halaman institusi, kop laporan).
 * Logo yang diunggah di Master → Institusi selalu diutamakan.
 */
export function LogoMark({ className, variant = 'mark', label }: { className?: string; variant?: 'mark' | 'seal'; label?: string }) {
    const { app } = usePage().props;
    const src = app.logo_url ?? (variant === 'seal' ? '/images/logo-iaia.webp' : '/images/logo-mark.webp');

    return (
        <img
            src={src}
            alt={label ?? ''}
            aria-hidden={label ? undefined : true}
            width={variant === 'seal' ? 256 : 128}
            height={variant === 'seal' ? 256 : 128}
            decoding="async"
            draggable={false}
            className={cn('size-9 shrink-0 object-contain select-none', className)}
        />
    );
}

/** Jargon LPM, tampil tepat di bawah tulisan SIMUTU. */
export const APP_TAGLINE = 'LPM AKRAB, AL-MANAN UNGGUL';

export function AppTagline({ className, inverted = false }: { className?: string; inverted?: boolean }) {
    return <div className={cn('truncate text-[10px] font-bold tracking-wide', inverted ? 'text-hero-accent' : 'text-primary', className)}>{APP_TAGLINE}</div>;
}

export function AppLogo({ subtitle, className, inverted = false, mark = true }: { subtitle?: string; className?: string; inverted?: boolean; mark?: boolean }) {
    return (
        <div className={cn('flex items-center gap-3', className)}>
            {mark && <LogoMark className="size-10" />}
            <div className="min-w-0 leading-tight">
                <div className={cn('text-[16px] font-extrabold tracking-tight', inverted ? 'text-white' : 'text-foreground')}>SIMUTU</div>
                <AppTagline inverted={inverted} />
                {subtitle && <div className={cn('truncate text-[11px] font-medium', inverted ? 'text-white/85' : 'text-muted-foreground')}>{subtitle}</div>}
            </div>
        </div>
    );
}
