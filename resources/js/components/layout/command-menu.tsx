import { router, usePage } from '@inertiajs/react';
import { BookOpenText, CornerDownLeft, Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { CommandDialog, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { useCan } from '@/hooks/use-can';
import type { GuideArticle } from '@/lib/guide/types';
import { guideUrl } from '@/lib/guide/url';
import { filterNavigation } from '@/lib/navigation';

/**
 * Palet perintah (⌘K / Ctrl+K) untuk berpindah menu dengan cepat sekaligus mencari panduan.
 */
export function CommandMenu() {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const can = useCan();
    const user = usePage().props.auth.user;
    const groups = useMemo(() => filterNavigation(can, user ?? {}), [can, user]);
    const guide = useLazyGuide(open, can, user);
    const guideResults = useMemo(() => (guide && search.trim() ? guide.search(search) : []), [guide, search]);

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

    const openGuide = (slug: string) => {
        setOpen(false);
        router.visit(guideUrl({ artikel: slug }));
    };

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="group flex h-10 w-full max-w-sm items-center gap-2.5 rounded-xl border border-white/80 bg-white/45 bg-linear-to-r from-white/75 to-white/35 px-3.5 text-sm text-muted-foreground shadow-soft backdrop-blur-md transition hover:border-primary/30 hover:from-white"
            >
                <Search className="size-4" />
                <span className="flex-1 truncate text-left">Cari menu atau panduan…</span>
                <kbd className="hidden rounded-md border border-white/80 bg-white/70 px-1.5 py-0.5 font-sans text-[11px] font-semibold text-muted-foreground sm:inline-block">
                    Ctrl K
                </kbd>
            </button>
            <CommandDialog
                open={open}
                onOpenChange={(value) => {
                    setOpen(value);
                    if (!value) setSearch('');
                }}
                title="Cari menu atau panduan"
                description="Ketik nama menu untuk berpindah halaman, atau pertanyaan untuk membuka panduan"
            >
                <CommandInput value={search} onValueChange={setSearch} placeholder="Ketik menu atau pertanyaan, mis. “dosen” atau “tambah pengguna”…" />
                <CommandList>
                    <CommandEmpty>Tidak ditemukan. Coba kata lain atau buka Pusat Panduan.</CommandEmpty>
                    {guideResults.length > 0 && (
                        <CommandGroup heading="Panduan">
                            {guideResults.map((article) => (
                                <CommandItem key={article.slug} value={`panduan ${article.slug}`} keywords={[search, article.title]} onSelect={() => openGuide(article.slug)}>
                                    <BookOpenText className="text-gold-foreground" />
                                    <span className="truncate">{article.title}</span>
                                    <CornerDownLeft className="ml-auto opacity-0 group-data-[selected=true]:opacity-60" />
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    )}
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

type GuideSearcher = { search: (query: string) => GuideArticle[] };

/** Isi panduan dimuat saat palet pertama kali dibuka agar bundle utama tetap ringan. */
function useLazyGuide(open: boolean, can: (...permissions: string[]) => boolean, user: { is_lecturer?: boolean; is_student?: boolean } | null): GuideSearcher | null {
    const [guide, setGuide] = useState<GuideSearcher | null>(null);
    // `can` dibuat ulang setiap render; simpan di ref agar pemuatan tidak dibatalkan saat pengguna mengetik.
    const viewerRef = useRef({ can, user });
    viewerRef.current = { can, user };

    useEffect(() => {
        if (!open || guide) return;
        let cancelled = false;
        import('@/lib/guide').then(({ guideArticles, isVisibleTo, searchGuide }) => {
            if (cancelled) return;
            const { can, user } = viewerRef.current;
            const viewer = { can, isGuest: !user, isLecturer: !!user?.is_lecturer, isStudent: !!user?.is_student };
            const articles = guideArticles.filter((article) => isVisibleTo(article, viewer));
            setGuide({ search: (query) => searchGuide(articles, query, 5).map((result) => result.article) });
        });
        return () => {
            cancelled = true;
        };
    }, [open, guide]);

    return guide;
}
