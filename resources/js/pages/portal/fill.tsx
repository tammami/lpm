import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CalendarClock, EyeOff, LoaderCircle, Save, Send, TriangleAlert } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { type AnswerValue, isAnswered } from '@/components/questionnaire/question-input';
import { countProgress, Questionnaire, type QuestionnaireSection } from '@/components/questionnaire/questionnaire';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { formatDateTime } from '@/lib/format';

interface Props {
    survey: { id: number; title: string; description: string | null; is_anonymous: boolean; ends_at: string; instrument: string; version: string };
    target: { teaching_assignment_id: number | null; course: string | null; course_code: string | null; class_code: string | null; lecturer: string | null };
    reopenReason: string | null;
    sections: QuestionnaireSection[];
}

export default function PortalFill({ survey, target, reopenReason, sections }: Props) {
    const storageKey = `simutu.draft.${survey.id}.${target.teaching_assignment_id ?? 'general'}`;
    const [answers, setAnswers] = useState<Record<number, AnswerValue>>(() => {
        try {
            return JSON.parse(localStorage.getItem(storageKey) ?? '{}');
        } catch {
            return {};
        }
    });
    const [confirming, setConfirming] = useState(false);
    const form = useForm<{ target: number | null; answers: Record<number, AnswerValue> }>({ target: target.teaching_assignment_id, answers: {} });

    useEffect(() => {
        try {
            localStorage.setItem(storageKey, JSON.stringify(answers));
        } catch {
            /* penyimpanan lokal tidak tersedia */
        }
    }, [answers, storageKey]);

    const progress = useMemo(() => countProgress(sections, answers), [sections, answers]);
    const percent = progress.total ? Math.round((progress.answered / progress.total) * 100) : 0;
    const questions = useMemo(() => sections.flatMap((s) => s.questions), [sections]);

    const trySubmit = () => {
        const missing = questions.find((q) => q.is_required && !isAnswered(q, answers[q.id]));
        if (missing) {
            toast.warning('Masih ada pertanyaan wajib yang belum dijawab.');
            document.getElementById(`q-${missing.id}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        setConfirming(true);
    };

    const submit = () => {
        form.transform(() => ({ target: target.teaching_assignment_id, answers }));
        form.post(route('portal.surveys.submit', survey.id), {
            onSuccess: () => {
                try {
                    localStorage.removeItem(storageKey);
                } catch {
                    /* abaikan */
                }
            },
            onError: (errors) => {
                setConfirming(false);
                if (errors.survey) toast.error(errors.survey);
                else toast.error('Periksa kembali jawaban yang ditandai.');
                const first = Object.keys(errors).find((key) => key.startsWith('answers.'));
                if (first) document.getElementById(`q-${first.split('.')[1]}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            },
        });
    };

    return (
        <>
            <Head title={target.course ? `Evaluasi ${target.course}` : survey.title} />
            <Link href={route('portal.home')} className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                <ArrowLeft className="size-4" /> Kembali ke daftar
            </Link>

            <section className="rounded-3xl border bg-card p-5">
                <div className="text-xs font-semibold tracking-wide text-primary uppercase">{survey.title}</div>
                {target.course ? (
                    <>
                        <h1 className="mt-1 text-xl leading-snug font-extrabold">{target.course}</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            <span className="font-semibold text-foreground">{target.lecturer}</span> · Kelas {target.class_code} · {target.course_code}
                        </p>
                    </>
                ) : (
                    <h1 className="mt-1 text-xl font-extrabold">{survey.instrument}</h1>
                )}
                {survey.description && <p className="mt-3 text-sm leading-relaxed text-muted-foreground">{survey.description}</p>}
                <div className="mt-4 flex flex-wrap gap-2 text-xs">
                    {survey.is_anonymous && (
                        <span className="inline-flex items-center gap-1.5 rounded-lg bg-secondary px-2.5 py-1 font-semibold text-secondary-foreground">
                            <EyeOff className="size-3.5" /> Anonim
                        </span>
                    )}
                    <span className="inline-flex items-center gap-1.5 rounded-lg bg-muted px-2.5 py-1 font-semibold text-muted-foreground">
                        <CalendarClock className="size-3.5" /> Batas {formatDateTime(survey.ends_at)}
                    </span>
                    <span className="inline-flex items-center gap-1.5 rounded-lg bg-muted px-2.5 py-1 font-semibold text-muted-foreground">
                        <Save className="size-3.5" /> Draf tersimpan otomatis di perangkat ini
                    </span>
                </div>
                {reopenReason && (
                    <div className="mt-4 flex gap-2 rounded-xl bg-warning-soft p-3 text-xs text-gold-foreground">
                        <TriangleAlert className="size-4 shrink-0" /> Pengisian Anda dibuka kembali oleh LPM: {reopenReason}. Silakan isi ulang.
                    </div>
                )}
            </section>

            <div className="sticky top-16 z-20 -mx-4 mt-4 border-b bg-background/95 px-4 py-3 backdrop-blur">
                <div className="mb-1.5 flex justify-between text-xs font-semibold">
                    <span>
                        {progress.answeredRequired}/{progress.required} pertanyaan wajib
                    </span>
                    <span className="tabular">{percent}%</span>
                </div>
                <Progress value={percent} className="h-1.5" />
            </div>

            <div className="mt-5">
                <Questionnaire sections={sections} answers={answers} onChange={(id, value) => setAnswers((a) => ({ ...a, [id]: value }))} errors={form.errors as Record<string, string>} />
            </div>

            <div className="sticky bottom-0 z-20 -mx-4 mt-8 border-t bg-background/95 px-4 py-3 backdrop-blur sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:px-0">
                <Button size="lg" className="h-12 w-full text-[16px] font-bold" onClick={trySubmit} disabled={form.processing}>
                    <Send /> Kirim evaluasi
                </Button>
            </div>

            <AlertDialog open={confirming} onOpenChange={setConfirming}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Kirim evaluasi sekarang?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Setelah dikirim, jawaban tidak dapat diubah. {survey.is_anonymous && 'Identitas Anda tidak tersimpan bersama jawaban.'}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={form.processing}>Periksa lagi</AlertDialogCancel>
                        <Button onClick={submit} disabled={form.processing}>
                            {form.processing ? <LoaderCircle className="animate-spin" /> : <Send />} Ya, kirim
                        </Button>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
