import { useForm } from '@inertiajs/react';
import { Check, LoaderCircle, Sparkles, Wand2 } from 'lucide-react';
import { useState } from 'react';
import { Combobox } from '@/components/combobox';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { originTone, priorityTone } from '@/lib/improvement';
import { formatScore } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Option } from '@/types';

interface Candidate {
    rule_key: string;
    title: string;
    description: string;
    rationale: string;
    origin: string;
    origin_label: string;
    target_name: string;
    indicator: string | null;
    priority: string;
    priority_label: string;
    pic_user_id: number | null;
    exists: boolean;
}

export default function Generate({ candidates, threshold, picOptions, defaultDueDays }: { candidates: Candidate[]; threshold: number; picOptions: Option[]; defaultDueDays: number }) {
    const fresh = candidates.filter((c) => !c.exists);
    const [selection, setSelection] = useState<Record<string, { pic_user_id: string; priority: string }>>(() =>
        Object.fromEntries(fresh.filter((c) => c.priority === 'high').map((c) => [c.rule_key, { pic_user_id: c.pic_user_id ? String(c.pic_user_id) : '', priority: c.priority }])),
    );
    const form = useForm({ selected: [] as { rule_key: string; pic_user_id: string | null; priority: string }[] });

    const toggle = (candidate: Candidate) =>
        setSelection((current) => {
            const next = { ...current };
            if (next[candidate.rule_key]) delete next[candidate.rule_key];
            else next[candidate.rule_key] = { pic_user_id: candidate.pic_user_id ? String(candidate.pic_user_id) : '', priority: candidate.priority };
            return next;
        });

    const submit = () => {
        form.transform(() => ({
            selected: Object.entries(selection).map(([rule_key, value]) => ({ rule_key, pic_user_id: value.pic_user_id || null, priority: value.priority })),
        }));
        form.post(route('improvement.recommendations.accept'));
    };

    const priorities = [
        { value: 'high', label: 'Tinggi' },
        { value: 'medium', label: 'Sedang' },
        { value: 'low', label: 'Rendah' },
    ];

    return (
        <>
            <PageHeader
                title="Usulan Rekomendasi Otomatis"
                description={
                    <>
                        Dihasilkan dari aturan: skor Monev di bawah ambang ({formatScore(threshold)}), response rate &lt; 60%, penurunan tren, temuan AMI lewat tenggat, dan akreditasi yang segera berakhir.
                        Tinjau, pilih, tetapkan PIC — tenggat bawaan {defaultDueDays} hari.
                    </>
                }
                breadcrumbs={[{ label: 'Rekomendasi', href: route('improvement.recommendations.index') }, { label: 'Usulan otomatis' }]}
                actions={
                    <Button onClick={submit} disabled={Object.keys(selection).length === 0 || form.processing}>
                        {form.processing ? <LoaderCircle className="animate-spin" /> : <Wand2 />} Jadikan {Object.keys(selection).length} rekomendasi
                    </Button>
                }
            />
            {form.errors.selected && <p className="mb-4 text-sm text-destructive">{form.errors.selected}</p>}
            {candidates.length === 0 ? (
                <Card>
                    <EmptyState icon={Sparkles} title="Tidak ada usulan baru" description="Seluruh indikator berada di atas ambang dan tidak ada tenggat yang terlewat. Pertahankan!" />
                </Card>
            ) : (
                <div className="flex flex-col gap-3">
                    {candidates.map((candidate) => {
                        const selected = selection[candidate.rule_key];
                        return (
                            <div
                                key={candidate.rule_key}
                                className={cn(
                                    'grid gap-4 rounded-2xl border bg-card p-4 transition lg:grid-cols-[32px_minmax(0,1fr)_380px] lg:items-center',
                                    selected && 'border-primary ring-2 ring-primary/15',
                                    candidate.exists && 'opacity-55',
                                )}
                            >
                                <button
                                    type="button"
                                    disabled={candidate.exists}
                                    onClick={() => toggle(candidate)}
                                    aria-label="Pilih"
                                    className={cn('flex size-6 items-center justify-center rounded-md border-2', selected ? 'border-primary bg-primary text-primary-foreground' : 'border-input')}
                                >
                                    {selected && <Check className="size-4" strokeWidth={3} />}
                                </button>
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <StatusBadge tone={originTone[candidate.origin]} dot={false}>
                                            {candidate.origin_label}
                                        </StatusBadge>
                                        <StatusBadge tone={priorityTone[candidate.priority]}>{candidate.priority_label}</StatusBadge>
                                        {candidate.exists && <StatusBadge tone="neutral">Sudah dibuat</StatusBadge>}
                                    </div>
                                    <div className="mt-1.5 font-bold">{candidate.title}</div>
                                    <p className="mt-0.5 text-sm text-muted-foreground">{candidate.description}</p>
                                    <p className="mt-1 text-xs font-medium text-gold-foreground">Dasar: {candidate.rationale}</p>
                                </div>
                                {selected ? (
                                    <div className="grid grid-cols-[1fr_120px] gap-2">
                                        <Combobox
                                            value={selected.pic_user_id}
                                            onChange={(v) => setSelection((s) => ({ ...s, [candidate.rule_key]: { ...s[candidate.rule_key], pic_user_id: v } }))}
                                            options={picOptions}
                                            placeholder="PIC…"
                                        />
                                        <SelectField value={selected.priority} onChange={(v) => setSelection((s) => ({ ...s, [candidate.rule_key]: { ...s[candidate.rule_key], priority: v } }))} options={priorities} />
                                    </div>
                                ) : (
                                    <div className="text-xs text-muted-foreground lg:text-right">{candidate.target_name}</div>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}
        </>
    );
}
