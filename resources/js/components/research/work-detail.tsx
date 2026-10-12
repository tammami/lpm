import { useForm } from '@inertiajs/react';
import { BadgeCheck, Ban, Copy, ExternalLink, GraduationCap, LoaderCircle, TriangleAlert, Undo2, UserRound } from 'lucide-react';
import { type ReactNode, useState } from 'react';
import { FormField } from '@/components/form-field';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import { doiHref, type ScientificWork, workStatusTone, yesNo } from '@/lib/research';

type Decision = 'revision' | 'invalid' | 'duplicate';

const decisions: Record<Decision, { label: string; action: string; placeholder: string; icon: typeof Undo2 }> = {
    revision: { label: 'Valid dengan perbaikan', action: 'Kembalikan ke dosen', placeholder: 'Mis. tautan bukti tidak bisa dibuka, indeksasi tidak sesuai…', icon: Undo2 },
    invalid: { label: 'Tidak valid', action: 'Nyatakan tidak valid', placeholder: 'Mis. bukan karya dosen ini, jurnal tidak sesuai klaim…', icon: Ban },
    duplicate: { label: 'Duplikat', action: 'Tandai duplikat', placeholder: 'Mis. sama dengan karya yang dicatat oleh …', icon: Copy },
};

/**
 * Rincian satu karya beserta aksi verifikasi LPM (bila diizinkan).
 */
export function WorkDetail({ work, canVerify, onClose }: { work: ScientificWork; canVerify: boolean; onClose: () => void }) {
    const [deciding, setDeciding] = useState<Decision | null>(null);
    const form = useForm({ decision: 'verified', note: work.verification_note ?? '' });
    const DecisionIcon = deciding ? decisions[deciding].icon : null;

    const decide = (decision: 'verified' | Decision) => {
        form.transform((data) => ({ ...data, decision, note: decision === 'verified' ? '' : data.note }));
        form.post(route('research.works.verify', work.id), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Sheet open onOpenChange={(open) => !open && onClose()}>
            <SheetContent className="w-full gap-0 p-0 sm:max-w-xl">
                <SheetHeader className="border-b px-6 py-5">
                    <div className="mb-1">
                        <StatusBadge tone={workStatusTone[work.status]}>{work.status_label}</StatusBadge>
                    </div>
                    <SheetTitle className="text-lg leading-snug">{work.title}</SheetTitle>
                    <SheetDescription>
                        {work.lecturer ? `${work.lecturer} · ` : ''}
                        {work.study_program}
                    </SheetDescription>
                </SheetHeader>

                <div className="min-h-0 flex-1 overflow-y-auto px-6 py-5">
                    {work.possible_duplicates && work.possible_duplicates.length > 0 && (
                        <div className="mb-5 rounded-xl border border-warning/40 bg-warning-soft px-3.5 py-3 text-sm text-gold-foreground">
                            <div className="flex items-center gap-2 font-semibold">
                                <TriangleAlert className="size-4" /> Kemungkinan duplikat
                            </div>
                            <ul className="mt-1.5 space-y-1 text-xs">
                                {work.possible_duplicates.map((other) => (
                                    <li key={other.id}>
                                        “{other.title}” — {other.lecturer ?? '—'} ({other.status_label})
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    <dl className="grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                        <Item label="Jenis / subjenis" wide>
                            {work.category_label} · {work.type_label}
                        </Item>
                        {work.indexation && work.indexation !== 'not_applicable' && (
                            <Item label="Indeksasi / akreditasi" wide>
                                {work.indexation_label}
                                {work.rank_label && work.rank_label !== 'Tidak Berlaku' && <span className="ml-2 font-semibold text-primary">{work.rank_label}</span>}
                            </Item>
                        )}
                        <Item label="Jurnal / penerbit / media" wide>
                            {work.venue}
                        </Item>
                        <Item label="Tahun">{work.year}</Item>
                        <Item label="Volume / no. / edisi">{work.volume}</Item>
                        <Item label="ISSN / ISBN">{work.identifier}</Item>
                        {work.ip_status && (
                            <Item label="HKI">
                                {work.ip_status_label}
                                {work.ip_number && ` · ${work.ip_number}`}
                            </Item>
                        )}
                        <Item label="Peran dosen">{work.author_role_label}</Item>
                        <Item label="Jumlah penulis">{work.author_count}</Item>
                    </dl>

                    <div className="mt-5 rounded-xl border bg-muted/20 p-3.5">
                        <div className="mb-2 text-xs font-semibold text-muted-foreground">Penulis yang ditandai</div>
                        {work.lecturers.length + work.students.length === 0 ? (
                            <p className="text-sm text-muted-foreground">Tidak ada dosen rekan atau mahasiswa yang ditandai.</p>
                        ) : (
                            <ul className="flex flex-col gap-1.5 text-sm">
                                {work.lecturers.map((lecturer) => (
                                    <li key={`l-${lecturer.id}`} className="flex items-center gap-2">
                                        <UserRound className="size-4 shrink-0 text-primary" />
                                        <span className="font-medium">{lecturer.name}</span>
                                        <span className="truncate text-xs text-muted-foreground">Dosen · {lecturer.study_program ?? '—'}</span>
                                    </li>
                                ))}
                                {work.students.map((student, index) => (
                                    <li key={`s-${index}`} className="flex items-center gap-2">
                                        <GraduationCap className="size-4 shrink-0 text-gold-foreground" />
                                        <span className="font-medium">{student.name}</span>
                                        <span className="truncate text-xs text-muted-foreground">
                                            Mahasiswa{student.nim ? ` · ${student.nim}` : ''}
                                            {student.study_program ? ` · ${student.study_program}` : student.student_id ? '' : ' · di luar data'}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                        {work.co_authors && <p className="mt-2 text-xs text-muted-foreground">Penulis lain: {work.co_authors}</p>}
                    </div>

                    <dl className="mt-5 grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                        <Item label="Link publikasi" wide>
                            <ExternalAnchor href={work.url} />
                        </Item>
                        <Item label="DOI" wide>
                            {work.doi && <ExternalAnchor href={doiHref(work.doi)} label={work.doi} />}
                        </Item>
                        <Item label="Link bukti" wide>
                            <ExternalAnchor href={work.evidence_url} />
                        </Item>
                        <Item label="Sumber dana">{work.funding_label}</Item>
                        <Item label="Asal kegiatan">{work.source_label}</Item>
                        <Item label="Relevan dengan prodi">{yesNo(work.relevant_to_program)}</Item>
                        <Item label="Sesuai roadmap penelitian">{yesNo(work.relevant_to_roadmap)}</Item>
                    </dl>

                    {work.verified_at && (
                        <div className="mt-5 rounded-xl border bg-muted/30 p-3 text-xs text-muted-foreground">
                            {work.status_label} — oleh {work.verifier ?? '—'} · {formatDateTime(work.verified_at)}
                            {work.verification_note && <p className="mt-1 text-sm text-foreground">{work.verification_note}</p>}
                        </div>
                    )}

                    {canVerify && deciding && (
                        <FormField label={`Catatan untuk dosen — ${decisions[deciding].label}`} error={form.errors.note} required className="mt-5">
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
                                        <Button key={decision} variant="outline" size="sm" onClick={() => setDeciding(decision)} disabled={work.status === decision}>
                                            <Icon /> {decisions[decision].label}
                                        </Button>
                                    );
                                })}
                                <Button size="sm" onClick={() => decide('verified')} disabled={form.processing || work.status === 'verified'}>
                                    {form.processing ? <LoaderCircle className="animate-spin" /> : <BadgeCheck />} {work.status === 'verified' ? 'Sudah valid' : 'Valid'}
                                </Button>
                            </>
                        )}
                    </SheetFooter>
                )}
            </SheetContent>
        </Sheet>
    );
}

function ExternalAnchor({ href, label }: { href: string | null; label?: string }) {
    if (!href) return null;

    return (
        <a href={href} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 break-all text-primary hover:underline">
            {label ?? href} <ExternalLink className="size-3.5 shrink-0" />
        </a>
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
