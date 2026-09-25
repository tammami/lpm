import { Database, Download, FileSpreadsheet, FileText, UserRound } from 'lucide-react';
import { useState } from 'react';
import { Combobox } from '@/components/combobox';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { Option } from '@/types';

interface Props {
    surveys: (Option & { mode: string; status_label: string })[];
    studyPrograms: Option[];
    lecturers: Option[];
    canRaw: boolean;
}

export default function ReportsIndex({ surveys, studyPrograms, lecturers, canRaw }: Props) {
    const [survey, setSurvey] = useState(surveys[0] ? String(surveys[0].value) : '');
    const [program, setProgram] = useState('all');
    const [lecturer, setLecturer] = useState('');
    const selected = surveys.find((s) => String(s.value) === survey);
    const params = { survey, study_program_id: program === 'all' ? undefined : program };

    return (
        <>
            <PageHeader
                title="Laporan"
                description="Unduh laporan resmi ber-kop institusi (PDF) dan rekap data (Excel). Setiap unduhan tercatat di log audit."
                breadcrumbs={[{ label: 'Analitik' }, { label: 'Laporan' }]}
            />
            <Card className="mb-6">
                <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <FormField label="Kegiatan Monev / survei">
                        <SelectField value={survey} onChange={setSurvey} options={surveys.map((s) => ({ value: s.value, label: `${s.label} · ${s.status_label}` }))} />
                    </FormField>
                    <FormField label="Cakupan">
                        <SelectField value={program} onChange={setProgram} options={studyPrograms} allLabel="Seluruh cakupan saya" />
                    </FormField>
                </CardContent>
            </Card>

            <div className="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
                <ReportCard
                    icon={FileText}
                    title="Laporan hasil Monev (PDF)"
                    description="Ringkasan, skor per prodi/aspek/butir, per dosen (sesuai hak akses), komentar, rekomendasi awal, dan area tanda tangan Ketua LPM."
                    href={survey ? route('reports.monev.pdf', params) : undefined}
                    label="Unduh PDF"
                />
                <ReportCard
                    icon={FileSpreadsheet}
                    title="Rekap analitik (Excel)"
                    description="Beberapa sheet: ringkasan, per prodi, per butir/indikator, dan per dosen — siap diolah lebih lanjut."
                    href={survey ? route('reports.monev.excel', params) : undefined}
                    label="Unduh Excel"
                />
                {canRaw && (
                    <ReportCard
                        icon={Database}
                        title="Data respons mentah (Excel)"
                        description="Satu baris per respons anonim beserta jawaban tiap butir. Tidak memuat identitas mahasiswa."
                        href={survey ? route('reports.monev.responses', { survey }) : undefined}
                        label="Unduh data"
                    />
                )}
                {lecturers.length > 0 && selected?.mode === 'teaching_evaluation' && (
                    <Card className="flex flex-col">
                        <CardHeader>
                            <span className="mb-2 flex size-10 items-center justify-center rounded-xl bg-secondary text-primary">
                                <UserRound className="size-5" />
                            </span>
                            <CardTitle>Laporan individual dosen (PDF)</CardTitle>
                            <CardDescription>Rapor evaluasi pembelajaran per dosen untuk bahan pembinaan.</CardDescription>
                        </CardHeader>
                        <CardContent className="mt-auto flex flex-col gap-3">
                            <Combobox value={lecturer} onChange={setLecturer} options={lecturers} placeholder="Pilih dosen…" />
                            <Button asChild disabled={!lecturer} variant="outline">
                                <a href={lecturer ? route('reports.lecturer.pdf', { lecturer, survey }) : undefined} aria-disabled={!lecturer}>
                                    <Download /> Unduh PDF dosen
                                </a>
                            </Button>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

function ReportCard({ icon: Icon, title, description, href, label }: { icon: typeof FileText; title: string; description: string; href?: string; label: string }) {
    return (
        <Card className="flex flex-col">
            <CardHeader>
                <span className="mb-2 flex size-10 items-center justify-center rounded-xl bg-secondary text-primary">
                    <Icon className="size-5" />
                </span>
                <CardTitle>{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent className="mt-auto">
                <Button asChild className="w-full" disabled={!href}>
                    <a href={href}>
                        <Download /> {label}
                    </a>
                </Button>
            </CardContent>
        </Card>
    );
}
