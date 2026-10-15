import { BookOpenText, CircleAlert, ExternalLink, GraduationCap, Pencil, Plus, Trash2, UserRoundMinus, UserRoundX, UsersRound } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { WorkForm } from '@/components/research/work-form';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { doiHref, type ScientificWork, type WorkFormOptions, workStatusTone } from '@/lib/research';
import { cn } from '@/lib/utils';

interface Props extends WorkFormOptions {
    lecturer: { id: number; name: string; nidn: string | null; nuptk: string | null; study_program: string | null } | null;
    works: ScientificWork[];
    tagged: ScientificWork[];
    tab: 'own' | 'tagged';
}

/** Status yang meminta dosen bertindak, dengan catatan LPM. */
const needsAction = (work: ScientificWork) => ['revision', 'invalid', 'duplicate'].includes(work.status);

export default function MyResearch({ lecturer, works, tagged, tab: initialTab, ...options }: Props) {
    const [editing, setEditing] = useState<ScientificWork | 'new' | null>(null);
    const [tab, setTab] = useState(initialTab);
    const year = new Date().getFullYear();
    const flagged = works.filter(needsAction);

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

    const all = [...works, ...tagged];
    const stats = [
        { label: 'Total karya (termasuk bersama)', value: all.length },
        { label: `Karya tahun ${year}`, value: all.filter((w) => w.year === year).length },
        { label: 'Melibatkan mahasiswa', value: all.filter((w) => w.students.length > 0).length },
        { label: 'Valid (diverifikasi LPM)', value: all.filter((w) => w.status === 'verified').length },
    ];

    return (
        <>
            <PageHeader
                title="Karya Ilmiah Saya"
                description={`${lecturer.name} · ${lecturer.study_program ?? '—'}. Isi artikel, buku, HKI, dan karya lain Anda — tandai juga mahasiswa yang ikut menulis. LPM memakainya untuk rekap mutu dan akreditasi.`}
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

            {flagged.length > 0 && (
                <div className="mb-6 flex items-start gap-3 rounded-2xl border border-destructive/25 bg-danger-soft px-4 py-3 text-sm text-danger-foreground">
                    <CircleAlert className="mt-0.5 size-4 shrink-0" />
                    <div>
                        <b>{flagged.length} karya perlu ditindaklanjuti.</b> Baca catatan LPM pada karya bertanda merah, lalu perbaiki datanya. Karya akan diperiksa ulang.
                    </div>
                </div>
            )}

            <Tabs value={tab} onValueChange={(v) => setTab(v as Props['tab'])}>
                <TabsList className="mb-4">
                    <TabsTrigger value="own">Saya isi ({works.length})</TabsTrigger>
                    <TabsTrigger value="tagged">Saya ditandai ({tagged.length})</TabsTrigger>
                </TabsList>

                <TabsContent value="own">
                    {works.length === 0 ? (
                        <Card>
                            <EmptyState
                                icon={BookOpenText}
                                title="Belum ada karya"
                                description="Tambahkan artikel jurnal, prosiding, buku, HKI, atau karya lain Anda. Tidak perlu mengunggah berkas — cukup data dan tautan buktinya."
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
                </TabsContent>

                <TabsContent value="tagged">
                    {tagged.length === 0 ? (
                        <Card>
                            <EmptyState icon={UsersRound} title="Belum ada karya yang menandai Anda" description="Bila dosen lain mencatat karya bersama dan menandai Anda, karya itu muncul di sini — Anda tidak perlu mengisinya ulang." />
                        </Card>
                    ) : (
                        <div className="flex flex-col gap-3">
                            {tagged.map((work) => (
                                <WorkCard key={work.id} work={work} />
                            ))}
                        </div>
                    )}
                </TabsContent>
            </Tabs>

            {editing !== null && (
                <WorkForm
                    {...options}
                    work={editing === 'new' ? null : editing}
                    ownLecturerId={lecturer.id}
                    storeUrl={route('research.mine.store')}
                    updateUrl={(work) => route('research.mine.update', work.id)}
                    onClose={() => setEditing(null)}
                />
            )}
        </>
    );
}

/** Kartu karya. Tanpa `onEdit` = karya dosen lain yang menandai pengguna (hanya baca + "Bukan saya"). */
function WorkCard({ work, onEdit }: { work: ScientificWork; onEdit?: () => void }) {
    const links = [
        { label: 'Publikasi', href: work.url },
        { label: 'DOI', href: doiHref(work.doi) },
        { label: 'Bukti', href: work.evidence_url },
    ].filter((link) => link.href);

    return (
        <Card className={cn('gap-0 py-0', onEdit && needsAction(work) && 'border-destructive/40')}>
            <div className="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between sm:p-5">
                <div className="min-w-0">
                    <div className="mb-1.5 flex flex-wrap items-center gap-2 text-xs">
                        <span className="rounded-md bg-secondary px-2 py-0.5 font-bold text-secondary-foreground tabular">{work.year}</span>
                        <span className="font-semibold text-muted-foreground">{work.type_label}</span>
                        {work.indexation && work.indexation !== 'not_applicable' && <span className="font-bold text-primary">{work.indexation_label}</span>}
                    </div>
                    <h2 className="font-semibold leading-snug">{work.title}</h2>
                    <p className="mt-0.5 text-sm text-muted-foreground">
                        {work.venue}
                        {work.volume && `, ${work.volume}`}
                        {onEdit ? ` · ${work.author_role_label}` : ` · dicatat oleh ${work.lecturer ?? '—'}`}
                    </p>
                    {(work.students.length > 0 || work.lecturers.length > 0) && (
                        <div className="mt-2 flex flex-wrap gap-1.5 text-xs">
                            {work.lecturers.map((lecturer) => (
                                <span key={lecturer.id} className="inline-flex items-center gap-1 rounded-md bg-secondary px-2 py-0.5 text-secondary-foreground">
                                    <UsersRound className="size-3" /> {lecturer.name}
                                </span>
                            ))}
                            {work.students.map((student, index) => (
                                <span key={index} className="inline-flex items-center gap-1 rounded-md bg-gold-soft px-2 py-0.5 text-gold-foreground">
                                    <GraduationCap className="size-3" /> {student.name}
                                </span>
                            ))}
                        </div>
                    )}
                    {links.length > 0 && (
                        <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                            {links.map((link) => (
                                <a key={link.label} href={link.href!} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 font-semibold text-primary hover:underline">
                                    {link.label} <ExternalLink className="size-3 shrink-0" />
                                </a>
                            ))}
                        </div>
                    )}
                    {onEdit && needsAction(work) && work.verification_note && (
                        <p className="mt-2 rounded-lg bg-danger-soft px-3 py-2 text-sm text-danger-foreground">
                            <b>Catatan LPM:</b> {work.verification_note}
                        </p>
                    )}
                </div>
                <div className="flex shrink-0 items-center gap-2">
                    <StatusBadge tone={workStatusTone[work.status]}>{work.status_label}</StatusBadge>
                    {onEdit ? (
                        <>
                            <Button variant="ghost" size="icon" onClick={onEdit} aria-label="Ubah">
                                <Pencil />
                            </Button>
                            {work.status !== 'verified' && (
                                <ConfirmDialog
                                    trigger={
                                        <Button variant="ghost" size="icon" aria-label="Hapus" className="text-danger-foreground hover:text-danger-foreground">
                                            <Trash2 />
                                        </Button>
                                    }
                                    title="Hapus karya ini?"
                                    description={`"${work.title}" akan dihapus dari data Anda.`}
                                    href={route('research.mine.destroy', work.id)}
                                    confirmLabel="Hapus"
                                />
                            )}
                        </>
                    ) : (
                        <ConfirmDialog
                            trigger={
                                <Button variant="ghost" size="sm" className="text-muted-foreground">
                                    <UserRoundMinus /> Bukan saya
                                </Button>
                            }
                            title="Lepaskan tanda Anda?"
                            description={`Anda tidak lagi tercatat sebagai penulis bersama "${work.title}". Karya tetap tercatat atas nama ${work.lecturer ?? 'pengisinya'}.`}
                            href={route('research.mine.untag', work.id)}
                            confirmLabel="Lepaskan"
                        />
                    )}
                </div>
            </div>
        </Card>
    );
}
