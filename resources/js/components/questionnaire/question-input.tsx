import { Check, Paperclip, Star } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

export interface QuestionOption {
    id: number;
    label: string;
    value: string;
    score: number | null;
}

export interface Question {
    id: number;
    code: string;
    label: string;
    description: string | null;
    type: string;
    type_label?: string;
    is_required: boolean;
    settings: Record<string, unknown> | null;
    options: QuestionOption[];
}

/** Nilai jawaban: id opsi (tunggal), daftar id opsi (ganda), angka, atau teks. */
export type AnswerValue = number | number[] | string | null | undefined;

interface Props {
    question: Question;
    value: AnswerValue;
    onChange: (value: AnswerValue) => void;
    disabled?: boolean;
    invalid?: boolean;
}

export function QuestionInput({ question, value, onChange, disabled, invalid }: Props) {
    switch (question.type) {
        case 'likert':
            return <LikertInput question={question} value={value as number | undefined} onChange={onChange} disabled={disabled} />;
        case 'yes_no':
            return <ChoiceGrid question={question} value={value as number | undefined} onChange={onChange} disabled={disabled} columns={2} />;
        case 'single_choice':
            return <ChoiceList question={question} value={value as number | undefined} onChange={onChange} disabled={disabled} />;
        case 'multiple_choice':
            return <MultiChoiceList question={question} value={(value as number[] | undefined) ?? []} onChange={onChange} disabled={disabled} />;
        case 'rating':
            return <RatingInput max={Number(question.settings?.max_rating ?? 5)} value={value as number | undefined} onChange={onChange} disabled={disabled} />;
        case 'numeric':
            return (
                <Input
                    type="number"
                    inputMode="decimal"
                    min={question.settings?.min as number | undefined}
                    max={question.settings?.max as number | undefined}
                    value={(value as string | number | undefined) ?? ''}
                    onChange={(e) => onChange(e.target.value === '' ? null : Number(e.target.value))}
                    disabled={disabled}
                    aria-invalid={invalid}
                    className="h-11 max-w-60 bg-card"
                />
            );
        case 'date':
            return (
                <Input type="date" value={(value as string | undefined) ?? ''} onChange={(e) => onChange(e.target.value)} disabled={disabled} aria-invalid={invalid} className="h-11 max-w-60 bg-card" />
            );
        case 'text':
            return (
                <Input
                    value={(value as string | undefined) ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                    maxLength={(question.settings?.max_length as number | undefined) ?? 255}
                    disabled={disabled}
                    aria-invalid={invalid}
                    className="h-11 bg-card"
                    placeholder="Tulis jawaban singkat…"
                />
            );
        case 'long_text':
            return (
                <Textarea
                    value={(value as string | undefined) ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                    maxLength={(question.settings?.max_length as number | undefined) ?? 2000}
                    disabled={disabled}
                    aria-invalid={invalid}
                    rows={4}
                    className="bg-card"
                    placeholder="Tuliskan masukan Anda secara jelas dan santun…"
                />
            );
        case 'file_upload':
            return (
                <div className="flex items-center gap-2 rounded-xl border border-dashed bg-muted/40 px-4 py-3 text-sm text-muted-foreground">
                    <Paperclip className="size-4" /> Unggah berkas tersedia pada modul Dokumen Bukti.
                </div>
            );
        default:
            return null;
    }
}

function LikertInput({ question, value, onChange, disabled }: { question: Question; value?: number; onChange: (v: AnswerValue) => void; disabled?: boolean }) {
    const count = question.options.length;

    return (
        <div role="radiogroup" aria-label={question.label} className={cn('grid gap-2', count <= 4 ? 'grid-cols-2 sm:grid-cols-4' : 'grid-cols-2 sm:grid-cols-5')}>
            {question.options.map((option, index) => {
                const selected = value === option.id;
                return (
                    <button
                        key={option.id}
                        type="button"
                        role="radio"
                        aria-checked={selected}
                        disabled={disabled}
                        onClick={() => onChange(option.id)}
                        className={cn(
                            'group relative flex min-h-14 flex-col items-center justify-center gap-0.5 rounded-xl border-2 px-2 py-2.5 text-center transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/40',
                            selected ? 'border-primary bg-primary text-primary-foreground shadow-md shadow-primary/20' : 'border-border bg-card hover:border-primary/40 hover:bg-secondary/60',
                            count % 2 === 1 && index === count - 1 && 'col-span-2 sm:col-span-1',
                        )}
                    >
                        <span className={cn('text-lg leading-none font-extrabold tabular', selected ? 'text-gold' : 'text-primary/80')}>{option.value}</span>
                        <span className={cn('text-[12px] leading-tight font-semibold', selected ? 'text-primary-foreground' : 'text-foreground/80')}>{option.label}</span>
                    </button>
                );
            })}
        </div>
    );
}

function ChoiceGrid({ question, value, onChange, disabled, columns }: { question: Question; value?: number; onChange: (v: AnswerValue) => void; disabled?: boolean; columns: number }) {
    return (
        <div role="radiogroup" className={cn('grid gap-2', columns === 2 && 'grid-cols-2 sm:max-w-sm')}>
            {question.options.map((option) => {
                const selected = value === option.id;
                return (
                    <button
                        key={option.id}
                        type="button"
                        role="radio"
                        aria-checked={selected}
                        disabled={disabled}
                        onClick={() => onChange(option.id)}
                        className={cn(
                            'h-12 rounded-xl border-2 text-sm font-bold transition outline-none focus-visible:ring-[3px] focus-visible:ring-ring/40',
                            selected ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-card hover:border-primary/40',
                        )}
                    >
                        {option.label}
                    </button>
                );
            })}
        </div>
    );
}

function ChoiceList({ question, value, onChange, disabled }: { question: Question; value?: number; onChange: (v: AnswerValue) => void; disabled?: boolean }) {
    return (
        <div role="radiogroup" className="flex flex-col gap-2">
            {question.options.map((option) => {
                const selected = value === option.id;
                return (
                    <button
                        key={option.id}
                        type="button"
                        role="radio"
                        aria-checked={selected}
                        disabled={disabled}
                        onClick={() => onChange(option.id)}
                        className={cn(
                            'flex min-h-12 items-center gap-3 rounded-xl border-2 px-4 py-2.5 text-left text-sm font-medium transition outline-none focus-visible:ring-[3px] focus-visible:ring-ring/40',
                            selected ? 'border-primary bg-secondary' : 'border-border bg-card hover:border-primary/40',
                        )}
                    >
                        <span className={cn('flex size-5 shrink-0 items-center justify-center rounded-full border-2', selected ? 'border-primary' : 'border-muted-foreground/40')}>
                            {selected && <span className="size-2.5 rounded-full bg-primary" />}
                        </span>
                        {option.label}
                    </button>
                );
            })}
        </div>
    );
}

function MultiChoiceList({ question, value, onChange, disabled }: { question: Question; value: number[]; onChange: (v: AnswerValue) => void; disabled?: boolean }) {
    const toggle = (id: number) => onChange(value.includes(id) ? value.filter((v) => v !== id) : [...value, id]);

    return (
        <div className="flex flex-col gap-2">
            {question.options.map((option) => {
                const selected = value.includes(option.id);
                return (
                    <button
                        key={option.id}
                        type="button"
                        role="checkbox"
                        aria-checked={selected}
                        disabled={disabled}
                        onClick={() => toggle(option.id)}
                        className={cn(
                            'flex min-h-12 items-center gap-3 rounded-xl border-2 px-4 py-2.5 text-left text-sm font-medium transition outline-none focus-visible:ring-[3px] focus-visible:ring-ring/40',
                            selected ? 'border-primary bg-secondary' : 'border-border bg-card hover:border-primary/40',
                        )}
                    >
                        <span className={cn('flex size-5 shrink-0 items-center justify-center rounded-md border-2', selected ? 'border-primary bg-primary text-primary-foreground' : 'border-muted-foreground/40')}>
                            {selected && <Check className="size-3.5" />}
                        </span>
                        {option.label}
                    </button>
                );
            })}
        </div>
    );
}

function RatingInput({ max, value, onChange, disabled }: { max: number; value?: number; onChange: (v: AnswerValue) => void; disabled?: boolean }) {
    return (
        <div className="flex items-center gap-1" role="radiogroup">
            {Array.from({ length: max }, (_, i) => i + 1).map((star) => (
                <button
                    key={star}
                    type="button"
                    role="radio"
                    aria-checked={value === star}
                    aria-label={`${star} bintang`}
                    disabled={disabled}
                    onClick={() => onChange(star)}
                    className="rounded-md p-1 transition hover:scale-110"
                >
                    <Star className={cn('size-8', value && star <= value ? 'fill-gold text-gold' : 'text-muted-foreground/30')} />
                </button>
            ))}
            {value ? <span className="ml-2 text-sm font-semibold text-muted-foreground tabular">{value}/{max}</span> : null}
        </div>
    );
}

/** Apakah jawaban dianggap terisi. */
export function isAnswered(question: Question, value: AnswerValue) {
    if (question.type === 'multiple_choice') return Array.isArray(value) && value.length > 0;
    if (question.type === 'file_upload') return true;
    return value !== null && value !== undefined && value !== '';
}
