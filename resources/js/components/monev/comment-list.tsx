import { Quote, Tags } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';

export interface CategorizedComments {
    total: number;
    categories: { value: string; label: string; count: number }[];
    items: {
        question: string;
        text: string;
        category: string;
        category_label: string;
    }[];
}

/**
 * Saran/komentar terbuka e-Monev dengan filter kategori otomatis (berbasis kata kunci).
 * "Tidak ada saran" disembunyikan dari tampilan "Semua" agar isian kosong tidak mengganggu.
 */
export function CommentList({
    comments,
    columns = 1,
    limit = 40,
    emptyText = 'Belum ada komentar.',
}: {
    comments: CategorizedComments;
    columns?: 1 | 2;
    /** Batas cuplikan pada tampilan "Semua" (data sudah diacak di server). */
    limit?: number;
    emptyText?: string;
}) {
    const [category, setCategory] = useState('all');
    const meaningful = comments.categories.filter((c) => c.value !== 'empty');
    const meaningfulTotal = meaningful.reduce((sum, c) => sum + c.count, 0);

    const items = useMemo(
        () => (category === 'all' ? comments.items.filter((item) => item.category !== 'empty').slice(0, limit) : comments.items.filter((item) => item.category === category)),
        [comments.items, category, limit],
    );
    const selectedCount = category === 'all' ? meaningfulTotal : (comments.categories.find((c) => c.value === category)?.count ?? 0);

    if (comments.total === 0) {
        return <p className="text-sm text-muted-foreground">{emptyText}</p>;
    }

    return (
        <div className="flex flex-col gap-3">
            <ToggleGroup
                type="single"
                value={category}
                onValueChange={(value) => value && setCategory(value)}
                variant="outline"
                size="sm"
                spacing={1.5}
                className="w-full flex-wrap"
                aria-label="Filter kategori saran"
            >
                <ToggleGroupItem value="all" className="rounded-full">
                    Semua <span className="tabular opacity-80">{formatNumber(meaningfulTotal)}</span>
                </ToggleGroupItem>
                {comments.categories.map((c) => (
                    <ToggleGroupItem key={c.value} value={c.value} className="rounded-full">
                        {c.label} <span className="tabular opacity-80">{formatNumber(c.count)}</span>
                    </ToggleGroupItem>
                ))}
            </ToggleGroup>

            <p className="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                <Tags className="size-3.5 shrink-0" aria-hidden="true" />
                Dikelompokkan otomatis berdasarkan kata kunci — bisa kurang tepat.
                {items.length < selectedCount && ` Menampilkan ${formatNumber(items.length)} cuplikan acak dari ${formatNumber(selectedCount)}.`}
            </p>

            {items.length === 0 ? (
                <p className="text-sm text-muted-foreground">Tidak ada komentar pada kategori ini.</p>
            ) : (
                <div className={cn('grid grid-cols-1 gap-2.5', columns === 2 && 'md:grid-cols-2')}>
                    {items.map((comment, index) => (
                        <figure key={index} className="relative rounded-xl bg-muted/50 py-3 pr-4 pl-10">
                            <Quote className="absolute top-3.5 left-3.5 size-4 fill-gold/30 text-gold-foreground" aria-hidden="true" />
                            <blockquote className="text-[13px] leading-relaxed">{comment.text}</blockquote>
                            <figcaption className="mt-1 flex flex-wrap items-center gap-x-2 text-[11px] text-muted-foreground">
                                {category === 'all' && <span className="font-semibold text-primary">{comment.category_label}</span>}
                                <span>{comment.question}</span>
                            </figcaption>
                        </figure>
                    ))}
                </div>
            )}
        </div>
    );
}
