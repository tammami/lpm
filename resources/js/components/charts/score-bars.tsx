import { formatScore } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type Classification, ClassBadge, InsufficientBadge } from './score-badge';

export interface ScoreBarItem {
    id: number | string;
    label: string;
    sublabel?: string;
    score: number | null;
    responses?: number;
    classification?: Classification | null;
    sufficient?: boolean;
    href?: string;
}

interface Props {
    items: ScoreBarItem[];
    max: number;
    min?: number;
    threshold?: number;
    minimum?: number;
    onSelect?: (item: ScoreBarItem) => void;
    showBadge?: boolean;
}

/**
 * Bar skor horizontal: satu warna seri, ujung data membulat, garis ambang tipis, nilai di ujung bar.
 */
export function ScoreBars({ items, max, min = 0, threshold, minimum = 0, onSelect, showBadge = true }: Props) {
    const span = max - min;
    const pct = (value: number) => Math.max(0, Math.min(100, ((value - min) / span) * 100));

    return (
        <div className="flex flex-col gap-3.5">
            {items.map((item) => {
                const below = threshold !== undefined && item.score !== null && item.score < threshold;
                const Wrapper = onSelect ? 'button' : 'div';
                return (
                    <Wrapper
                        key={item.id}
                        type={onSelect ? 'button' : undefined}
                        onClick={onSelect ? () => onSelect(item) : undefined}
                        className={cn('group relative block w-full text-left', onSelect && 'rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-ring/40')}
                    >
                        <div className="mb-1 flex items-baseline justify-between gap-3">
                            <div className="min-w-0">
                                <span className={cn('truncate text-[13px] font-semibold', onSelect && 'group-hover:text-primary')}>{item.label}</span>
                                {item.sublabel && <span className="ml-2 text-xs text-muted-foreground">{item.sublabel}</span>}
                            </div>
                            <div className="flex shrink-0 items-center gap-2">
                                {item.score === null && item.sufficient === false ? (
                                    <InsufficientBadge responses={item.responses ?? 0} minimum={minimum} />
                                ) : (
                                    <>
                                        {showBadge && <ClassBadge classification={item.classification} />}
                                        <span className="w-10 text-right text-sm font-extrabold tabular">{formatScore(item.score)}</span>
                                    </>
                                )}
                            </div>
                        </div>
                        <div className="relative h-3 rounded-full bg-track">
                            {item.score !== null && (
                                <div
                                    className={cn('absolute inset-y-0 left-0 rounded-r-[4px] rounded-l-full transition-[width] duration-500', below ? 'bg-grad-critical' : 'bg-grad-good')}
                                    style={{ width: `${pct(item.score)}%` }}
                                />
                            )}
                            {threshold !== undefined && (
                                <span className="absolute -top-0.5 -bottom-0.5 w-px bg-foreground/40" style={{ left: `${pct(threshold)}%` }} aria-hidden />
                            )}
                        </div>
                        <div className="pointer-events-none absolute -top-9 right-0 z-10 hidden rounded-lg border border-white/85 surface-glass-strong px-2.5 py-1.5 text-xs group-hover:block">
                            <span className="font-semibold">{item.label}</span> · skor {formatScore(item.score)}
                            {item.responses !== undefined && <span className="text-muted-foreground"> · n={item.responses}</span>}
                        </div>
                    </Wrapper>
                );
            })}
            {threshold !== undefined && (
                <div className="flex items-center gap-2 text-[11px] text-muted-foreground">
                    <span className="inline-block h-3 w-px bg-foreground/40" /> Ambang perhatian {formatScore(threshold)}
                    <span className="ml-3 inline-block size-2 rounded-full bg-destructive" /> Di bawah ambang
                </div>
            )}
        </div>
    );
}
