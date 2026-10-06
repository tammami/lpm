import { useForm } from '@inertiajs/react';
import { BadgeCheck, ExternalLink, LoaderCircle, Undo2 } from 'lucide-react';
import { type ReactNode, useState } from 'react';
import { FormField } from '@/components/form-field';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import { type ScientificWork, workHref } from '@/lib/research';

export const workStatusTone = { pending: 'warning', verified: 'success', revision: 'danger' } as const;

/**
 * Rincian satu karya ilmiah beserta aksi verifikasi (bila diizinkan).
 */
export function WorkDetail({ work, canVerify, onClose }: { work: ScientificWork; canVerify: boolean; onClose: () => void }) {
    const [returning, setReturning] = useState(false);
    const form = useForm({ decision: 'verified', note: work.verification_note ?? '' });
    const href = workHref(work.url);

    const decide = (decision: 'verified' | 'revision') => {
        form.transform((data) => ({ ...data, decision }));
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
                    <dl className="grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                        <Item label="Jenis karya" wide>
                            {work.type_label}
                            {work.rank_label && <span className="ml-2 font-semibold text-primary">{work.rank_label}</span>}
                        </Item>
                        <Item label="Jurnal / penerbit / kegiatan" wide>
                            {work.venue}
                        </Item>
                        <Item label="Tahun">{work.year}</Item>
                        <Item label="Volume / halaman">{work.volume}</Item>
                        <Item label="ISSN / ISBN / nomor">{work.identifier}</Item>
                        <Item label="Peran penulis">{work.author_role_label}</Item>
                        <Item label="Penulis lain" wide>
                            {work.co_authors}
                        </Item>
                        <Item label="Asal kegiatan">{work.source_label}</Item>
                        <Item label="Sumber dana">{work.funding_label}</Item>
                        <Item label="DOI / tautan" wide>
                            {href && (
                                <a href={href} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 break-all text-primary hover:underline">
                                    {work.url} <ExternalLink className="size-3.5 shrink-0" />
                                </a>
                            )}
                        </Item>
                    </dl>

                    {work.verified_at && (
                        <div className="mt-5 rounded-xl border bg-muted/30 p-3 text-xs text-muted-foreground">
                            {work.status === 'revision' ? 'Dikembalikan' : 'Diverifikasi'} oleh {work.verifier ?? '—'} · {formatDateTime(work.verified_at)}
                            {work.verification_note && <p className="mt-1 text-sm text-foreground">{work.verification_note}</p>}
                        </div>
                    )}

                    {canVerify && returning && (
                        <FormField label="Catatan untuk dosen" error={form.errors.note} required className="mt-5">
                            <Textarea rows={3} value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} placeholder="Mis. tautan DOI tidak bisa dibuka, peringkat SINTA tidak sesuai…" autoFocus />
                        </FormField>
                    )}
                </div>

                {canVerify && (
                    <SheetFooter className="flex-row justify-end gap-2 border-t bg-muted/40 px-6 py-4">
                        {returning ? (
                            <>
                                <Button variant="outline" onClick={() => setReturning(false)} disabled={form.processing}>
                                    Batal
                                </Button>
                                <Button variant="destructive" onClick={() => decide('revision')} disabled={form.processing}>
                                    {form.processing ? <LoaderCircle className="animate-spin" /> : <Undo2 />} Kembalikan ke dosen
                                </Button>
                            </>
                        ) : (
                            <>
                                <Button variant="outline" onClick={() => setReturning(true)}>
                                    <Undo2 /> Minta perbaikan
                                </Button>
                                <Button onClick={() => decide('verified')} disabled={form.processing || work.status === 'verified'}>
                                    {form.processing ? <LoaderCircle className="animate-spin" /> : <BadgeCheck />} {work.status === 'verified' ? 'Sudah terverifikasi' : 'Verifikasi'}
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
