import { Link, useForm } from '@inertiajs/react';
import { BookMarked, Layers, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { FormDialog } from '@/components/form-dialog';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { SwitchField } from '@/components/switch-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { versionTone } from '@/lib/accreditation';
import { formatDate } from '@/lib/format';
import type { Option } from '@/types';

interface Instrument {
    id: number;
    code: string;
    name: string;
    description: string | null;
    is_active: boolean;
    accreditation_body_id: number;
    body: string | null;
    body_name: string | null;
    versions: { id: number; version: string; status: string; status_label: string; criteria_count: number; indicators_count: number; periods_count: number; published_at: string | null }[];
}

export default function AccreditationInstruments({ instruments, bodies }: { instruments: Instrument[]; bodies: Option[] }) {
    const [editing, setEditing] = useState<Instrument | 'new' | null>(null);

    return (
        <>
            <PageHeader
                title="Instrumen Akreditasi"
                description="Instrumen LAM berversi: kriteria, sub-kriteria, indikator, bobot, dan bukti yang dibutuhkan. Versi berlaku terkunci; perubahan dibuat pada versi baru."
                breadcrumbs={[{ label: 'Akreditasi' }, { label: 'Instrumen LAM' }]}
                actions={
                    <Button onClick={() => setEditing('new')}>
                        <Plus /> Instrumen baru
                    </Button>
                }
            />

            {instruments.length === 0 ? (
                <Card>
                    <EmptyState icon={BookMarked} title="Belum ada instrumen akreditasi" description="Tambahkan instrumen LAMDIK/LAMGAMA lalu impor kriteria & indikatornya dari Excel." />
                </Card>
            ) : (
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    {instruments.map((instrument) => {
                        const current = instrument.versions.find((v) => v.status === 'published') ?? instrument.versions[0];
                        return (
                            <Card key={instrument.id} className="gap-0 py-0">
                                <div className="flex items-start gap-3 border-b px-5 py-4">
                                    <span className="flex h-11 min-w-11 shrink-0 items-center justify-center rounded-xl bg-gold-soft px-2 text-[11px] font-extrabold text-gold-foreground">{instrument.body ?? '—'}</span>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2">
                                            <span className="font-mono text-xs font-bold text-primary">{instrument.code}</span>
                                            {!instrument.is_active && <StatusBadge tone="neutral">Nonaktif</StatusBadge>}
                                        </div>
                                        <div className="font-bold">{instrument.name}</div>
                                        {instrument.description && <p className="mt-0.5 line-clamp-2 text-xs text-muted-foreground">{instrument.description}</p>}
                                    </div>
                                    <Button variant="ghost" size="icon-sm" onClick={() => setEditing(instrument)} aria-label="Ubah instrumen">
                                        <Pencil />
                                    </Button>
                                </div>
                                <CardContent className="flex flex-col gap-2 py-4">
                                    {instrument.versions.map((version) => (
                                        <Link key={version.id} href={route('accreditation.versions.show', version.id)} className="flex items-center gap-3 rounded-xl border px-3 py-2.5 transition hover:border-primary/30 hover:bg-secondary/30">
                                            <Layers className="size-4 text-muted-foreground" />
                                            <span className="font-bold">v{version.version}</span>
                                            <StatusBadge tone={versionTone[version.status]}>{version.status_label}</StatusBadge>
                                            <span className="ml-auto text-xs text-muted-foreground">
                                                {version.criteria_count} kriteria · {version.indicators_count} indikator · {version.periods_count} periode
                                            </span>
                                        </Link>
                                    ))}
                                    {current?.published_at && <p className="text-[11px] text-muted-foreground">Berlaku sejak {formatDate(current.published_at)}</p>}
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            )}

            {editing && <InstrumentForm instrument={editing === 'new' ? null : editing} bodies={bodies} onClose={() => setEditing(null)} />}
        </>
    );
}

function InstrumentForm({ instrument, bodies, onClose }: { instrument: Instrument | null; bodies: Option[]; onClose: () => void }) {
    const form = useForm({
        accreditation_body_id: instrument ? String(instrument.accreditation_body_id) : '',
        code: instrument?.code ?? '',
        name: instrument?.name ?? '',
        description: instrument?.description ?? '',
        is_active: instrument?.is_active ?? true,
    });

    const submit = () => {
        const options = { preserveScroll: true, onSuccess: onClose };
        if (instrument) form.put(route('accreditation.instruments.update', instrument.id), options);
        else form.post(route('accreditation.instruments.store'), options);
    };

    return (
        <FormDialog open onOpenChange={(o) => !o && onClose()} title={instrument ? 'Ubah instrumen' : 'Instrumen akreditasi baru'} description={instrument ? undefined : 'Versi 1.0 (draf) dibuat otomatis.'} onSubmit={submit} processing={form.processing}>
            <div className="grid gap-4">
                <FormField label="Lembaga akreditasi" error={form.errors.accreditation_body_id} required>
                    <SelectField value={form.data.accreditation_body_id} onChange={(v) => form.setData('accreditation_body_id', v)} options={bodies} placeholder="Pilih LAM" />
                </FormField>
                <FormField label="Kode" error={form.errors.code} required>
                    <Input value={form.data.code} onChange={(e) => form.setData('code', e.target.value.toUpperCase())} placeholder="IAPS-LAMDIK" />
                </FormField>
                <FormField label="Nama instrumen" error={form.errors.name} required>
                    <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                </FormField>
                <FormField label="Deskripsi" error={form.errors.description}>
                    <Textarea rows={3} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                </FormField>
                {instrument && <SwitchField label="Aktif" checked={form.data.is_active} onChange={(v) => form.setData('is_active', v)} />}
            </div>
        </FormDialog>
    );
}
