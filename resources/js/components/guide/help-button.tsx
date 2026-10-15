import { LifeBuoy } from 'lucide-react';
import { lazy, Suspense, useEffect, useState } from 'react';
import { cn } from '@/lib/utils';

/** Isi panel (beserta seluruh artikel panduan) baru dimuat saat pertama kali dibuka. */
const HelpPanel = lazy(() => import('./help-panel'));

const SEEN_KEY = 'simutu.guide.seen';

function readSeen() {
    try {
        return localStorage.getItem(SEEN_KEY) === '1';
    } catch {
        return true;
    }
}

function markSeen() {
    try {
        localStorage.setItem(SEEN_KEY, '1');
    } catch {
        /* abaikan */
    }
}

const isTyping = (target: EventTarget | null) =>
    target instanceof HTMLElement && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));

/**
 * Tombol "Panduan" yang selalu terlihat di bilah atas, membuka panel bantuan di samping
 * tanpa meninggalkan halaman. Pintasan: tombol "?".
 */
export function HelpButton({ compact = false }: { compact?: boolean }) {
    const [open, setOpen] = useState(false);
    const [loaded, setLoaded] = useState(false);
    const [seen, setSeen] = useState(readSeen);

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === '?' && !event.metaKey && !event.ctrlKey && !event.altKey && !isTyping(event.target)) {
                event.preventDefault();
                setLoaded(true);
                setOpen(true);
            }
        };
        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);

    const openPanel = () => {
        setLoaded(true);
        setOpen(true);
        if (!seen) {
            markSeen();
            setSeen(true);
        }
    };

    return (
        <>
            <button
                type="button"
                onClick={openPanel}
                aria-label="Buka panduan penggunaan"
                title="Panduan penggunaan (tekan ?)"
                className={cn(
                    'relative inline-flex h-10 shrink-0 items-center gap-2 rounded-xl border border-gold/45 bg-linear-to-r from-gold-soft to-white/80 font-semibold text-gold-foreground shadow-soft transition hover:border-gold hover:from-gold-soft hover:to-gold-soft focus-visible:ring-2 focus-visible:ring-gold/50 focus-visible:outline-none',
                    compact ? 'w-10 justify-center' : 'px-3 text-sm',
                )}
            >
                <LifeBuoy className="size-[18px]" aria-hidden="true" />
                {!compact && <span className="hidden sm:inline">Panduan</span>}
                {!seen && (
                    <span className="absolute -top-1 -right-1 flex size-3" aria-hidden="true">
                        <span className="absolute inline-flex size-full animate-ping rounded-full bg-gold opacity-60 motion-reduce:animate-none" />
                        <span className="relative inline-flex size-3 rounded-full bg-gold ring-2 ring-white" />
                    </span>
                )}
            </button>
            {loaded && (
                <Suspense fallback={null}>
                    <HelpPanel open={open} onOpenChange={setOpen} />
                </Suspense>
            )}
        </>
    );
}

