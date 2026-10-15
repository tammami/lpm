import { router } from '@inertiajs/react';
import { ArrowRight, ChevronRight, Lightbulb, MapPin, UsersRound } from 'lucide-react';
import { Fragment, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { categoryOf, findArticle, type GuideArticle } from '@/lib/guide';
import { cn } from '@/lib/utils';

/** Ubah **tebal** menjadi <strong>. */
export function RichText({ text }: { text: string }) {
    const parts = text.split(/(\*\*[^*]+\*\*)/g);
    return (
        <>
            {parts.map((part, index) =>
                part.startsWith('**') && part.endsWith('**') ? (
                    <strong key={index} className="font-semibold text-foreground">
                        {part.slice(2, -2)}
                    </strong>
                ) : (
                    <Fragment key={index}>{part}</Fragment>
                ),
            )}
        </>
    );
}

const routeAvailable = (name?: string) => {
    if (!name) return false;
    try {
        return route().has(name);
    } catch {
        return false;
    }
};

interface Props {
    article: GuideArticle;
    /** Panduan terkait yang boleh ditampilkan (sudah disaring per peran). */
    visibleSlugs: Set<string>;
    onOpenArticle: (slug: string) => void;
    /** Dipanggil sebelum pindah ke halaman fitur, mis. untuk menutup panel. */
    onNavigate?: () => void;
    compact?: boolean;
    footer?: ReactNode;
}

export function GuideArticleView({ article, visibleSlugs, onOpenArticle, onNavigate, compact = false, footer }: Props) {
    const category = categoryOf(article);
    const CategoryIcon = category?.icon;
    const related = (article.related ?? []).filter((slug) => visibleSlugs.has(slug)).map(findArticle).filter((item): item is GuideArticle => !!item);
    const canOpenPage = article.where?.route && routeAvailable(article.where.route);

    return (
        <article className="flex flex-col gap-5">
            <header>
                {category && (
                    <div className="mb-2 inline-flex items-center gap-1.5 rounded-full bg-secondary px-2.5 py-0.5 text-xs font-semibold text-secondary-foreground">
                        {CategoryIcon && <CategoryIcon className="size-3.5" />}
                        {category.title}
                    </div>
                )}
                <h2 className={cn('font-extrabold tracking-tight text-foreground', compact ? 'text-lg leading-snug' : 'text-2xl')}>{article.title}</h2>
                <p className="mt-1.5 text-sm text-muted-foreground">{article.summary}</p>
            </header>

            {(article.where || article.who) && (
                <div className="flex flex-col gap-2.5 rounded-2xl border border-white/80 bg-white/60 p-3.5 text-sm shadow-soft">
                    {article.where && (
                        <div className="flex items-start gap-2.5">
                            <MapPin className="mt-0.5 size-4 shrink-0 text-primary" aria-hidden="true" />
                            <div className="min-w-0 flex-1">
                                <div className="text-xs font-semibold text-muted-foreground">Letak menu</div>
                                <div className="flex flex-wrap items-center gap-1 font-semibold">
                                    {article.where.path.map((step, index) => (
                                        <span key={index} className="inline-flex items-center gap-1">
                                            {index > 0 && <ChevronRight className="size-3.5 text-muted-foreground" aria-hidden="true" />}
                                            {step}
                                        </span>
                                    ))}
                                </div>
                            </div>
                        </div>
                    )}
                    {article.who && (
                        <div className="flex items-start gap-2.5">
                            <UsersRound className="mt-0.5 size-4 shrink-0 text-primary" aria-hidden="true" />
                            <div>
                                <div className="text-xs font-semibold text-muted-foreground">Untuk</div>
                                <div>{article.who}</div>
                            </div>
                        </div>
                    )}
                    {canOpenPage && (
                        <Button
                            size="sm"
                            className="self-start"
                            onClick={() => {
                                onNavigate?.();
                                router.visit(route(article.where!.route!));
                            }}
                        >
                            Buka halamannya <ArrowRight />
                        </Button>
                    )}
                </div>
            )}

            {article.steps && article.steps.length > 0 && (
                <section aria-label="Langkah-langkah">
                    <h3 className="mb-3 text-sm font-bold">Langkah-langkah</h3>
                    <ol className="flex flex-col gap-3">
                        {article.steps.map((step, index) => (
                            <li key={index} className="flex gap-3">
                                <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-grad-primary text-xs font-bold text-primary-foreground tabular">
                                    {index + 1}
                                </span>
                                <p className="pt-0.5 text-sm leading-relaxed text-foreground/85">
                                    <RichText text={step} />
                                </p>
                            </li>
                        ))}
                    </ol>
                </section>
            )}

            {article.notes && article.notes.length > 0 && (
                <div role="note" aria-label="Perlu diketahui" className="rounded-2xl border border-gold/30 bg-gold-soft/70 p-4">
                    <h3 className="mb-2 flex items-center gap-1.5 text-sm font-bold text-gold-foreground">
                        <Lightbulb className="size-4" aria-hidden="true" /> Perlu diketahui
                    </h3>
                    <ul className="flex list-disc flex-col gap-1.5 pl-5 text-sm leading-relaxed text-foreground/85 marker:text-gold-foreground">
                        {article.notes.map((note, index) => (
                            <li key={index}>
                                <RichText text={note} />
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {related.length > 0 && (
                <section aria-label="Panduan terkait">
                    <h3 className="mb-2 text-sm font-bold">Panduan terkait</h3>
                    <div className="flex flex-col gap-1.5">
                        {related.map((item) => (
                            <button
                                key={item.slug}
                                type="button"
                                onClick={() => onOpenArticle(item.slug)}
                                className="group flex items-center justify-between gap-3 rounded-xl border border-white/80 bg-white/55 px-3 py-2 text-left text-sm font-medium transition hover:border-primary/30 hover:bg-white"
                            >
                                <span>{item.title}</span>
                                <ChevronRight className="size-4 shrink-0 text-muted-foreground transition group-hover:translate-x-0.5 group-hover:text-primary" />
                            </button>
                        ))}
                    </div>
                </section>
            )}

            {footer}
        </article>
    );
}

/** Satu baris hasil/daftar panduan. */
export function GuideArticleLink({ article, onClick, showCategory = true }: { article: GuideArticle; onClick: () => void; showCategory?: boolean }) {
    const category = categoryOf(article);
    const Icon = category?.icon;

    return (
        <button
            type="button"
            onClick={onClick}
            className="group flex w-full items-start gap-3 rounded-xl border border-white/80 bg-white/55 p-3 text-left transition hover:border-primary/30 hover:bg-white focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:outline-none"
        >
            {Icon && (
                <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-secondary text-secondary-foreground">
                    <Icon className="size-4" />
                </span>
            )}
            <span className="min-w-0 flex-1">
                <span className="block text-sm font-semibold text-foreground">{article.title}</span>
                <span className="mt-0.5 block text-xs text-muted-foreground">{article.summary}</span>
                {showCategory && category && <span className="mt-1 block text-[11px] font-semibold text-primary">{category.title}</span>}
            </span>
            <ChevronRight className="mt-1 size-4 shrink-0 text-muted-foreground transition group-hover:translate-x-0.5 group-hover:text-primary" />
        </button>
    );
}
