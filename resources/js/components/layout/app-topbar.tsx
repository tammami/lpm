import { Link, usePage } from '@inertiajs/react';
import { Bell, CalendarRange, Menu } from 'lucide-react';
import { CommandMenu } from '@/components/layout/command-menu';
import { UserMenu } from '@/components/layout/user-menu';
import { Button } from '@/components/ui/button';

export function AppTopbar({ onOpenMobileNav }: { onOpenMobileNav: () => void }) {
    const { activePeriod, unreadNotifications } = usePage().props;
    const hasNotifications = (() => {
        try {
            return route().has('notifications.index');
        } catch {
            return false;
        }
    })();

    return (
        <header className="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-3 border-b border-white/60 bg-white/30 bg-linear-to-r from-white/60 via-white/35 to-white/20 px-4 backdrop-blur-xl md:px-6 lg:px-8">
            <Button variant="ghost" size="icon" className="lg:hidden" onClick={onOpenMobileNav} aria-label="Buka menu">
                <Menu className="size-5" />
            </Button>

            <div className="min-w-0 flex-1">
                <CommandMenu />
            </div>

            {activePeriod && (
                <div className="hidden items-center gap-2 rounded-xl border border-white/80 bg-linear-to-r from-gold-soft to-info-soft px-3 py-1.5 text-xs font-semibold text-gold-foreground shadow-card xl:flex">
                    <CalendarRange className="size-3.5" />
                    <span>{activePeriod.name}</span>
                </div>
            )}

            {hasNotifications && (
                <Button variant="ghost" size="icon" className="relative rounded-xl" asChild>
                    <Link href={route('notifications.index')} aria-label="Notifikasi">
                        <Bell className="size-5" />
                        {unreadNotifications > 0 && (
                            <span className="absolute top-1.5 right-1.5 flex size-4 items-center justify-center rounded-full bg-grad-danger text-[11px] font-bold text-white ring-2 ring-white">
                                {unreadNotifications > 9 ? '9+' : unreadNotifications}
                            </span>
                        )}
                    </Link>
                </Button>
            )}

            <UserMenu />
        </header>
    );
}
