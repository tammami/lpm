import { router, useForm, usePoll } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Copy, Download, FileCheck2, FileSpreadsheet, LoaderCircle, Play, Trash2, XCircle } from 'lucide-react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { PageHeader } from '@/components/page-header';
import { StatTile } from '@/components/stat-tile';
import { SwitchField } from '@/components/switch-field';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ImportJobRow } from './index';
import { importStatusLabels } from './index';

interface Props {
    job: ImportJobRow;
    columns: { key: string; label: string; required: boolean }[];
    preview: { columns: string[]; rows: { row: number; status: string; values: Record<string, string | null> }[] } | null;
    errors: { row_number: number; column: string | null; value: string | null; message: string }[];
    errorCount: number;
}

const steps = ['Unggah', 'Validasi', 'Pratinjau', 'Impor', 'Selesai'];

export default function ImportShow({ job, columns, preview, errors, errorCount }: Props) {
    const running = job.status === 'queued' || job.status === 'importing';
    usePoll(3000, {}, { autoStart: running });
    const form = useForm({ update_existing: job.update_existing });
    const current = job.status === 'completed' ? 4 : running ? 3 : job.status === 'validated' ? 2 : job.status === 'failed' ? 1 : 1;
    const labelFor = (key: string) => columns.find((c) => c.key === key)?.label ?? key;
    const shownColumns = (preview?.columns ?? []).filter((key) => columns.some((c) => c.key === key));

    return (
        <>
            <PageHeader
                title={`Impor ${job.type_label}`}
                description={job.original_name}
                breadcrumbs={[{ label: 'Impor Data', href: route('imports.index') }, { label: `#${job.id}` }]}
                actions={
                    !running && (
                        <ConfirmDialog
                            trigger={
                                <Button variant="outline">
                                    <Trash2 /> Hapus riwayat
                                </Button>
                            }
                            title="Hapus riwayat impor ini?"
                            description="Data yang sudah diimpor tidak ikut terhapus."
                            href={route('imports.destroy', job.id)}
                            confirmLabel="Hapus"
                        />
                    )
                }
            />

            <ol className="mb-6 grid grid-cols-5 gap-2">
                {steps.map((step, index) => (
                    <li key={step} className="flex flex-col gap-2">
                        <div className={cn('h-1.5 rounded-full', index <= current ? (job.status === 'failed' && index === current ? 'bg-destructive' : 'bg-primary') : 'bg-muted')} />
                        <span className={cn('text-xs font-semibold', index <= current ? 'text-foreground' : 'text-muted-foreground')}>{step}</span>
                    </li>
                ))}
            </ol>

            {job.status === 'failed' && (
                <Alert variant="destructive" className="mb-6">
                    <XCircle />
                    <AlertTitle>Proses gagal</AlertTitle>
                    <AlertDescription>{job.failure_message}</AlertDescription>
                </Alert>
            )}
            {running && (
                <Alert className="mb-6 border-info/30 bg-info-soft">
                    <LoaderCircle className="animate-spin text-info" />
                    <AlertTitle className="text-info">{importStatusLabels[job.status]}…</AlertTitle>
                    <AlertDescription className="text-info">{formatNumber(job.imported_rows)} baris telah diproses. Halaman diperbarui otomatis.</AlertDescription>
                </Alert>
            )}
            {job.status === 'completed' && job.result && (
                <Alert className="mb-6 border-success/30 bg-success-soft">
                    <CheckCircle2 className="text-primary" />
                    <AlertTitle>Impor selesai</AlertTitle>
                    <AlertDescription>
                        {job.result.created} data baru, {job.result.updated} diperbarui, {job.result.skipped} dilewati. {job.invalid_rows > 0 && `${job.invalid_rows} baris tidak valid tidak diimpor.`}
                    </AlertDescription>
                </Alert>
            )}

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatTile label="Total baris" value={formatNumber(job.total_rows)} icon={FileSpreadsheet} tone="neutral" />
                <StatTile label="Valid" value={formatNumber(job.valid_rows)} icon={FileCheck2} />
                <StatTile label="Sudah ada di sistem" value={formatNumber(job.duplicate_rows)} icon={Copy} tone="gold" hint="Akan diperbarui atau dilewati" />
                <StatTile label="Tidak valid" value={formatNumber(job.invalid_rows)} icon={AlertTriangle} tone={job.invalid_rows ? 'danger' : 'neutral'} hint="Tidak ikut diimpor" />
            </div>

            {job.status === 'validated' && (
                <Card className="mt-6 border-primary/25">
                    <CardContent className="flex flex-col gap-4 lg:flex-row lg:items-center">
                        <div className="flex-1">
                            <div className="font-bold">Siap diimpor: {formatNumber(job.valid_rows)} baris valid</div>
                            <p className="text-sm text-muted-foreground">Baris yang tidak valid akan dilewati. Perbaiki file lalu unggah ulang bila ingin mengimpor semuanya.</p>
                        </div>
                        {job.duplicate_rows > 0 && (
                            <div className="lg:w-80">
                                <SwitchField
                                    checked={form.data.update_existing}
                                    onChange={(v) => form.setData('update_existing', v)}
                                    label="Perbarui data yang sudah ada"
                                    description={`${job.duplicate_rows} baris cocok dengan data lama.`}
                                />
                            </div>
                        )}
                        <Button size="lg" disabled={form.processing || job.valid_rows === 0} onClick={() => form.post(route('imports.confirm', job.id))}>
                            {form.processing ? <LoaderCircle className="animate-spin" /> : <Play />} Konfirmasi impor
                        </Button>
                    </CardContent>
                </Card>
            )}

            {errorCount > 0 && (
                <Card className="mt-6 gap-0 overflow-hidden pb-0">
                    <CardHeader className="flex flex-row items-start justify-between pb-4">
                        <div>
                            <CardTitle className="text-destructive">Kesalahan validasi ({formatNumber(errorCount)})</CardTitle>
                            <CardDescription>Nomor baris sesuai baris pada file Excel.</CardDescription>
                        </div>
                        <Button variant="outline" size="sm" asChild>
                            <a href={route('imports.errors', job.id)}>
                                <Download /> Unduh laporan error
                            </a>
                        </Button>
                    </CardHeader>
                    <div className="max-h-96 overflow-auto">
                        <Table>
                            <TableHeader className="sticky top-0 bg-muted/90">
                                <TableRow>
                                    <TableHead className="w-24 px-6">Baris</TableHead>
                                    <TableHead>Kolom</TableHead>
                                    <TableHead>Nilai</TableHead>
                                    <TableHead>Keterangan</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {errors.map((error, index) => (
                                    <TableRow key={index}>
                                        <TableCell className="px-6 font-mono text-xs font-bold">Row {error.row_number}</TableCell>
                                        <TableCell className="text-xs">{error.column ? labelFor(error.column) : '—'}</TableCell>
                                        <TableCell className="font-mono text-xs text-muted-foreground">{error.value ?? '—'}</TableCell>
                                        <TableCell className="text-[13px] font-medium text-destructive">{error.message}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </Card>
            )}

            {preview && preview.rows.length > 0 && (
                <Card className="mt-6 gap-0 overflow-hidden pb-0">
                    <CardHeader className="pb-4">
                        <CardTitle>Pratinjau data</CardTitle>
                        <CardDescription>{preview.rows.length} baris pertama.</CardDescription>
                    </CardHeader>
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-muted/50">
                                    <TableHead className="px-6">Baris</TableHead>
                                    <TableHead>Status</TableHead>
                                    {shownColumns.map((key) => (
                                        <TableHead key={key}>{labelFor(key)}</TableHead>
                                    ))}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {preview.rows.map((row) => (
                                    <TableRow key={row.row} className={cn(row.status === 'invalid' && 'bg-danger-soft/50')}>
                                        <TableCell className="px-6 font-mono text-xs">{row.row}</TableCell>
                                        <TableCell>
                                            <span
                                                className={cn(
                                                    'rounded-md px-1.5 py-0.5 text-[11px] font-semibold',
                                                    row.status === 'invalid' ? 'bg-destructive/10 text-destructive' : row.status === 'existing' ? 'bg-gold-soft text-gold-foreground' : 'bg-secondary text-secondary-foreground',
                                                )}
                                            >
                                                {row.status === 'invalid' ? 'Error' : row.status === 'existing' ? 'Sudah ada' : 'Baru'}
                                            </span>
                                        </TableCell>
                                        {shownColumns.map((key) => (
                                            <TableCell key={key} className="text-xs whitespace-nowrap">
                                                {row.values[key] ?? <span className="text-muted-foreground">—</span>}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </Card>
            )}

            {job.status === 'completed' && (
                <div className="mt-6 flex justify-end">
                    <Button variant="outline" onClick={() => router.visit(route('imports.index'))}>
                        Kembali ke impor data
                    </Button>
                </div>
            )}
        </>
    );
}
