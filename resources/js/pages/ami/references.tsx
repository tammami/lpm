import { useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { type Column, DataTable } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { RowActions } from '@/components/row-actions';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { SwitchField } from '@/components/switch-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { severityTone } from '@/lib/ami';
import type { Option } from '@/types';

interface Standard {
    id: number;
    code: string;
    name: string;
    category: string;
    statement: string | null;
    indicator: string | null;
    target: string | null;
    reference: string | null;
    is_active: boolean;
    sort_order: number;
    findings_count: number;
}
interface Severity {
    id: number;
    code: string;
    name: string;
    description: string | null;
    color: string;
    requires_corrective_action: boolean;
    default_due_days: number;
    sort_order: number;
    is_active: boolean;
}
interface RootCause {
    id: number;
    name: string;
    description: string | null;
    sort_order: number;
    is_active: boolean;
}

export default function References({ standards, categories, severities, rootCauses }: { standards: Standard[]; categories: Option[]; severities: Severity[]; rootCauses: RootCause[] }) {
    const [standard, setStandard] = useState<Standard | 'new' | null>(null);
    const [severity, setSeverity] = useState<Severity | 'new' | null>(null);
    const [rootCause, setRootCause] = useState<RootCause | 'new' | null>(null);
    const categoryLabel = (value: string) => categories.find((c) => c.value === value)?.label ?? value;

    const columns: Column<Standard>[] = [
        { key: 'code', header: 'Kode', className: 'w-24', cell: (row) => <span className="font-mono text-xs font-bold text-primary">{row.code}</span> },
        {
            key: 'name',
            header: 'Standar',
            cell: (row) => (
                <div className="max-w-2xl">
                    <div className="font-semibold">{row.name}</div>
                    {row.statement && <div className="line-clamp-2 text-xs text-muted-foreground">{row.statement}</div>}
                </div>
            ),
        },
        { key: 'category', header: 'Kelompok', cell: (row) => <StatusBadge tone="primary" dot={false}>{categoryLabel(row.category)}</StatusBadge> },
        { key: 'target', header: 'Target', cell: (row) => <span className="text-xs">{row.target ?? '—'}</span> },
        { key: 'findings', header: 'Temuan', cell: (row) => <span className="tabular">{row.findings_count}</span> },
        {
            key: 'actions',
            header: '',
            className: 'w-12',
            cell: (row) => (
                <RowActions>
                    <DropdownMenuItem onSelect={() => setStandard(row)}>
                        <Pencil /> Ubah
                    </DropdownMenuItem>
                    <ConfirmDialog
                        trigger={
                            <DropdownMenuItem variant="destructive" onSelect={(e) => e.preventDefault()}>
                                <Trash2 /> Hapus
                            </DropdownMenuItem>
                        }
                        title={`Hapus ${row.code}?`}
                        href={route('ami.standards.destroy', row.id)}
                        confirmLabel="Hapus"
                    />
                </RowActions>
            ),
        },
    ];

    return (
        <>
            <PageHeader title="Standar & Referensi AMI" description="Standar SPMI, kategori temuan, dan klasifikasi akar masalah ditetapkan oleh LPM — tidak ditanam di kode." breadcrumbs={[{ label: 'Audit Mutu Internal' }, { label: 'Standar & Referensi' }]} />
            <Tabs defaultValue="standards">
                <TabsList className="mb-5">
                    <TabsTrigger value="standards">Standar SPMI ({standards.length})</TabsTrigger>
                    <TabsTrigger value="severities">Kategori temuan</TabsTrigger>
                    <TabsTrigger value="root-causes">Akar masalah</TabsTrigger>
                </TabsList>
                <TabsContent value="standards">
                    <div className="mb-3 flex justify-end">
                        <Button onClick={() => setStandard('new')}>
                            <Plus /> Tambah standar
                        </Button>
                    </div>
                    <DataTable columns={columns} data={standards} rowKey={(row) => row.id} />
                </TabsContent>
                <TabsContent value="severities">
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        {severities.map((item) => (
                            <Card key={item.id}>
                                <CardContent className="flex items-start gap-3">
                                    <StatusBadge tone={severityTone[item.color] ?? 'neutral'}>{item.code}</StatusBadge>
                                    <div className="flex-1">
                                        <div className="font-bold">{item.name}</div>
                                        <p className="mt-0.5 text-sm text-muted-foreground">{item.description}</p>
                                        <div className="mt-2 flex flex-wrap gap-2 text-xs">
                                            <StatusBadge tone={item.requires_corrective_action ? 'danger' : 'neutral'} dot={false}>
                                                {item.requires_corrective_action ? 'Wajib tindakan koreksi' : 'Tindakan opsional'}
                                            </StatusBadge>
                                            <StatusBadge tone="neutral" dot={false}>
                                                Tenggat {item.default_due_days} hari
                                            </StatusBadge>
                                        </div>
                                    </div>
                                    <Button variant="ghost" size="icon-sm" onClick={() => setSeverity(item)} aria-label="Ubah">
                                        <Pencil />
                                    </Button>
                                </CardContent>
                            </Card>
                        ))}
                        <button type="button" onClick={() => setSeverity('new')} className="flex min-h-28 items-center justify-center gap-2 rounded-2xl border-2 border-dashed text-sm font-semibold text-muted-foreground hover:border-primary/40 hover:text-primary">
                            <Plus className="size-4" /> Kategori temuan baru
                        </button>
                    </div>
                </TabsContent>
                <TabsContent value="root-causes">
                    <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                        {rootCauses.map((item) => (
                            <Card key={item.id}>
                                <CardContent className="flex items-center justify-between gap-2">
                                    <div>
                                        <div className="font-semibold">{item.name}</div>
                                        {item.description && <div className="text-xs text-muted-foreground">{item.description}</div>}
                                    </div>
                                    <Button variant="ghost" size="icon-sm" onClick={() => setRootCause(item)} aria-label="Ubah">
                                        <Pencil />
                                    </Button>
                                </CardContent>
                            </Card>
                        ))}
                        <button type="button" onClick={() => setRootCause('new')} className="flex min-h-20 items-center justify-center gap-2 rounded-2xl border-2 border-dashed text-sm font-semibold text-muted-foreground hover:border-primary/40 hover:text-primary">
                            <Plus className="size-4" /> Tambah
                        </button>
                    </div>
                </TabsContent>
            </Tabs>
            {standard && <StandardForm standard={standard === 'new' ? null : standard} categories={categories} onClose={() => setStandard(null)} />}
            {severity && <SeverityForm severity={severity === 'new' ? null : severity} onClose={() => setSeverity(null)} />}
            {rootCause && <RootCauseForm rootCause={rootCause === 'new' ? null : rootCause} onClose={() => setRootCause(null)} />}
        </>
    );
}

function StandardForm({ standard, categories, onClose }: { standard: Standard | null; categories: Option[]; onClose: () => void }) {
    const form = useForm({
        code: standard?.code ?? '',
        name: standard?.name ?? '',
        category: standard?.category ?? 'pendidikan',
        statement: standard?.statement ?? '',
        indicator: standard?.indicator ?? '',
        target: standard?.target ?? '',
        reference: standard?.reference ?? '',
        sort_order: standard?.sort_order ?? 0,
        is_active: standard?.is_active ?? true,
    });
    const submit = () => {
        const options = { preserveScroll: true, onSuccess: onClose };
        if (standard) form.put(route('ami.standards.update', standard.id), options);
        else form.post(route('ami.standards.store'), options);
    };

    return (
        <FormDialog open onOpenChange={(o) => !o && onClose()} title={standard ? 'Ubah standar' : 'Tambah standar'} onSubmit={submit} processing={form.processing} size="lg">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
                <FormField label="Kode" error={form.errors.code} required>
                    <Input value={form.data.code} onChange={(e) => form.setData('code', e.target.value.toUpperCase())} />
                </FormField>
                <FormField label="Nama standar" error={form.errors.name} required className="sm:col-span-3">
                    <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                </FormField>
                <FormField label="Kelompok" error={form.errors.category} required className="sm:col-span-2">
                    <SelectField value={form.data.category} onChange={(v) => form.setData('category', v)} options={categories} />
                </FormField>
                <FormField label="Target" error={form.errors.target} className="sm:col-span-2">
                    <Input value={form.data.target} onChange={(e) => form.setData('target', e.target.value)} />
                </FormField>
                <FormField label="Pernyataan standar" error={form.errors.statement} className="sm:col-span-4">
                    <Textarea rows={3} value={form.data.statement} onChange={(e) => form.setData('statement', e.target.value)} />
                </FormField>
                <FormField label="Indikator" error={form.errors.indicator} className="sm:col-span-4">
                    <Textarea rows={2} value={form.data.indicator} onChange={(e) => form.setData('indicator', e.target.value)} />
                </FormField>
                <FormField label="Rujukan" error={form.errors.reference} className="sm:col-span-3">
                    <Input value={form.data.reference} onChange={(e) => form.setData('reference', e.target.value)} placeholder="Dokumen SPMI / SN-Dikti" />
                </FormField>
                <FormField label="Urutan">
                    <Input type="number" value={form.data.sort_order} onChange={(e) => form.setData('sort_order', Number(e.target.value))} />
                </FormField>
            </div>
        </FormDialog>
    );
}

function SeverityForm({ severity, onClose }: { severity: Severity | null; onClose: () => void }) {
    const form = useForm({
        code: severity?.code ?? '',
        name: severity?.name ?? '',
        description: severity?.description ?? '',
        color: severity?.color ?? 'warning',
        requires_corrective_action: severity?.requires_corrective_action ?? true,
        default_due_days: severity?.default_due_days ?? 30,
        sort_order: severity?.sort_order ?? 0,
        is_active: severity?.is_active ?? true,
    });

    return (
        <FormDialog
            open
            onOpenChange={(o) => !o && onClose()}
            title={severity ? 'Ubah kategori temuan' : 'Kategori temuan baru'}
            onSubmit={() => form.post(route('ami.severities.save', severity?.id ?? undefined), { preserveScroll: true, onSuccess: onClose })}
            processing={form.processing}
        >
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <FormField label="Kode" error={form.errors.code} required>
                    <Input value={form.data.code} onChange={(e) => form.setData('code', e.target.value.toUpperCase())} />
                </FormField>
                <FormField label="Nama" error={form.errors.name} required className="sm:col-span-2">
                    <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                </FormField>
                <FormField label="Warna" className="sm:col-span-2">
                    <SelectField
                        value={form.data.color}
                        onChange={(v) => form.setData('color', v)}
                        options={[
                            { value: 'danger', label: 'Merah' },
                            { value: 'warning', label: 'Kuning' },
                            { value: 'info', label: 'Biru' },
                            { value: 'neutral', label: 'Abu-abu' },
                        ]}
                    />
                </FormField>
                <FormField label="Tenggat (hari)" error={form.errors.default_due_days}>
                    <Input type="number" value={form.data.default_due_days} onChange={(e) => form.setData('default_due_days', Number(e.target.value))} />
                </FormField>
                <FormField label="Keterangan" className="sm:col-span-3">
                    <Textarea rows={2} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                </FormField>
                <div className="sm:col-span-3">
                    <SwitchField
                        checked={form.data.requires_corrective_action}
                        onChange={(v) => form.setData('requires_corrective_action', v)}
                        label="Wajib tindakan koreksi & verifikasi"
                        description="Bila tidak, temuan dapat ditutup tanpa tindakan koreksi (mis. observasi)."
                    />
                </div>
            </div>
        </FormDialog>
    );
}

function RootCauseForm({ rootCause, onClose }: { rootCause: RootCause | null; onClose: () => void }) {
    const form = useForm({ name: rootCause?.name ?? '', description: rootCause?.description ?? '', sort_order: rootCause?.sort_order ?? 0, is_active: rootCause?.is_active ?? true });

    return (
        <FormDialog
            open
            onOpenChange={(o) => !o && onClose()}
            title={rootCause ? 'Ubah kategori akar masalah' : 'Kategori akar masalah baru'}
            onSubmit={() => form.post(route('ami.root-causes.save', rootCause?.id ?? undefined), { preserveScroll: true, onSuccess: onClose })}
            processing={form.processing}
        >
            <div className="grid gap-4">
                <FormField label="Nama" error={form.errors.name} required>
                    <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                </FormField>
                <FormField label="Keterangan">
                    <Textarea rows={2} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                </FormField>
            </div>
        </FormDialog>
    );
}
