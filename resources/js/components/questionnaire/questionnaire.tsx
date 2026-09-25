import { CheckCircle2 } from 'lucide-react';
import { type AnswerValue, isAnswered, type Question, QuestionInput } from '@/components/questionnaire/question-input';
import { cn } from '@/lib/utils';

export interface QuestionnaireSection {
    id: number;
    code: string;
    title: string;
    description: string | null;
    questions: Question[];
}

interface Props {
    sections: QuestionnaireSection[];
    answers: Record<number, AnswerValue>;
    onChange: (questionId: number, value: AnswerValue) => void;
    errors?: Record<string, string>;
    disabled?: boolean;
    showNumbers?: boolean;
}

export function Questionnaire({ sections, answers, onChange, errors = {}, disabled }: Props) {
    let number = 0;

    return (
        <div className="flex flex-col gap-6">
            {sections.map((section) => {
                const answered = section.questions.filter((q) => isAnswered(q, answers[q.id])).length;
                return (
                    <section key={section.id} id={`q-section-${section.id}`} className="scroll-mt-28">
                        <div className="mb-3 flex items-end justify-between gap-3 px-1">
                            <div className="flex items-center gap-3">
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary text-sm font-extrabold text-gold">{section.code}</span>
                                <div>
                                    <h2 className="text-[16px] leading-tight font-bold">{section.title}</h2>
                                    {section.description && <p className="text-xs text-muted-foreground">{section.description}</p>}
                                </div>
                            </div>
                            <span className="shrink-0 text-xs font-semibold text-muted-foreground tabular">
                                {answered}/{section.questions.length}
                            </span>
                        </div>
                        <div className="flex flex-col gap-3">
                            {section.questions.map((question) => {
                                number += 1;
                                const value = answers[question.id];
                                const done = isAnswered(question, value);
                                const error = errors[`answers.${question.id}`];
                                return (
                                    <div
                                        key={question.id}
                                        id={`q-${question.id}`}
                                        className={cn(
                                            'scroll-mt-28 rounded-2xl border bg-card p-4 transition sm:p-5',
                                            error ? 'border-destructive/50 ring-2 ring-destructive/10' : done ? 'border-primary/25' : '',
                                        )}
                                    >
                                        <div className="mb-3.5 flex gap-3">
                                            <span className={cn('mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold', done ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground')}>
                                                {done ? <CheckCircle2 className="size-4" /> : number}
                                            </span>
                                            <div>
                                                <p className="text-[16px] leading-snug font-semibold">
                                                    {question.label}
                                                    {question.is_required && <span className="ml-0.5 text-destructive">*</span>}
                                                </p>
                                                {question.description && <p className="mt-1 text-sm text-muted-foreground">{question.description}</p>}
                                            </div>
                                        </div>
                                        <QuestionInput question={question} value={value} onChange={(v) => onChange(question.id, v)} disabled={disabled} invalid={!!error} />
                                        {error && <p className="mt-2 text-xs font-medium text-destructive">{error}</p>}
                                    </div>
                                );
                            })}
                        </div>
                    </section>
                );
            })}
        </div>
    );
}

export function countProgress(sections: QuestionnaireSection[], answers: Record<number, AnswerValue>) {
    const questions = sections.flatMap((s) => s.questions);
    const required = questions.filter((q) => q.is_required);
    const answeredRequired = required.filter((q) => isAnswered(q, answers[q.id])).length;
    const answered = questions.filter((q) => isAnswered(q, answers[q.id])).length;
    return { total: questions.length, answered, required: required.length, answeredRequired };
}
