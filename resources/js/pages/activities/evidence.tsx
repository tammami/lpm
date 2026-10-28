import { Link } from '@inertiajs/react';
import { CalendarRange, ChevronRight, FileCheck2, FolderArchive, FolderOpen, Paperclip, TriangleAlert, Upload } from 'lucide-react';
import { useState } from 'react';
import { Combobox } from '@/components/combobox';
import { EmptyState } from '@/components/empty-state';
import { type EvidenceFormOptions, EvidenceUploadDialog } from '@/components/evidence/evidence-upload-dialog';
import { LinkedEvidenceList } from '@/components/evidence/linked-evidence';
import { FormDialog } from '@/components/form-dialog';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { SearchInput } from '@/components/search-input';
import { SelectField } from '@/components/select-field';
import { StatTile } from '@/components/stat-tile';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { activityStatusTone, dateRange, type LpmActivity } from '@/lib/activities';
import type { Option } from '@/types';

interface Props {
    activities: LpmActivity[];
    filters: Record<string, string | undefined>;
    years: Option[];
    stats: { documents: number; activities_with: number; total: number; done_without: number };
    categories: Option[];
    evidenceOptions: EvidenceFormOptions | null;
    canManage: boolean;
}

/**
 * Arsip bukti program kerja LPM: seluruh dokumen per kegiatan dalam satu tahun.
 */
export default function ActivityEvidence({ activities, filters, years, stats, categories, evidenceOptions, canManage }: Props) {
    const { filters: query, setFilter } = useQueryFilters({
        year: filters.year ?? String(new Date().getFullYear()),
        search: filters.search ?? '',
        category: filters.category ?? 'all',
        evidence: filters.evidence ?? 'all',
    });
    const [choosing, setChoosing] = useState(false);
    const [uploadFor, setUploadFor] = useState<LpmActivity | null>(null);

    return (
        <>
            <PageHeader
                title="Bukti Program Kerja LPM"
                description="Arsip dokumen pelaksanaan setiap kegiatan program kerja — undangan, daftar hadir, notulen, foto, laporan. Berkas tersimpan di repositori Dokumen Bukti."
                breadcrumbs={[{ label: 'Program Kerja LPM' }, { label: 'Bukti Program Kerja' }]}
                actions={
                    <>
                        <Button variant="outline" asChild>
                            <Link href={route('activities.index', { year: query.year })}>
                                <CalendarRange /> Timeline kegiatan
                            </Link>
                        </Button>
                        {canManage && evidenceOptions && (
                            <Button onClick={() => setChoosing(true)} disabled={stats.total === 0}>
                                <Upload /> Unggah bukti
                            </Button>
                        )}
                    </>
                }
            />

            <div className="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <StatTile label="Dokumen bukti" value={stats.documents} icon={FolderArchive} hint={`Tahun ${query.year}`} />
                <StatTile label="Kegiatan berbukti" value={`${stats.activities_with}/${stats.total}`} icon={FileCheck2} tone="info" />
                <StatTile label="Terlaksana tanpa bukti" value={stats.done_without} icon={TriangleAlert} tone={stats.done_without ? 'gold' : 'neutral'} hint="Segera lengkapi buktinya" />
                <StatTile label="Belum berbukti" value={stats.total - stats.activities_with} icon={FolderOpen} tone="neutral" hint="Termasuk yang belum berjalan" />
            </div>

            <Card className="mb-5 gap-0 p-4">
                <div className="flex flex-wrap items-center gap-2">
                    <SelectField value={query.year} onChange={(v) => setFilter('year', v)} options={years} label="Tahun program kerja" className="w-28" />
                    <SelectField value={query.category} onChange={(v) => setFilter('category', v)} options={categories} allLabel="Semua jenis" className="w-56" />
                    <SelectField
                        value={query.evidence}
                        onChange={(v) => setFilter('evidence', v)}
                        options={[
                            { value: 'has', label: 'Sudah ada bukti' },
                            { value: 'missing', label: 'Belum ada bukti' },
                        ]}
                        allLabel="Semua kegiatan"
                        className="w-44"
                    />
                    <SearchInput value={query.search} onChange={(v) => setFilter('search', v)} placeholder="Cari kegiatan atau judul dokumen…" className="min-w-56 flex-1" />
                </div>
            </Card>

            {activities.length === 0 ? (
                <Card>
                    <EmptyState
                        icon={FolderArchive}
                        title={stats.total === 0 ? `Belum ada kegiatan tahun ${query.year}` : 'Tidak ada kegiatan yang cocok'}
                        description={stats.total === 0 ? 'Tambahkan kegiatan di Timeline Kegiatan terlebih dahulu, lalu unggah buktinya di sini.' : 'Ubah filter atau kata kunci.'}
                    />
                </Card>
            ) : (
                <div className="flex flex-col gap-3">
                    {activities.map((activity) => (
                        <Card key={activity.id} className="gap-0 p-4 sm:p-5">
                            <div className="mb-3 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div className="min-w-0">
                                    <Link href={activity.show_url} className="group inline-flex items-center gap-1 font-semibold leading-snug hover:text-primary">
                                        {activity.title} <ChevronRight className="size-4 opacity-50 group-hover:opacity-100" />
                                    </Link>
                                    <div className="mt-0.5 text-xs text-muted-foreground">
                                        {activity.code} · {activity.category_label} · {dateRange(activity.start_date, activity.end_date)}
                                    </div>
                                </div>
                                <div className="flex shrink-0 items-center gap-1.5">
                                    <span className="inline-flex items-center gap-1 text-xs font-semibold text-muted-foreground">
                                        <Paperclip className="size-3.5" /> {activity.evidence?.length ?? 0}
                                    </span>
                                    <StatusBadge tone={activityStatusTone[activity.status]}>{activity.status_label}</StatusBadge>
                                </div>
                            </div>
                            <LinkedEvidenceList
                                items={activity.evidence ?? []}
                                mapTo={{ type: 'lpm_activity', id: activity.id, label: `${activity.code} — ${activity.title}` }}
                                options={evidenceOptions ?? undefined}
                                canAttach={canManage}
                                compact
                                emptyText={activity.status === 'done' ? 'Kegiatan sudah terlaksana tetapi belum ada bukti.' : 'Belum ada bukti.'}
                            />
                        </Card>
                    ))}
                </div>
            )}

            {choosing && <ChooseActivity activities={activities} onClose={() => setChoosing(false)} onChoose={(activity) => setUploadFor(activity)} />}
            {uploadFor && evidenceOptions && (
                <EvidenceUploadDialog
                    {...evidenceOptions}
                    mapTo={{ type: 'lpm_activity', id: uploadFor.id, label: `${uploadFor.code} — ${uploadFor.title}` }}
                    defaultTitle={`Bukti ${uploadFor.title}`}
                    onClose={() => setUploadFor(null)}
                />
            )}
        </>
    );
}

function ChooseActivity({ activities, onClose, onChoose }: { activities: LpmActivity[]; onClose: () => void; onChoose: (activity: LpmActivity) => void }) {
    const [selected, setSelected] = useState<string>('');
    const [error, setError] = useState<string | undefined>();
    const options = activities.map((activity) => ({ value: String(activity.id), label: activity.title, description: `${activity.code} · ${dateRange(activity.start_date, activity.end_date)}` }));

    const submit = () => {
        const activity = activities.find((item) => String(item.id) === selected);
        if (!activity) {
            setError('Pilih kegiatan yang buktinya akan diunggah.');
            return;
        }
        onClose();
        onChoose(activity);
    };

    return (
        <FormDialog open onOpenChange={(open) => !open && onClose()} title="Unggah bukti untuk kegiatan" description="Pilih kegiatan terlebih dahulu." onSubmit={submit} submitLabel="Lanjut">
            <FormField label="Kegiatan" error={error} required>
                <Combobox value={selected} onChange={setSelected} options={options} searchPlaceholder="Cari kegiatan…" placeholder="Pilih kegiatan…" />
            </FormField>
        </FormDialog>
    );
}
