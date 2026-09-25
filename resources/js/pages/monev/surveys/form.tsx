import { useForm } from '@inertiajs/react';
import { EyeOff, LoaderCircle, Save } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { Combobox, MultiCombobox } from '@/components/combobox';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { SwitchField } from '@/components/switch-field';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { Option } from '@/types';

interface SurveyForm {
    id: number;
    title: string;
    description: string | null;
    instrument_version_id: number;
    academic_period_id: number | null;
    mode: string;
    respondent_type: string;
    status: string;
    is_anonymous: boolean;
    min_responses: number | null;
    starts_at: string;
    ends_at: string;
    study_program_ids: string[];
}

interface Props {
    survey: SurveyForm | null;
    instrumentVersions: (Option & { instrument_type: string; respondent_type: string })[];
    periods: Option[];
    activePeriodId: number | null;
    studyPrograms: Option[];
    modes: Option[];
    respondentTypes: Option[];
    defaultMinResponses: number;
}

const localDate = (offsetDays: number, hour: number) => {
    const date = new Date();
    date.setDate(date.getDate() + offsetDays);
    date.setHours(hour, 0, 0, 0);
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:00`;
};

export default function SurveyFormPage({ survey, instrumentVersions, periods, activePeriodId, studyPrograms, modes, respondentTypes, defaultMinResponses }: Props) {
    const locked = survey !== null && survey.status !== 'draft';
    const form = useForm({
        title: survey?.title ?? '',
        description: survey?.description ?? '',
        instrument_version_id: survey ? String(survey.instrument_version_id) : '',
        academic_period_id: survey?.academic_period_id ? String(survey.academic_period_id) : activePeriodId ? String(activePeriodId) : '',
        mode: survey?.mode ?? 'teaching_evaluation',
        respondent_type: survey?.respondent_type ?? 'mahasiswa',
        is_anonymous: survey?.is_anonymous ?? true,
        min_responses: survey?.min_responses ?? ('' as number | ''),
        starts_at: survey?.starts_at ?? localDate(0, 8),
        ends_at: survey?.ends_at ?? localDate(21, 23),
        study_program_ids: survey?.study_program_ids ?? ([] as string[]),
    });

    const pickVersion = (value: string) => {
        const version = instrumentVersions.find((v) => String(v.value) === value);
        form.setData((data) => ({
            ...data,
            instrument_version_id: value,
            mode: version?.instrument_type === 'monev_pembelajaran' ? 'teaching_evaluation' : 'general',
            respondent_type: version?.respondent_type === 'dosen' ? 'dosen' : 'mahasiswa',
            title: data.title || (version ? version.label.split(' — ')[0] + (activePeriodId ? ` ${periods.find((p) => p.value === activePeriodId)?.label ?? ''}` : '') : ''),
        }));
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (survey) form.put(route('surveys.update', survey.id));
        else form.post(route('surveys.store'));
    };

    return (
        <div className="mx-auto max-w-4xl">
            <PageHeader
                title={survey ? 'Ubah kegiatan Monev' : 'Kegiatan Monev baru'}
                description="Responden, eligibility, dan anonimitas diatur di sini. Setelah dibuka, instrumen dan sasaran dikunci."
                breadcrumbs={[{ label: 'e-Monev' }, { label: 'Kegiatan Monev', href: route('surveys.index') }, { label: survey ? 'Ubah' : 'Baru' }]}
            />
            {locked && (
                <Alert className="mb-5 border-info/30 bg-info-soft">
                    <AlertDescription className="text-info">Kegiatan sudah dibuka — hanya judul, deskripsi, tanggal berakhir, dan ambang minimum respons yang dapat diubah.</AlertDescription>
                </Alert>
            )}
            <form onSubmit={submit} className="flex flex-col gap-5">
                <Section title="Instrumen & jenis kegiatan" description="Hanya versi instrumen yang sudah terbit yang dapat dipilih.">
                    <FormField label="Versi instrumen" error={form.errors.instrument_version_id} required className="sm:col-span-2">
                        <Combobox value={form.data.instrument_version_id} onChange={pickVersion} options={instrumentVersions} placeholder="Pilih instrumen terbit…" invalid={!!form.errors.instrument_version_id} />
                    </FormField>
                    <FormField label="Judul kegiatan" error={form.errors.title} required className="sm:col-span-2">
                        <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="Monev Pembelajaran Ganjil 2026/2027" />
                    </FormField>
                    <FormField label="Petunjuk untuk responden" error={form.errors.description} className="sm:col-span-2">
                        <Textarea rows={3} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} placeholder="Isilah dengan jujur sesuai pengalaman Anda selama perkuliahan…" />
                    </FormField>
                </Section>

                <fieldset disabled={locked} className="contents">
                    <Section title="Sasaran responden" description="Eligibility dihitung otomatis — mahasiswa hanya mengevaluasi dosen di kelas yang diikutinya.">
                        <FormField label="Jenis kegiatan" error={form.errors.mode} required>
                            <SelectField value={form.data.mode} onChange={(v) => form.setData('mode', v)} options={modes} disabled={locked} />
                        </FormField>
                        <FormField label="Responden" error={form.errors.respondent_type} required>
                            <SelectField
                                value={form.data.respondent_type}
                                onChange={(v) => form.setData('respondent_type', v)}
                                options={respondentTypes}
                                disabled={locked || form.data.mode === 'teaching_evaluation'}
                            />
                        </FormField>
                        <FormField label="Periode akademik" error={form.errors.academic_period_id} required={form.data.mode === 'teaching_evaluation'}>
                            <SelectField value={form.data.academic_period_id} onChange={(v) => form.setData('academic_period_id', v)} options={periods} disabled={locked} />
                        </FormField>
                        <FormField label="Cakupan program studi" hint="Kosongkan untuk seluruh prodi." error={form.errors.study_program_ids}>
                            <MultiCombobox values={form.data.study_program_ids} onChange={(v) => form.setData('study_program_ids', v)} options={studyPrograms} placeholder="Semua prodi" />
                        </FormField>
                    </Section>
                </fieldset>

                <Section title="Jadwal pengisian" description="Pengingat otomatis dikirim menjelang tanggal berakhir.">
                    <FormField label="Dibuka mulai" error={form.errors.starts_at} required>
                        <Input type="datetime-local" value={form.data.starts_at} onChange={(e) => form.setData('starts_at', e.target.value)} disabled={locked} />
                    </FormField>
                    <FormField label="Berakhir" error={form.errors.ends_at} required>
                        <Input type="datetime-local" value={form.data.ends_at} onChange={(e) => form.setData('ends_at', e.target.value)} />
                    </FormField>
                </Section>

                <Section title="Privasi & ambang hasil">
                    <div className="sm:col-span-2">
                        <SwitchField
                            checked={form.data.is_anonymous}
                            onChange={(v) => form.setData('is_anonymous', v)}
                            label={
                                <span className="inline-flex items-center gap-1.5">
                                    <EyeOff className="size-4 text-primary" /> Respons anonim (disarankan)
                                </span>
                            }
                            description="Status pengisian dipisahkan dari isi evaluasi. Tidak ada pihak — termasuk LPM — yang dapat melihat jawaban per mahasiswa."
                        />
                    </div>
                    <FormField label="Minimum respons per dosen/kelas" error={form.errors.min_responses} hint={`Kosongkan untuk memakai pengaturan sistem (${defaultMinResponses}).`}>
                        <Input
                            type="number"
                            min={1}
                            value={form.data.min_responses}
                            onChange={(e) => form.setData('min_responses', e.target.value === '' ? '' : Number(e.target.value))}
                            placeholder={String(defaultMinResponses)}
                        />
                    </FormField>
                </Section>

                <div className="flex justify-end gap-2">
                    <Button type="button" variant="outline" onClick={() => history.back()}>
                        Batal
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? <LoaderCircle className="animate-spin" /> : <Save />} {survey ? 'Simpan perubahan' : 'Simpan sebagai draf'}
                    </Button>
                </div>
            </form>
        </div>
    );
}

function Section({ title, description, children }: { title: string; description?: string; children: ReactNode }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                {description && <CardDescription>{description}</CardDescription>}
            </CardHeader>
            <CardContent className="grid grid-cols-1 gap-4 sm:grid-cols-2">{children}</CardContent>
        </Card>
    );
}
