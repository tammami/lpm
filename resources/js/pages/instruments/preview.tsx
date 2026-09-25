import { Link } from '@inertiajs/react';
import { ArrowLeft, Eye, RotateCcw } from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import type { AnswerValue } from '@/components/questionnaire/question-input';
import { countProgress, Questionnaire, type QuestionnaireSection } from '@/components/questionnaire/questionnaire';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';

interface Props {
    instrument: { id: number; name: string; description: string | null };
    version: { id: number; version: string; status_label: string };
    sections: QuestionnaireSection[];
}

export default function InstrumentPreview({ instrument, version, sections }: Props) {
    const [answers, setAnswers] = useState<Record<number, AnswerValue>>({});
    const progress = countProgress(sections, answers);
    const percent = progress.total ? Math.round((progress.answered / progress.total) * 100) : 0;

    return (
        <div className="mx-auto max-w-3xl">
            <PageHeader
                title={`Pratinjau: ${instrument.name}`}
                breadcrumbs={[{ label: 'Instrumen', href: route('instruments.index') }, { label: `v${version.version}`, href: route('instrument-versions.show', version.id) }, { label: 'Pratinjau' }]}
                actions={
                    <>
                        <Button variant="outline" onClick={() => setAnswers({})}>
                            <RotateCcw /> Kosongkan
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={route('instrument-versions.show', version.id)}>
                                <ArrowLeft /> Kembali ke builder
                            </Link>
                        </Button>
                    </>
                }
            />
            <Alert className="mb-5 border-info/30 bg-info-soft">
                <Eye className="text-info" />
                <AlertDescription className="text-info">Mode pratinjau — tampilan seperti yang dilihat responden. Jawaban tidak disimpan.</AlertDescription>
            </Alert>
            <div className="sticky top-16 z-10 -mx-1 mb-5 rounded-2xl border bg-card/95 p-4 backdrop-blur">
                <div className="mb-2 flex justify-between text-xs font-semibold">
                    <span>Kelengkapan</span>
                    <span className="tabular">
                        {progress.answered}/{progress.total} butir · {percent}%
                    </span>
                </div>
                <Progress value={percent} className="h-2" />
            </div>
            <Questionnaire sections={sections} answers={answers} onChange={(id, value) => setAnswers((a) => ({ ...a, [id]: value }))} />
        </div>
    );
}
