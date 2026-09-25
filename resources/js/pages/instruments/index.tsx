import { Link, useForm } from '@inertiajs/react';
import { Archive, ClipboardList, FileStack, ListChecks, Plus, Users } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { FormDialog } from '@/components/form-dialog';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { PaginationBar } from '@/components/pagination-bar';
import { SearchInput } from '@/components/search-input';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { fromNow } from '@/lib/format';
import { instrumentTypeTone, versionTone } from '@/lib/instrument';
import type { Option, Paginated } from '@/types';

interface InstrumentCard {
    id: number;
    code: string;
    name: string;
    description: string | null;
    type: string;
    type_label: string;
    respondent_label: string;
    versions_count: number;
    archived: boolean;
    updated_at: string;
    latest: { id: number; version: string; status: string; status_label: string; questions_count: number } | null;
    published_version: string | null;
    surveys_count: number;
}

interface Props {
    instruments: Paginated<InstrumentCard>;
    filters: Record<string, string | undefined>;
    types: Option[];
    respondentTypes: Option[];
    canManage: boolean;
}

export default function InstrumentsIndex({ instruments, filters, types, respondentTypes, canManage }: Props) {
    const { filters: query, setFilter } = useQueryFilters({
        search: filters.search ?? '',
        type: filters.type ?? 'all',
        respondent_type: filters.respondent_type ?? 'all',
        archived: filters.archived === '1' || filters.archived === 'true' ? '1' : '',
    });
    const [creating, setCreating] = useState(false);

    return (
        <>
            <PageHeader
                title="Instrumen Mutu"
                description="Susun instrumen Monev, survei, dan AMI tanpa bantuan developer. Setiap perubahan tercatat sebagai versi baru sehingga data historis tetap utuh."
                breadcrumbs={[{ label: 'e-Monev' }, { label: 'Instrumen' }]}
                actions={
                    canManage && (
                        <Button onClick={() => setCreating(true)}>
                            <Plus /> Instrumen baru
                        </Button>
                    )
                }
            />

            <div className="mb-5 flex flex-col gap-2 lg:flex-row lg:items-center">
                <SearchInput value={query.search} onChange={(v) => setFilter('search', v)} placeholder="Cari instrumen…" />
                <div className="grid grid-cols-2 gap-2 sm:flex">
                    <SelectField value={query.type} onChange={(v) => setFilter('type', v)} options={types} allLabel="Semua jenis" className="sm:w-52" />
                    <SelectField value={query.respondent_type} onChange={(v) => setFilter('respondent_type', v)} options={respondentTypes} allLabel="Semua responden" className="sm:w-48" />
                </div>
                <label className="flex items-center gap-2 text-sm text-muted-foreground lg:ml-auto">
                    <Switch checked={query.archived === '1'} onCheckedChange={(v) => setFilter('archived', v ? '1' : '')} />
                    Tampilkan arsip
                </label>
            </div>

            {instruments.data.length === 0 ? (
                <div className="rounded-2xl border bg-card">
                    <EmptyState
                        icon={ClipboardList}
                        title="Belum ada instrumen"
                        description="Mulai dengan membuat instrumen Monev Pembelajaran, lalu susun bagian dan butir pertanyaannya."
                        action={
                            canManage && (
                                <Button onClick={() => setCreating(true)}>
                                    <Plus /> Instrumen baru
                                </Button>
                            )
                        }
                    />
                </div>
            ) : (
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 2xl:grid-cols-3">
                    {instruments.data.map((instrument) => (
                        <Link
                            key={instrument.id}
                            href={instrument.latest ? route('instrument-versions.show', instrument.latest.id) : route('instruments.show', instrument.id)}
                            className="group relative flex flex-col overflow-hidden rounded-2xl border bg-card p-5 transition hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-lg hover:shadow-primary/5"
                        >
                            <div className="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary via-primary/70 to-gold opacity-0 transition group-hover:opacity-100" />
                            <div className="flex items-start justify-between gap-3">
                                <StatusBadge tone={instrumentTypeTone[instrument.type] ?? 'neutral'} dot={false}>
                                    {instrument.type_label}
                                </StatusBadge>
                                {instrument.archived ? (
                                    <StatusBadge tone="neutral">
                                        <Archive className="size-3" /> Arsip
                                    </StatusBadge>
                                ) : (
                                    instrument.latest && (
                                        <StatusBadge tone={versionTone[instrument.latest.status]}>
                                            v{instrument.latest.version} · {instrument.latest.status_label}
                                        </StatusBadge>
                                    )
                                )}
                            </div>
                            <h3 className="mt-4 text-[16px] leading-snug font-bold group-hover:text-primary">{instrument.name}</h3>
                            <div className="mt-1 font-mono text-xs text-muted-foreground">{instrument.code}</div>
                            {instrument.description && <p className="mt-3 line-clamp-2 text-sm text-muted-foreground">{instrument.description}</p>}

                            <div className="mt-auto pt-5" />
                            <div className="grid grid-cols-3 gap-2 border-t pt-4 text-xs text-muted-foreground">
                                <span className="flex items-center gap-1.5">
                                    <ListChecks className="size-3.5" /> {instrument.latest?.questions_count ?? 0} butir
                                </span>
                                <span className="flex items-center gap-1.5">
                                    <FileStack className="size-3.5" /> {instrument.versions_count} versi
                                </span>
                                <span className="flex items-center gap-1.5">
                                    <Users className="size-3.5" /> {instrument.respondent_label}
                                </span>
                            </div>
                            <div className="mt-3 flex items-center justify-between text-[11px] text-muted-foreground">
                                <span>{instrument.published_version ? `Versi terbit: v${instrument.published_version}` : 'Belum ada versi terbit'}</span>
                                <span>Diubah {fromNow(instrument.updated_at)}</span>
                            </div>
                        </Link>
                    ))}
                </div>
            )}

            {instruments.last_page > 1 && (
                <div className="mt-4 rounded-2xl border bg-card">
                    <PaginationBar meta={instruments} />
                </div>
            )}

            {creating && <CreateInstrumentDialog types={types} respondentTypes={respondentTypes} onClose={() => setCreating(false)} />}
        </>
    );
}

function CreateInstrumentDialog({ types, respondentTypes, onClose }: { types: Option[]; respondentTypes: Option[]; onClose: () => void }) {
    const form = useForm({ code: '', name: '', type: 'monev_pembelajaran', respondent_type: 'mahasiswa', description: '' });

    return (
        <FormDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title="Instrumen baru"
            description="Versi 1.0 (draf) akan dibuat otomatis beserta satu bagian awal."
            onSubmit={() => form.post(route('instruments.store'))}
            processing={form.processing}
            submitLabel="Buat & susun"
        >
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <FormField label="Nama instrumen" error={form.errors.name} required className="sm:col-span-2">
                    <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="Monev Pembelajaran" autoFocus />
                </FormField>
                <FormField label="Kode" error={form.errors.code} required hint="Huruf, angka, strip. Mis. MONEV-PBM">
                    <Input value={form.data.code} onChange={(e) => form.setData('code', e.target.value.toUpperCase().replace(/\s+/g, '-'))} />
                </FormField>
                <FormField label="Jenis" error={form.errors.type} required>
                    <SelectField value={form.data.type} onChange={(v) => form.setData('type', v)} options={types} />
                </FormField>
                <FormField label="Responden" error={form.errors.respondent_type} required className="sm:col-span-2">
                    <SelectField value={form.data.respondent_type} onChange={(v) => form.setData('respondent_type', v)} options={respondentTypes} />
                </FormField>
                <FormField label="Deskripsi" error={form.errors.description} className="sm:col-span-2">
                    <Textarea rows={3} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} placeholder="Tujuan dan cakupan instrumen…" />
                </FormField>
            </div>
        </FormDialog>
    );
}
