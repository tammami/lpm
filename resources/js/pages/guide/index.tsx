import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, ChevronRight, LayoutGrid, LifeBuoy, LogIn, Search, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { GuideArticleLink, GuideArticleView } from '@/components/guide/guide-article';
import { guideUrl, useGuideArticles } from '@/components/guide/use-guide';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { findArticle, type GuideArticle, guideCategories, popularSlugs, searchGuide } from '@/lib/guide';
import { cn } from '@/lib/utils';

interface Props {
    filters: { q: string; kategori: string; artikel: string; semua: boolean };
}

export default function GuideIndex({ filters }: Props) {
    const user = usePage().props.auth.user;
    const { articles, canShowAll, viewer } = useGuideArticles(filters.semua);
    const isAdmin = viewer.can('master.view', 'users.manage', 'surveys.manage');
    const [query, setQuery] = useState(filters.q);
    const inputRef = useRef<HTMLInputElement>(null);

    const visibleSlugs = useMemo(() => new Set(articles.map((article) => article.slug)), [articles]);
    const article = filters.artikel && visibleSlugs.has(filters.artikel) ? findArticle(filters.artikel) : undefined;
    const results = useMemo(() => (query.trim() ? searchGuide(articles, query).map((result) => result.article) : []), [articles, query]);
    const categories = useMemo(
        () =>
            guideCategories
                .map((category) => ({ ...category, articles: articles.filter((item) => item.category === category.key) }))
                .filter((category) => category.articles.length > 0),
        [articles],
    );
    const activeCategory = categories.find((category) => category.key === filters.kategori);
    const popular = popularSlugs.filter((slug) => visibleSlugs.has(slug)).map(findArticle).filter((item): item is GuideArticle => !!item);

    const visit = (params: Partial<Props['filters']>, options: { replace?: boolean } = {}) =>
        router.visit(guideUrl({ semua: filters.semua, ...params }), { preserveState: true, preserveScroll: !!options.replace, replace: options.replace });

    // Simpan kata kunci di URL (tanpa menambah riwayat) agar hasil bisa dibagikan.
    // Dilewati bila URL sudah sesuai, mis. setelah memilih kategori atau membuka panduan.
    useEffect(() => {
        if (query.trim() === filters.q.trim()) return;
        const timer = setTimeout(() => visit({ q: query.trim() || undefined }, { replace: true }), 400);
        return () => clearTimeout(timer);
    }, [query]);

    const openArticle = (slug: string) => {
        visit({ artikel: slug, q: query.trim() || undefined });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const content = (() => {
        if (article) {
            return (
                <div className="rounded-3xl border border-white/80 surface-glass p-5 sm:p-8">
                    <Button variant="ghost" size="sm" className="mb-4 -ml-2" onClick={() => visit({ q: query.trim() || undefined, kategori: query.trim() ? undefined : article.category })}>
                        <ArrowLeft /> {query.trim() ? 'Kembali ke hasil pencarian' : 'Kembali ke daftar'}
                    </Button>
                    <GuideArticleView article={article} visibleSlugs={visibleSlugs} onOpenArticle={openArticle} />
                </div>
            );
        }

        if (query.trim()) {
            return (
                <section aria-live="polite">
                    <h2 className="mb-3 text-sm font-semibold text-muted-foreground">
                        {results.length ? `${results.length} panduan cocok dengan “${query.trim()}”` : `Tidak ada panduan untuk “${query.trim()}”`}
                    </h2>
                    {results.length > 0 ? (
                        <div className="flex flex-col gap-2">
                            {results.map((item) => (
                                <GuideArticleLink key={item.slug} article={item} onClick={() => openArticle(item.slug)} />
                            ))}
                        </div>
                    ) : (
                        <div className="rounded-2xl border border-dashed bg-white/40 p-6 text-sm text-muted-foreground">
                            <p>Coba kata yang lebih umum atau sinonimnya, mis. “pengguna” untuk user, “kata sandi” untuk password.</p>
                            <p className="mt-2">Masih belum ketemu? Hubungi admin LPM atau admin program studi Anda.</p>
                        </div>
                    )}
                </section>
            );
        }

        if (activeCategory) {
            const Icon = activeCategory.icon;
            return (
                <section>
                    <div className="mb-4 flex items-center gap-3">
                        <span className="flex size-10 items-center justify-center rounded-xl bg-secondary text-secondary-foreground">
                            <Icon className="size-5" />
                        </span>
                        <div>
                            <h2 className="text-lg font-extrabold">{activeCategory.title}</h2>
                            <p className="text-sm text-muted-foreground">{activeCategory.description}</p>
                        </div>
                    </div>
                    <div className="flex flex-col gap-2">
                        {activeCategory.articles.map((item) => (
                            <GuideArticleLink key={item.slug} article={item} onClick={() => openArticle(item.slug)} showCategory={false} />
                        ))}
                    </div>
                </section>
            );
        }

        return (
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                {categories.map((category) => {
                    const Icon = category.icon;
                    return (
                        <section key={category.key} className="flex flex-col rounded-2xl border border-white/80 surface-glass p-4">
                            <button type="button" onClick={() => visit({ kategori: category.key })} className="group mb-3 flex items-center gap-3 text-left">
                                <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-secondary text-secondary-foreground">
                                    <Icon className="size-5" />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block font-bold group-hover:text-primary">{category.title}</span>
                                    <span className="block text-xs text-muted-foreground">{category.description}</span>
                                </span>
                            </button>
                            <ul className="flex flex-1 flex-col gap-0.5">
                                {category.articles.slice(0, 4).map((item) => (
                                    <li key={item.slug}>
                                        <button
                                            type="button"
                                            onClick={() => openArticle(item.slug)}
                                            className="flex min-h-9 w-full items-start gap-1.5 rounded-lg px-2 py-2 text-left text-sm text-foreground/85 hover:bg-white/70 hover:text-primary"
                                        >
                                            <ChevronRight className="mt-0.5 size-3.5 shrink-0 text-muted-foreground" />
                                            {item.title}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                            {category.articles.length > 4 && (
                                <button type="button" onClick={() => visit({ kategori: category.key })} className="mt-1 self-start rounded-md px-2 py-1.5 text-xs font-semibold text-primary hover:underline">
                                    Lihat semua ({category.articles.length})
                                </button>
                            )}
                        </section>
                    );
                })}
            </div>
        );
    })();

    return (
        <>
            <Head title={article ? `${article.title} · Panduan` : 'Pusat Panduan'} />

            <section className="relative mb-6 overflow-hidden rounded-3xl bg-hero px-5 py-7 text-white sm:px-8 sm:py-9">
                <div className="bg-pattern-star absolute inset-0 opacity-50" aria-hidden="true" />
                <div className="relative max-w-2xl">
                    <div className="mb-2 inline-flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-xs font-semibold">
                        <LifeBuoy className="size-3.5" aria-hidden="true" /> Pusat Panduan SIMUTU
                    </div>
                    <h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl">Mau melakukan apa hari ini?</h1>
                    <p className="mt-1.5 text-sm text-white/85">
                        Ketik kata kunci atau pertanyaan Anda. Panduan menunjukkan letak menu dan langkah-langkahnya{user ? ' sesuai peran Anda' : ''}.
                    </p>
                    <div className="relative mt-5">
                        <Search className="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                        <Input
                            ref={inputRef}
                            autoFocus={!filters.artikel}
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder={!user ? 'Mis. lupa kata sandi, cara masuk…' : isAdmin ? 'Mis. tambah pengguna, buka kelas, impor data…' : 'Mis. cara isi monev, ganti kata sandi…'}
                            aria-label="Cari panduan"
                            className="h-13 rounded-2xl border-white bg-white pr-11 pl-12 text-[16px] text-foreground shadow-float"
                        />
                        {query && (
                            <button
                                type="button"
                                onClick={() => {
                                    setQuery('');
                                    inputRef.current?.focus();
                                }}
                                className="absolute top-1/2 right-2 flex size-9 -translate-y-1/2 items-center justify-center rounded-xl text-muted-foreground hover:bg-secondary hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none"
                                aria-label="Hapus pencarian"
                            >
                                <X className="size-4" />
                            </button>
                        )}
                    </div>
                    {popular.length > 0 && !query && (
                        <div className="mt-4 flex flex-wrap items-center gap-2">
                            <span className="text-xs font-semibold text-white/80">Sering dicari:</span>
                            {popular.slice(0, 5).map((item) => (
                                <button
                                    key={item.slug}
                                    type="button"
                                    onClick={() => openArticle(item.slug)}
                                    className="rounded-full border border-white/25 bg-white/10 px-3 py-1 text-xs font-semibold text-white transition hover:bg-white/20"
                                >
                                    {item.title}
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            </section>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-[260px_minmax(0,1fr)]">
                <div className="lg:sticky lg:top-24 lg:self-start">
                    <nav aria-label="Kategori panduan" className="flex gap-1.5 overflow-x-auto pb-1 lg:flex-col lg:overflow-visible lg:pb-0">
                        <CategoryButton
                            active={!activeCategory && !query.trim() && !article}
                            onClick={() => {
                                setQuery('');
                                visit({});
                            }}
                            icon={LayoutGrid}
                            label="Semua topik"
                            count={articles.length}
                        />
                        {categories.map((category) => (
                            <CategoryButton
                                key={category.key}
                                active={(activeCategory?.key ?? article?.category) === category.key && !query.trim()}
                                onClick={() => {
                                    setQuery('');
                                    visit({ kategori: category.key });
                                }}
                                icon={category.icon}
                                label={category.title}
                                count={category.articles.length}
                            />
                        ))}
                    </nav>

                    {canShowAll && (
                        <label className="mt-4 hidden cursor-pointer items-start gap-2.5 rounded-xl border border-white/80 bg-white/50 p-3 text-xs text-muted-foreground lg:flex">
                            <Switch checked={filters.semua} onCheckedChange={(value) => visit({ semua: value, q: query.trim() || undefined, kategori: filters.kategori || undefined })} />
                            <span>
                                <span className="block font-semibold text-foreground">Tampilkan untuk semua peran</span>
                                Untuk membantu dosen, mahasiswa, atau admin prodi.
                            </span>
                        </label>
                    )}

                    {!user && (
                        <Button asChild className="mt-4 hidden w-full lg:inline-flex">
                            <Link href={route('login')}>
                                <LogIn /> Ke halaman masuk
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="min-w-0">{content}</div>
            </div>
        </>
    );
}

function CategoryButton({ active, onClick, icon: Icon, label, count }: { active: boolean; onClick: () => void; icon: typeof LayoutGrid; label: string; count: number }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-current={active ? 'page' : undefined}
            className={cn(
                'flex shrink-0 items-center gap-2.5 rounded-xl px-3 py-2 text-left text-sm font-medium whitespace-nowrap transition lg:whitespace-normal',
                active ? 'bg-grad-primary text-primary-foreground shadow-soft' : 'bg-white/45 text-foreground/80 hover:bg-white/80 hover:text-foreground lg:bg-transparent',
            )}
        >
            <Icon className="size-4 shrink-0" aria-hidden="true" />
            <span className="flex-1">{label}</span>
            <span className={cn('rounded-full px-1.5 text-[11px] tabular', active ? 'bg-white/20' : 'bg-white/70 text-muted-foreground')}>{count}</span>
        </button>
    );
}
