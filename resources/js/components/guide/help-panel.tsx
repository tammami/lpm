import { Link } from '@inertiajs/react';
import { ArrowLeft, BookOpenText, LifeBuoy, Search, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/components/ui/sheet';
import { articlesForCurrentPage, findArticle, popularSlugs, searchGuide } from '@/lib/guide';
import { cn } from '@/lib/utils';
import { GuideArticleLink, GuideArticleView } from './guide-article';
import { guideUrl, useGuideArticles } from './use-guide';

export default function HelpPanel({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
    const { articles } = useGuideArticles();
    const [query, setQuery] = useState('');
    const [slug, setSlug] = useState<string | null>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const scrollRef = useRef<HTMLDivElement>(null);

    const visibleSlugs = useMemo(() => new Set(articles.map((article) => article.slug)), [articles]);
    const results = useMemo(() => (query.trim() ? searchGuide(articles, query, 12).map((result) => result.article) : []), [articles, query]);
    // Dihitung saat panel dibuka agar mengikuti halaman yang sedang aktif.
    const contextual = useMemo(() => (open ? articlesForCurrentPage(articles).slice(0, 6) : []), [open, articles]);
    const popular = useMemo(
        () => popularSlugs.filter((item) => visibleSlugs.has(item) && !contextual.some((article) => article.slug === item)).map(findArticle).slice(0, 6),
        [visibleSlugs, contextual],
    );
    const article = slug ? findArticle(slug) : undefined;

    useEffect(() => {
        if (!open) {
            setSlug(null);
            setQuery('');
        }
    }, [open]);

    const openArticle = (next: string) => {
        setSlug(next);
        scrollRef.current?.querySelector('[data-radix-scroll-area-viewport]')?.scrollTo({ top: 0 });
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="right"
                className="w-full gap-0 p-0 sm:max-w-md"
                onOpenAutoFocus={(event) => {
                    event.preventDefault();
                    inputRef.current?.focus();
                }}
            >
                <div className="border-b border-white/70 pt-5 pr-14 pb-4 pl-5">
                    <SheetTitle className="flex items-center gap-2 text-lg font-extrabold">
                        <span className="flex size-8 items-center justify-center rounded-lg bg-gold-soft text-gold-foreground">
                            <LifeBuoy className="size-4" />
                        </span>
                        Panduan Penggunaan
                    </SheetTitle>
                    <SheetDescription className="mt-1 text-xs">Ketik apa yang ingin Anda lakukan. Contoh: “tambah pengguna”, “lupa kata sandi”, “isi monev”.</SheetDescription>

                    {article ? (
                        <Button variant="ghost" size="sm" className="mt-3 -ml-2" onClick={() => setSlug(null)}>
                            <ArrowLeft /> {query ? 'Kembali ke hasil pencarian' : 'Kembali'}
                        </Button>
                    ) : (
                        <div className="relative mt-3">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                            <Input
                                ref={inputRef}
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                placeholder="Cari panduan…"
                                aria-label="Cari panduan"
                                className="h-11 bg-white pr-9 pl-9 text-base"
                            />
                            {query && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        setQuery('');
                                        inputRef.current?.focus();
                                    }}
                                    className="absolute top-1/2 right-1.5 flex size-8 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground hover:bg-secondary hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none"
                                    aria-label="Hapus pencarian"
                                >
                                    <X className="size-4" />
                                </button>
                            )}
                        </div>
                    )}
                </div>

                <ScrollArea ref={scrollRef} className="min-h-0 flex-1">
                    <div className="px-5 py-5">
                        {article ? (
                            <GuideArticleView article={article} visibleSlugs={visibleSlugs} onOpenArticle={openArticle} onNavigate={() => onOpenChange(false)} compact />
                        ) : query.trim() ? (
                            results.length > 0 ? (
                                <div className="flex flex-col gap-2" role="list" aria-label="Hasil pencarian panduan">
                                    <p className="mb-1 text-xs font-semibold text-muted-foreground" aria-live="polite">
                                        {results.length} panduan cocok
                                    </p>
                                    {results.map((item) => (
                                        <GuideArticleLink key={item.slug} article={item} onClick={() => openArticle(item.slug)} />
                                    ))}
                                </div>
                            ) : (
                                <div className="rounded-2xl border border-dashed p-5 text-center text-sm text-muted-foreground" aria-live="polite">
                                    <p className="font-semibold text-foreground">Panduan tidak ditemukan</p>
                                    <p className="mt-1">Coba kata lain yang lebih umum, mis. “dosen”, “kelas”, “laporan”, atau “kata sandi”.</p>
                                </div>
                            )
                        ) : (
                            <div className="flex flex-col gap-6">
                                {contextual.length > 0 && (
                                    <section>
                                        <h3 className="mb-2 text-xs font-bold text-primary">Untuk halaman ini</h3>
                                        <div className="flex flex-col gap-2">
                                            {contextual.map((item) => (
                                                <GuideArticleLink key={item.slug} article={item} onClick={() => openArticle(item.slug)} showCategory={false} />
                                            ))}
                                        </div>
                                    </section>
                                )}
                                {popular.length > 0 && (
                                    <section>
                                        <h3 className="mb-2 text-xs font-bold text-muted-foreground">Sering ditanyakan</h3>
                                        <div className="flex flex-col gap-2">
                                            {popular.map((item) => item && <GuideArticleLink key={item.slug} article={item} onClick={() => openArticle(item.slug)} />)}
                                        </div>
                                    </section>
                                )}
                            </div>
                        )}
                    </div>
                </ScrollArea>

                <div className="border-t border-white/70 px-5 py-3">
                    <Button variant="outline" className="w-full" asChild>
                        <Link href={guideUrl(article ? { artikel: article.slug } : { q: query.trim() || undefined })} onClick={() => onOpenChange(false)}>
                            <BookOpenText /> Buka Pusat Panduan lengkap
                        </Link>
                    </Button>
                </div>
            </SheetContent>
        </Sheet>
    );
}
