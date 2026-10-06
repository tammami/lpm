import { BookOpenText, CircleAlert, ExternalLink, Pencil, Plus, Trash2, UserRoundX } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { workStatusTone } from '@/components/research/work-detail';
import { WorkForm } from '@/components/research/work-form';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { type ScientificWork, type WorkFormOptions, workHref } from '@/lib/research';
import { cn } from '@/lib/utils';

interface Props extends WorkFormOptions {
    lecturer: { name: string; nidn: string | null; study_program: string | null } | null;
    works: ScientificWork[];
}

export default function MyResearch({ lecturer, works, ...options }: Props) {
    const [editing, setEditing] = useState<ScientificWork | 'new' | null>(null);
    const year = new Date().getFullYear();
    const revisions = works.filter((w) => w.status === 'revision');

    if (!lecturer) {
        return (
            <>
                <PageHeader title="Karya Ilmiah Saya" breadcrumbs={[{ label: 'Karya Ilmiah Saya' }]} />
                <Card>
                    <EmptyState icon={UserRoundX} title="Akun belum terhubung dengan data dosen" description="Hubungi admin prodi atau LPM agar akun Anda ditautkan ke data dosen." />
                </Card>
            </>
        );
    }

    const stats = [
        { label: 'Total karya tercatat', value: works.length },
        { label: `Karya tahun ${year}`, value: works.filter((w) => w.year === year).length },
        { label: 'Terverifikasi', value: works.filter((w) => w.status === 'verified').length },
        { label: 'Menunggu verifikasi', value: works.filter((w) => w.status === 'pending').length },
    ];

    return (
        <>
            <PageHeader
                title="Karya Ilmiah Saya"
                description={`${lecturer.name} · ${lecturer.study_program ?? '—'}. Isi data publikasi, buku, dan HKI Anda — LPM memakainya untuk rekap mutu dan akreditasi.`}
                breadcrumbs={[{ label: 'Karya Ilmiah Saya' }]}
                actions={
                    <Button onClick={() => setEditing('new')}>
                        <Plus /> Tambah karya
                    </Button>
                }
            />

            <div className="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                {stats.map((stat) => (
                    <div key={stat.label} className="rounded-2xl border border-white/75 surface-glass px-4 py-3.5">
                        <div className="text-xs font-semibold text-muted-foreground">{stat.label}</div>
                        <div className="mt-1 text-2xl font-extrabold tabular">{stat.value}</div>
                    </div>
                ))}
            </div>

            {revisions.length > 0 && (
                <div className="mb-6 flex items-start gap-3 rounded-2xl border border-destructive/25 bg-danger-soft px-4 py-3 text-sm text-danger-foreground">
                    <CircleAlert className="mt-0.5 size-4 shrink-0" />
                    <div>
                        <b>{revisions.length} karya perlu diperbaiki.</b> Baca catatan LPM pada karya bertanda merah, lalu ubah datanya. Karya akan diperiksa ulang.
                    </div>
                </div>
            )}

            {works.length === 0 ? (
                <Card>
                    <EmptyState
                        icon={BookOpenText}
                        title="Belum ada karya ilmiah"
                        description="Tambahkan artikel jurnal, prosiding, buku, atau HKI Anda. Tidak perlu mengunggah berkas — cukup data dan DOI/tautannya."
                        action={
                            <Button onClick={() => setEditing('new')}>
                                <Plus /> Tambah karya pertama
                            </Button>
                        }
                    />
                </Card>
            ) : (
                <div className="flex flex-col gap-3">
                    {works.map((work) => (
                        <WorkCard key={work.id} work={work} onEdit={() => setEditing(work)} />
                    ))}
                </div>
            )}

            {editing !== null && (
                <WorkForm
                    {...options}
                    work={editing === 'new' ? null : editing}
                    storeUrl={route('research.mine.store')}
                    updateUrl={(work) => route('research.mine.update', work.id)}
                    onClose={() => setEditing(null)}
                />
            )}
        </>
    );
}

function WorkCard({ work, onEdit }: { work: ScientificWork; onEdit: () => void }) {
    const href = workHref(work.url);

    return (
        <Card className={cn('gap-0 py-0', work.status === 'revision' && 'border-destructive/40')}>
            <div className="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between sm:p-5">
                <div className="min-w-0">
                    <div className="mb-1.5 flex flex-wrap items-center gap-2 text-xs">
                        <span className="rounded-md bg-secondary px-2 py-0.5 font-bold text-secondary-foreground tabular">{work.year}</span>
                        <span className="font-semibold text-muted-foreground">{work.type_label}</span>
                        {work.rank_label && <span className="font-bold text-primary">{work.rank_label}</span>}
                    </div>
                    <h3 className="font-semibold leading-snug">{work.title}</h3>
                    <p className="mt-0.5 text-sm text-muted-foreground">
                        {work.venue}
                        {work.volume && `, ${work.volume}`} · {work.author_role_label}
                    </p>
                    {href && (
                        <a href={href} target="_blank" rel="noreferrer" className="mt-1 inline-flex items-center gap-1 text-xs break-all text-primary hover:underline">
                            {work.url} <ExternalLink className="size-3 shrink-0" />
                        </a>
                    )}
                    {work.status === 'revision' && work.verification_note && (
                        <p className="mt-2 rounded-lg bg-danger-soft px-3 py-2 text-sm text-danger-foreground">
                            <b>Catatan LPM:</b> {work.verification_note}
                        </p>
                    )}
                </div>
                <div className="flex shrink-0 items-center gap-2">
                    <StatusBadge tone={workStatusTone[work.status]}>{work.status_label}</StatusBadge>
                    <Button variant="ghost" size="icon" onClick={onEdit} aria-label="Ubah">
                        <Pencil />
                    </Button>
                    {work.status !== 'verified' && (
                        <ConfirmDialog
                            trigger={
                                <Button variant="ghost" size="icon" aria-label="Hapus" className="text-destructive hover:text-destructive">
                                    <Trash2 />
                                </Button>
                            }
                            title="Hapus karya ini?"
                            description={`"${work.title}" akan dihapus dari data Anda.`}
                            href={route('research.mine.destroy', work.id)}
                            confirmLabel="Hapus"
                        />
                    )}
                </div>
            </div>
        </Card>
    );
}
