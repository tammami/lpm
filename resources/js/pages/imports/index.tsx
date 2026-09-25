import { Link, useForm } from '@inertiajs/react';
import { BookOpen, ClipboardList, Download, FileSpreadsheet, GraduationCap, LoaderCircle, School, Upload, Users, UsersRound } from 'lucide-react';
import { useState } from 'react';
import { type Column, DataTable } from '@/components/data-table';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { EnumBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { fromNow } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Option, Paginated } from '@/types';

interface ImporterDef {
    type: string;
    label: string;
    description: string;
    columns: { key: string; label: string; required: boolean; example: string; note: string }[];
}

export interface ImportJobRow {
    id: number;
    type: string;
    type_label: string;
    original_name: string;
    status: string;
    total_rows: number;
    valid_rows: number;
    invalid_rows: number;
    duplicate_rows: number;
    imported_rows: number;
    failure_message: string | null;
    user: string | null;
    result: { created: number; updated: number; skipped: number } | null;
    update_existing: boolean;
    created_at: string;
    completed_at: string | null;
}

export const importStatusLabels: Record<string, string> = {
    uploaded: 'Diunggah',
    validated: 'Menunggu konfirmasi',
    queued: 'Dalam antrean',
    importing: 'Sedang diimpor',
    completed: 'Selesai',
    failed: 'Gagal',
};

const icons: Record<string, typeof Users> = {
    mahasiswa: GraduationCap,
    dosen: Users,
    mata_kuliah: BookOpen,
    penugasan_mengajar: School,
    peserta_kelas: UsersRound,
    butir_instrumen: ClipboardList,
};

const order = ['mahasiswa', 'dosen', 'mata_kuliah', 'penugasan_mengajar', 'peserta_kelas', 'butir_instrumen'];

export default function ImportsIndex({ importers, jobs, draftVersions }: { importers: ImporterDef[]; jobs: Paginated<ImportJobRow>; draftVersions: Option[] }) {
    const sorted = [...importers].sort((a, b) => order.indexOf(a.type) - order.indexOf(b.type));
    const [selected, setSelected] = useState(sorted[0]?.type ?? '');
    const current = sorted.find((i) => i.type === selected);
    const form = useForm({ type: selected, file: null as File | null, instrument_version_id: '' });

    const choose = (type: string) => {
        setSelected(type);
        form.setData('type', type);
        form.clearErrors();
    };

    const columns: Column<ImportJobRow>[] = [
        {
            key: 'file',
            header: 'File',
            cell: (row) => (
                <Link href={route('imports.show', row.id)} className="group flex items-center gap-2.5">
                    <FileSpreadsheet className="size-5 text-primary" />
                    <div>
                        <div className="font-semibold group-hover:text-primary">{row.original_name}</div>
                        <div className="text-xs text-muted-foreground">
                            {row.type_label} · {row.user} · {fromNow(row.created_at)}
                        </div>
                    </div>
                </Link>
            ),
        },
        {
            key: 'rows',
            header: 'Baris',
            cell: (row) => (
                <span className="text-xs text-muted-foreground tabular">
                    {row.total_rows} total · <span className="text-primary">{row.valid_rows} valid</span> · <span className="text-destructive">{row.invalid_rows} error</span>
                </span>
            ),
        },
        { key: 'status', header: 'Status', cell: (row) => <EnumBadge value={row.status === 'validated' ? 'pending' : row.status === 'failed' ? 'rejected' : row.status} label={importStatusLabels[row.status] ?? row.status} /> },
    ];

    return (
        <>
            <PageHeader
                title="Impor Data"
                description="Excel menjadi alat impor, bukan pusat sistem. Setiap file divalidasi & dipratinjau sebelum benar-benar disimpan."
                breadcrumbs={[{ label: 'Data' }, { label: 'Impor Data' }]}
            />
            <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
                <div>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {sorted.map((importer, index) => {
                            const Icon = icons[importer.type] ?? FileSpreadsheet;
                            const active = importer.type === selected;
                            return (
                                <button
                                    key={importer.type}
                                    type="button"
                                    onClick={() => choose(importer.type)}
                                    className={cn(
                                        'relative flex flex-col items-start gap-2 rounded-2xl border bg-card p-4 text-left transition',
                                        active ? 'border-primary ring-2 ring-primary/15' : 'hover:border-primary/30',
                                    )}
                                >
                                    <span className="absolute top-3 right-3 font-mono text-[11px] font-bold text-muted-foreground">{index + 1}</span>
                                    <span className={cn('flex size-10 items-center justify-center rounded-xl', active ? 'bg-primary text-gold' : 'bg-secondary text-primary')}>
                                        <Icon className="size-5" />
                                    </span>
                                    <span className="text-sm font-bold">{importer.label}</span>
                                    <span className="text-xs leading-relaxed text-muted-foreground">{importer.description}</span>
                                </button>
                            );
                        })}
                    </div>
                    <p className="mt-3 text-xs text-muted-foreground">Urutan disarankan: Mahasiswa & Dosen → Mata Kuliah → Kelas & Dosen Pengampu → Peserta Kelas.</p>

                    <div className="mt-6">
                        <h3 className="mb-3 text-sm font-bold">Riwayat impor</h3>
                        <DataTable columns={columns} data={jobs.data} pagination={jobs} rowKey={(row) => row.id} emptyTitle="Belum ada riwayat impor" />
                    </div>
                </div>

                {current && (
                    <Card className="h-fit xl:sticky xl:top-24">
                        <CardHeader>
                            <CardTitle>Impor {current.label}</CardTitle>
                            <CardDescription>Unduh template, isi datanya, lalu unggah kembali.</CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-5">
                            <Button variant="outline" asChild>
                                <a href={route('imports.template', current.type)}>
                                    <Download /> Unduh template {current.label.toLowerCase()}.xlsx
                                </a>
                            </Button>
                            <div className="rounded-xl bg-muted/50 p-3">
                                <div className="mb-2 text-xs font-bold text-muted-foreground uppercase">Kolom</div>
                                <div className="flex flex-wrap gap-1.5">
                                    {current.columns.map((column) => (
                                        <span
                                            key={column.key}
                                            title={column.note}
                                            className={cn('rounded-md px-2 py-0.5 text-[11px] font-semibold', column.required ? 'bg-primary text-primary-foreground' : 'bg-card text-muted-foreground ring-1 ring-border')}
                                        >
                                            {column.label}
                                        </span>
                                    ))}
                                </div>
                                <p className="mt-2 text-[11px] text-muted-foreground">Kolom hijau wajib diisi.</p>
                            </div>
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    form.post(route('imports.store'), { forceFormData: true });
                                }}
                                className="flex flex-col gap-4"
                            >
                                {current.type === 'butir_instrumen' && (
                                    <FormField label="Versi instrumen tujuan" error={form.errors.instrument_version_id} required hint="Hanya versi berstatus draf.">
                                        <SelectField value={form.data.instrument_version_id} onChange={(v) => form.setData('instrument_version_id', v)} options={draftVersions} placeholder="Pilih versi draf" />
                                    </FormField>
                                )}
                                <FormField label="File Excel / CSV" error={form.errors.file} required>
                                    <label
                                        className={cn(
                                            'flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-4 py-8 text-center transition hover:border-primary/40 hover:bg-secondary/40',
                                            form.data.file && 'border-primary/40 bg-secondary/40',
                                        )}
                                    >
                                        <Upload className="size-6 text-primary" />
                                        <span className="text-sm font-semibold">{form.data.file ? form.data.file.name : 'Klik untuk memilih file'}</span>
                                        <span className="text-xs text-muted-foreground">.xlsx atau .csv, maks. 10 MB</span>
                                        <input type="file" accept=".xlsx,.csv" className="sr-only" onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)} />
                                    </label>
                                </FormField>
                                <Button type="submit" disabled={!form.data.file || form.processing}>
                                    {form.processing ? <LoaderCircle className="animate-spin" /> : <Upload />} Unggah & validasi
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}
