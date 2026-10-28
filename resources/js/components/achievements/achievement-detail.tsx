import { useForm } from '@inertiajs/react';
import { BadgeCheck, Ban, ExternalLink, FileText, LoaderCircle, Undo2 } from 'lucide-react';
import { type ReactNode, useState } from 'react';
import { FormField } from '@/components/form-field';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { type Achievement, achievementStatusTone } from '@/lib/achievements';
import { formatDate, formatDateTime } from '@/lib/format';

type Decision = 'revision' | 'rejected';

const decisions: Record<Decision, { label: string; action: string; placeholder: string; icon: typeof Undo2 }> = {
    revision: { label: 'Perlu perbaikan', action: 'Kembalikan ke mahasiswa', placeholder: 'Mis. sertifikat tidak memuat nama Anda, tingkat lomba tidak sesuai…', icon: Undo2 },
    rejected: { label: 'Tolak', action: 'Tolak prestasi', placeholder: 'Mis. bukan kegiatan kompetitif, bukti tidak sah…', icon: Ban },
};

/**
 * Rincian prestasi dan aksi verifikasi bidang kemahasiswaan.
 */
export function AchievementDetail({ achievement, canVerify, onClose }: { achievement: Achievement; canVerify: boolean; onClose: () => void }) {
    const [deciding, setDeciding] = useState<Decision | null>(null);
    const form = useForm({ decision: 'verified', note: achievement.verification_note ?? '' });
    const DecisionIcon = deciding ? decisions[deciding].icon : null;

    const decide = (decision: 'verified' | Decision) => {
        form.transform((data) => ({ ...data, decision, note: decision === 'verified' ? '' : data.note }));
        form.post(route('achievements.verify', achievement.id), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Sheet open onOpenChange={(open) => !open && onClose()}>
            <SheetContent className="w-full gap-0 p-0 sm:max-w-xl">
                <SheetHeader className="border-b px-6 py-5">
                    <div className="mb-1">
                        <StatusBadge tone={achievementStatusTone[achievement.status]}>{achievement.status_label}</StatusBadge>
                    </div>
                    <SheetTitle className="text-lg leading-snug">{achievement.title}</SheetTitle>
                    <SheetDescription>
                        {achievement.student ? `${achievement.student.name} · ${achievement.student.nim} · ${achievement.student.study_program ?? '—'}` : '—'}
                    </SheetDescription>
                </SheetHeader>

                <div className="min-h-0 flex-1 overflow-y-auto px-6 py-5">
                    <dl className="grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                        <Item label="Prestasi yang dicapai">{achievement.achievement}</Item>
                        <Item label="Tingkat">{achievement.level_label}</Item>
                        <Item label="Jenis">{achievement.category_label}</Item>
                        <Item label="Keikutsertaan">{achievement.participation_label}</Item>
                        <Item label="Penyelenggara">{achievement.organizer}</Item>
                        <Item label="Tanggal diperoleh">{formatDate(achievement.achieved_on)}</Item>
                        <Item label="Keterangan" wide>
                            {achievement.description && <p className="whitespace-pre-line">{achievement.description}</p>}
                        </Item>
                        <Item label="Bukti" wide>
                            {(achievement.certificate_url || achievement.evidence_url) && (
                                <div className="flex flex-col gap-1.5">
                                    {achievement.certificate_url && (
                                        <a href={achievement.certificate_url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1.5 font-semibold text-primary hover:underline">
                                            <FileText className="size-4" /> {achievement.certificate_name ?? 'Sertifikat'}
                                        </a>
                                    )}
                                    {achievement.evidence_url && (
                                        <a href={achievement.evidence_url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 break-all text-primary hover:underline">
                                            {achievement.evidence_url} <ExternalLink className="size-3.5 shrink-0" />
                                        </a>
                                    )}
                                </div>
                            )}
                        </Item>
                    </dl>

                    {achievement.verified_at && (
                        <div className="mt-5 rounded-xl border bg-muted/30 p-3 text-xs text-muted-foreground">
                            {achievement.status_label} — oleh {achievement.verifier ?? '—'} · {formatDateTime(achievement.verified_at)}
                            {achievement.verification_note && <p className="mt-1 text-sm text-foreground">{achievement.verification_note}</p>}
                        </div>
                    )}

                    {canVerify && deciding && (
                        <FormField label={`Catatan untuk mahasiswa — ${decisions[deciding].label}`} error={form.errors.note} required className="mt-5">
                            <Textarea rows={3} value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} placeholder={decisions[deciding].placeholder} autoFocus />
                        </FormField>
                    )}
                </div>

                {canVerify && (
                    <SheetFooter className="flex-row flex-wrap justify-end gap-2 border-t bg-muted/40 px-6 py-4">
                        {deciding ? (
                            <>
                                <Button variant="outline" onClick={() => setDeciding(null)} disabled={form.processing}>
                                    Batal
                                </Button>
                                <Button variant={deciding === 'revision' ? 'default' : 'destructive'} onClick={() => decide(deciding)} disabled={form.processing}>
                                    {form.processing ? <LoaderCircle className="animate-spin" /> : DecisionIcon && <DecisionIcon />}
                                    {decisions[deciding].action}
                                </Button>
                            </>
                        ) : (
                            <>
                                {(Object.keys(decisions) as Decision[]).map((decision) => {
                                    const Icon = decisions[decision].icon;
                                    return (
                                        <Button key={decision} variant="outline" size="sm" onClick={() => setDeciding(decision)} disabled={achievement.status === decision}>
                                            <Icon /> {decisions[decision].label}
                                        </Button>
                                    );
                                })}
                                <Button size="sm" onClick={() => decide('verified')} disabled={form.processing || achievement.status === 'verified'}>
                                    {form.processing ? <LoaderCircle className="animate-spin" /> : <BadgeCheck />} {achievement.status === 'verified' ? 'Sudah terverifikasi' : 'Verifikasi'}
                                </Button>
                            </>
                        )}
                    </SheetFooter>
                )}
            </SheetContent>
        </Sheet>
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
