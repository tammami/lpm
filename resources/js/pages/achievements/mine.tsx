import { CircleAlert, ExternalLink, FileText, Medal, Pencil, Plus, Trash2, Trophy, UserRoundX } from 'lucide-react';
import { useState } from 'react';
import { AchievementForm } from '@/components/achievements/achievement-form';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { type Achievement, type AchievementFormOptions, achievementStatusTone } from '@/lib/achievements';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';

interface Props extends AchievementFormOptions {
    student: { name: string; nim: string; study_program: string | null } | null;
    achievements: Achievement[];
}

const editable = (achievement: Achievement) => achievement.status === 'pending' || achievement.status === 'revision';

export default function MyAchievements({ student, achievements, ...options }: Props) {
    const [editing, setEditing] = useState<Achievement | 'new' | null>(null);

    if (!student) {
        return (
            <>
                <PageHeader title="Prestasi Saya" />
                <Card>
                    <EmptyState icon={UserRoundX} title="Akun belum terhubung dengan data mahasiswa" description="Hubungi admin prodi agar akun Anda ditautkan ke data mahasiswa." />
                </Card>
            </>
        );
    }

    const verified = achievements.filter((a) => a.status === 'verified').length;
    const flagged = achievements.filter((a) => a.status === 'revision');

    return (
        <>
            <PageHeader
                title="Prestasi Saya"
                description="Catat prestasi akademik maupun nonakademik Anda. Bidang kemahasiswaan memverifikasinya, lalu prestasi terverifikasi masuk rekap kampus untuk akreditasi."
                actions={
                    <Button onClick={() => setEditing('new')}>
                        <Plus /> Catat prestasi
                    </Button>
                }
            />

            <div className="mb-5 grid grid-cols-3 gap-3">
                {[
                    { label: 'Total', value: achievements.length },
                    { label: 'Terverifikasi', value: verified },
                    { label: 'Menunggu', value: achievements.filter((a) => a.status === 'pending').length },
                ].map((stat) => (
                    <div key={stat.label} className="rounded-2xl border border-white/75 surface-glass px-4 py-3">
                        <div className="text-xs font-semibold text-muted-foreground">{stat.label}</div>
                        <div className="mt-0.5 text-2xl font-extrabold tabular">{stat.value}</div>
                    </div>
                ))}
            </div>

            {flagged.length > 0 && (
                <div className="mb-5 flex items-start gap-3 rounded-2xl border border-warning/40 bg-warning-soft px-4 py-3 text-sm text-gold-foreground">
                    <CircleAlert className="mt-0.5 size-4 shrink-0" />
                    <div>
                        <b>{flagged.length} prestasi perlu diperbaiki.</b> Baca catatan bidang kemahasiswaan, lalu ubah datanya agar diperiksa ulang.
                    </div>
                </div>
            )}

            {achievements.length === 0 ? (
                <Card>
                    <EmptyState
                        icon={Trophy}
                        title="Belum ada prestasi"
                        description="Juara lomba, medali, hibah, delegasi, atau penghargaan lain — dari tingkat kampus sampai internasional."
                        action={
                            <Button onClick={() => setEditing('new')}>
                                <Plus /> Catat prestasi pertama
                            </Button>
                        }
                    />
                </Card>
            ) : (
                <div className="flex flex-col gap-3">
                    {achievements.map((achievement) => (
                        <Card key={achievement.id} className={cn('gap-0 p-4 sm:p-5', achievement.status === 'revision' && 'border-warning/50', achievement.status === 'rejected' && 'border-destructive/30')}>
                            <div className="flex items-start gap-3">
                                <span className={cn('flex size-11 shrink-0 items-center justify-center rounded-xl', achievement.status === 'verified' ? 'bg-grad-orange text-white' : 'bg-gold-soft text-gold-foreground')}>
                                    <Medal className="size-5" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                                        <span className="font-bold text-gold-foreground">{achievement.achievement}</span>
                                        <span className="text-muted-foreground">
                                            · {achievement.level_label} · {achievement.category_label}
                                        </span>
                                    </div>
                                    <h3 className="mt-0.5 font-semibold leading-snug">{achievement.title}</h3>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        {achievement.organizer} · {formatDate(achievement.achieved_on)} · {achievement.participation_label}
                                    </p>
                                    <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                                        {achievement.certificate_url && (
                                            <a href={achievement.certificate_url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 font-semibold text-primary hover:underline">
                                                <FileText className="size-3.5" /> Sertifikat
                                            </a>
                                        )}
                                        {achievement.evidence_url && (
                                            <a href={achievement.evidence_url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 font-semibold text-primary hover:underline">
                                                Tautan bukti <ExternalLink className="size-3" />
                                            </a>
                                        )}
                                    </div>
                                    {achievement.verification_note && achievement.status !== 'verified' && (
                                        <p className={cn('mt-2 rounded-lg px-3 py-2 text-sm', achievement.status === 'rejected' ? 'bg-danger-soft text-danger-foreground' : 'bg-warning-soft text-gold-foreground')}>
                                            <b>Catatan kemahasiswaan:</b> {achievement.verification_note}
                                        </p>
                                    )}
                                </div>
                            </div>
                            <div className="mt-3 flex items-center justify-between gap-2 border-t pt-3">
                                <StatusBadge tone={achievementStatusTone[achievement.status]}>{achievement.status_label}</StatusBadge>
                                <div className="flex items-center gap-1">
                                    {editable(achievement) && (
                                        <Button variant="ghost" size="sm" onClick={() => setEditing(achievement)}>
                                            <Pencil /> Ubah
                                        </Button>
                                    )}
                                    {achievement.status !== 'verified' && (
                                        <ConfirmDialog
                                            trigger={
                                                <Button variant="ghost" size="icon-sm" aria-label="Hapus prestasi" className="text-danger-foreground hover:text-danger-foreground">
                                                    <Trash2 />
                                                </Button>
                                            }
                                            title="Hapus prestasi ini?"
                                            description={`"${achievement.title}" beserta sertifikatnya akan dihapus.`}
                                            href={route('achievements.mine.destroy', achievement.id)}
                                            confirmLabel="Hapus"
                                        />
                                    )}
                                </div>
                            </div>
                        </Card>
                    ))}
                </div>
            )}

            {editing !== null && <AchievementForm {...options} achievement={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
        </>
    );
}
