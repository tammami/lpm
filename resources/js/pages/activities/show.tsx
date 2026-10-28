import { useForm } from '@inertiajs/react';
import { CalendarCheck2, CircleDot, Paperclip, Pencil, Trash2 } from 'lucide-react';
import { type ReactNode, useState } from 'react';
import { ActivityForm } from '@/components/activities/activity-form';
import { ConfirmDialog } from '@/components/confirm-dialog';
import type { EvidenceFormOptions } from '@/components/evidence/evidence-upload-dialog';
import { LinkedEvidenceList } from '@/components/evidence/linked-evidence';
import { FormDialog } from '@/components/form-dialog';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { type ActivityFormOptions, type ActivityStatus, activityStatusTone, dateRange, formatRupiah, type LpmActivity } from '@/lib/activities';
import { formatDateTime } from '@/lib/format';

interface Props extends ActivityFormOptions {
    activity: LpmActivity & { creator: string | null; created_at: string | null; updated_at: string | null };
    evidenceOptions: EvidenceFormOptions | null;
    canManage: boolean;
}

export default function ActivityShow({ activity, evidenceOptions, canManage, ...options }: Props) {
    const [editing, setEditing] = useState(false);
    const [statusTarget, setStatusTarget] = useState<ActivityStatus | null>(null);

    return (
        <>
            <PageHeader
                title={activity.title}
                description={`${activity.code} · ${activity.category_label} · ${dateRange(activity.start_date, activity.end_date)}`}
                breadcrumbs={[{ label: 'Program Kerja LPM' }, { label: 'Timeline Kegiatan', href: route('activities.index', { year: activity.year }) }, { label: activity.code }]}
                meta={
                    <div className="flex flex-wrap gap-1.5">
                        <StatusBadge tone={activityStatusTone[activity.status]}>{activity.status_label}</StatusBadge>
                        {activity.is_overdue && <StatusBadge tone="danger">Terlambat</StatusBadge>}
                    </div>
                }
                actions={
                    canManage && (
                        <>
                            {activity.status === 'planned' && (
                                <Button variant="outline" onClick={() => setStatusTarget('ongoing')}>
                                    <CircleDot /> Mulai berlangsung
                                </Button>
                            )}
                            {activity.status !== 'done' && (
                                <Button onClick={() => setStatusTarget('done')}>
                                    <CalendarCheck2 /> Tandai terlaksana
                                </Button>
                            )}
                            <Button variant="outline" onClick={() => setEditing(true)}>
                                <Pencil /> Ubah
                            </Button>
                            <ConfirmDialog
                                trigger={
                                    <Button variant="ghost" size="icon" aria-label="Hapus kegiatan" className="text-danger-foreground hover:text-danger-foreground">
                                        <Trash2 />
                                    </Button>
                                }
                                title="Hapus kegiatan ini?"
                                description="Kegiatan dihapus dari timeline. Dokumen buktinya tetap tersimpan di repositori Dokumen Bukti."
                                href={route('activities.destroy', activity.id)}
                                confirmLabel="Hapus"
                            />
                        </>
                    )
                }
            />

            <div className="grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
                <Card className="gap-0 p-5">
                    <h2 className="mb-4 text-sm font-bold">Rincian kegiatan</h2>
                    <dl className="grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                        <Item label="Tanggal">{dateRange(activity.start_date, activity.end_date)}</Item>
                        <Item label="Jenis">{activity.category_label}</Item>
                        <Item label="Penanggung jawab">{activity.pic}</Item>
                        <Item label="Tempat">{activity.location}</Item>
                        <Item label="Sasaran / peserta">{activity.participants}</Item>
                        <Item label="Anggaran">{formatRupiah(activity.budget)}</Item>
                        <Item label="Deskripsi" wide>
                            {activity.description && <p className="whitespace-pre-line">{activity.description}</p>}
                        </Item>
                        <Item label="Target / luaran" wide>
                            {activity.target_output && <p className="whitespace-pre-line">{activity.target_output}</p>}
                        </Item>
                        <Item label="Catatan realisasi / hasil" wide>
                            {activity.realization_note && <p className="whitespace-pre-line">{activity.realization_note}</p>}
                        </Item>
                    </dl>
                    <p className="mt-5 border-t pt-3 text-xs text-muted-foreground">
                        Dicatat {activity.creator ? `oleh ${activity.creator} ` : ''}· diperbarui {formatDateTime(activity.updated_at)}
                    </p>
                </Card>

                <Card className="gap-0 p-5">
                    <h2 className="mb-1 flex items-center gap-2 text-sm font-bold">
                        <Paperclip className="size-4" /> Bukti pelaksanaan
                    </h2>
                    <p className="mb-4 text-xs text-muted-foreground">
                        Undangan, daftar hadir, notulen, foto, laporan, atau SK. Berkas tersimpan di repositori Dokumen Bukti sehingga dapat dipakai lagi untuk akreditasi.
                    </p>
                    <LinkedEvidenceList
                        items={activity.evidence ?? []}
                        mapTo={{ type: 'lpm_activity', id: activity.id, label: `${activity.code} — ${activity.title}` }}
                        options={evidenceOptions ?? undefined}
                        canAttach={canManage}
                        emptyText={canManage ? 'Belum ada bukti. Unggah dokumen atau pilih dari repositori.' : 'Belum ada bukti yang diunggah.'}
                    />
                </Card>
            </div>

            {editing && <ActivityForm {...options} activity={activity} onClose={() => setEditing(false)} />}
            {statusTarget && <StatusDialog activity={activity} status={statusTarget} label={options.statuses.find((s) => s.value === statusTarget)?.label ?? statusTarget} onClose={() => setStatusTarget(null)} />}
        </>
    );
}

function StatusDialog({ activity, status, label, onClose }: { activity: LpmActivity; status: ActivityStatus; label: string; onClose: () => void }) {
    const form = useForm({ status, realization_note: activity.realization_note ?? '' });

    return (
        <FormDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={`Ubah status: ${label}`}
            description={status === 'done' ? 'Catat hasil singkat kegiatan, lalu unggah buktinya di panel Bukti pelaksanaan.' : undefined}
            onSubmit={() => form.post(route('activities.status', activity.id), { preserveScroll: true, onSuccess: onClose })}
            processing={form.processing}
            submitLabel="Simpan status"
        >
            <FormField label="Catatan realisasi / hasil" error={form.errors.realization_note}>
                <Textarea rows={4} value={form.data.realization_note} onChange={(e) => form.setData('realization_note', e.target.value)} placeholder="Mis. Diikuti 35 peserta; menghasilkan draf manual mutu." autoFocus />
            </FormField>
        </FormDialog>
    );
}

function Item({ label, children, wide }: { label: string; children: ReactNode; wide?: boolean }) {
    return (
        <div className={wide ? 'sm:col-span-2' : undefined}>
            <dt className="text-xs font-semibold text-muted-foreground">{label}</dt>
            <dd className="mt-0.5">{children || <span className="text-muted-foreground">—</span>}</dd>
        </div>
    );
}
