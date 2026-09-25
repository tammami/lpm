import { type ReactNode, useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { AppSidebar } from '@/components/layout/app-sidebar';
import { AppTopbar } from '@/components/layout/app-topbar';
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/components/ui/sheet';
import { cn } from '@/lib/utils';

const STORAGE_KEY = 'simutu.sidebar.collapsed';

function readCollapsed() {
    try {
        return localStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}

export default function AppLayout({ children }: { children: ReactNode }) {
    const [collapsed, setCollapsed] = useState(readCollapsed);
    const [mobileOpen, setMobileOpen] = useState(false);

    useEffect(() => router.on('navigate', () => setMobileOpen(false)), []);

    const toggleCollapsed = () => {
        setCollapsed((value) => {
            try {
                localStorage.setItem(STORAGE_KEY, value ? '0' : '1');
            } catch {
                /* abaikan */
            }
            return !value;
        });
    };

    return (
        <div className="flex min-h-svh">
            <aside
                className={cn(
                    'sticky top-0 hidden h-svh shrink-0 transition-[width] duration-200 lg:block',
                    collapsed ? 'w-[76px]' : 'w-[272px]',
                )}
            >
                <AppSidebar collapsed={collapsed} onToggleCollapse={toggleCollapsed} />
            </aside>

            <Sheet open={mobileOpen} onOpenChange={setMobileOpen}>
                <SheetContent side="left" className="w-[280px] border-none p-0">
                    <SheetTitle className="sr-only">Menu navigasi</SheetTitle>
                    <SheetDescription className="sr-only">Navigasi utama aplikasi</SheetDescription>
                    <AppSidebar onNavigate={() => setMobileOpen(false)} />
                </SheetContent>
            </Sheet>

            <div className="flex min-w-0 flex-1 flex-col">
                <AppTopbar onOpenMobileNav={() => setMobileOpen(true)} />
                <main className="flex-1 px-4 py-6 md:px-6 lg:px-8 lg:py-8">
                    <div className="mx-auto w-full max-w-[1400px]">{children}</div>
                </main>
            </div>
        </div>
    );
}
