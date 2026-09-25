import { router, usePage } from '@inertiajs/react';
import { CornerDownLeft, Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { CommandDialog, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { useCan } from '@/hooks/use-can';
import { filterNavigation } from '@/lib/navigation';

/**
 * Palet perintah (⌘K / Ctrl+K) untuk berpindah menu dengan cepat.
 */
export function CommandMenu() {
    const [open, setOpen] = useState(false);
    const can = useCan();
    const user = usePage().props.auth.user;
    const groups = useMemo(() => filterNavigation(can, user ?? {}), [can, user]);

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                setOpen((value) => !value);
            }
        };
        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);

    const go = (name: string) => {
        setOpen(false);
        router.visit(route(name));
    };

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="group flex h-10 w-full max-w-sm items-center gap-2.5 rounded-xl border border-white/80 bg-white/45 bg-linear-to-r from-white/75 to-white/35 px-3.5 text-sm text-muted-foreground shadow-soft backdrop-blur-md transition hover:border-primary/30 hover:from-white"
            >
                <Search className="size-4" />
                <span className="flex-1 text-left">Cari menu…</span>
                <kbd className="hidden rounded-md border border-white/80 bg-white/70 px-1.5 py-0.5 font-sans text-[11px] font-semibold text-muted-foreground sm:inline-block">
                    Ctrl K
                </kbd>
            </button>
            <CommandDialog open={open} onOpenChange={setOpen} title="Cari menu" description="Ketik nama menu untuk berpindah halaman">
                <CommandInput placeholder="Ketik nama menu, mis. “dosen” atau “temuan”…" />
                <CommandList>
                    <CommandEmpty>Menu tidak ditemukan.</CommandEmpty>
                    {groups.map((group) => (
                        <CommandGroup key={group.label} heading={group.label}>
                            {group.items.flatMap((item) => {
                                const Icon = item.icon;
                                const leaves = item.children
                                    ? item.children.map((child) => ({ title: child.title, route: child.route, parent: item.title }))
                                    : [{ title: item.title, route: item.route!, parent: undefined }];
                                return leaves.map((leaf) => (
                                    <CommandItem key={leaf.route} value={`${leaf.parent ?? ''} ${leaf.title}`} onSelect={() => go(leaf.route)}>
                                        <Icon className="text-muted-foreground" />
                                        <span>{leaf.title}</span>
                                        {leaf.parent && <span className="text-xs text-muted-foreground">· {leaf.parent}</span>}
                                        <CornerDownLeft className="ml-auto opacity-0 group-data-[selected=true]:opacity-60" />
                                    </CommandItem>
                                ));
                            })}
                        </CommandGroup>
                    ))}
                </CommandList>
            </CommandDialog>
        </>
    );
}
