import { router, useForm } from '@inertiajs/react';
import { Copy, DatabaseBackup, Download, FolderOpen, History, LoaderCircle, Trash2, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { type Column, DataTable } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatBytes, formatDateTime, fromNow } from '@/lib/format';

interface Backup {
    name: string;
    size: number;
    created_at: string;
}

interface Props {
    backups: Backup[];
    directory: string;
    database: string;
    keepFiles: number;
    schedule: string;
    canRestore: boolean;
}

export default function Backups({ backups, directory, database, keepFiles, schedule, canRestore }: Props) {
    const [processing, setProcessing] = useState(false);
    const [restoring, setRestoring] = useState<Backup | null>(null);
    const latest = backups[0];

    const create = () =>
        router.post(route('backups.store'), {}, { preserveScroll: true, onStart: () => setProcessing(true), onFinish: () => setProcessing(false) });

    const copyDirectory = async () => {
        try {
            await navigator.clipboard.writeText(directory);
            toast.success('Lokasi folder disalin.');
        } catch {
            toast.error('Tidak dapat menyalin otomatis. Salin lokasi secara manual.');
        }
    };

    const columns: Column<Backup>[] = [
        {
            key: 'name',
            header: 'Nama file',
            cell: (row) => (
                <div className="flex items-center gap-2.5">
                    <DatabaseBackup className="size-5 shrink-0 text-primary" />
                    <span className="font-mono text-[13px] font-semibold break-all">{row.name}</span>
                </div>
            ),
        },
        {
            key: 'created_at',
            header: 'Dibuat',
            className: 'whitespace-nowrap',
            cell: (row) => (
                <div>
                    <div className="text-[13px] tabular">{formatDateTime(row.created_at)}</div>
                    <div className="text-[11px] text-muted-foreground">{fromNow(row.created_at)}</div>
                </div>
            ),
        },
        { key: 'size', header: 'Ukuran', className: 'whitespace-nowrap', cell: (row) => <span className="text-[13px] tabular">{formatBytes(row.size)}</span> },
        {
            key: 'actions',
            header: '',
            className: 'text-right whitespace-nowrap',
            cell: (row) => (
                <div className="flex justify-end gap-1">
                    {canRestore && (
                        <Button variant="ghost" size="sm" onClick={() => setRestoring(row)}>
                            <History /> Pulihkan
                        </Button>
                    )}
                    <Button variant="ghost" size="sm" asChild>
                        <a href={route('backups.download', row.name)}>
                            <Download /> Unduh
                        </a>
                    </Button>
                    <ConfirmDialog
                        trigger={
                            <Button variant="ghost" size="sm" className="text-destructive" aria-label={`Hapus ${row.name}`}>
                                <Trash2 />
                            </Button>
                        }
                        title="Hapus file cadangan?"
                        description={
                            <>
                                <span className="font-mono">{row.name}</span> akan dihapus permanen dari server. Pastikan salinannya sudah disimpan di tempat lain.
                            </>
                        }
                        confirmLabel="Ya, hapus"
                        href={route('backups.destroy', row.name)}
                    />
                </div>
            ),
        },
    ];

    return (
        <>
            <PageHeader
                title="Cadangan Database"
                description="Buat salinan seluruh data SIMUTU secara berkala, lalu simpan salinannya di luar server ini."
                breadcrumbs={[{ label: 'Sistem' }, { label: 'Cadangan Database' }]}
                actions={
                    <Button onClick={create} disabled={processing}>
                        {processing ? <LoaderCircle className="animate-spin" /> : <DatabaseBackup />} Cadangkan sekarang
                    </Button>
                }
            />

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_400px]">
                <div className="flex flex-col gap-4">
                    <div className="flex items-start gap-3 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-amber-950">
                        <TriangleAlert className="mt-0.5 size-5 shrink-0 text-amber-600" />
                        <div className="text-sm leading-relaxed">
                            <div className="font-bold">Jangan mengubah nama file cadangan.</div>
                            Nama file memuat nama database serta tanggal dan jam pembuatan. Bila diubah, file tidak lagi dikenali sistem dan urutan cadangan
                            menjadi sulit dilacak. Salin file apa adanya.
                        </div>
                    </div>

                    <DataTable
                        columns={columns}
                        data={backups}
                        rowKey={(row) => row.name}
                        emptyTitle="Belum ada cadangan"
                        emptyDescription="Tekan “Cadangkan sekarang” untuk membuat cadangan pertama."
                    />
                </div>

                <div className="flex h-fit flex-col gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Lokasi file cadangan</CardTitle>
                            <CardDescription>Folder di server tempat semua file cadangan disimpan.</CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            <div className="flex items-start gap-2 rounded-xl bg-muted/50 p-3">
                                <FolderOpen className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                <code className="text-xs leading-relaxed break-all">{directory}</code>
                            </div>
                            <Button variant="outline" size="sm" onClick={copyDirectory}>
                                <Copy /> Salin lokasi folder
                            </Button>
                            <p className="text-xs leading-relaxed text-muted-foreground">
                                Salin file dari folder ini (atau gunakan tombol Unduh) ke flashdisk, komputer lain, atau penyimpanan awan. Cadangan yang hanya
                                berada di server ikut hilang bila server rusak.
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Ringkasan</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="flex flex-col gap-3 text-sm">
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">Database</dt>
                                    <dd className="font-mono font-semibold">{database}</dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">Cadangan terakhir</dt>
                                    <dd className="text-right font-semibold">{latest ? formatDateTime(latest.created_at) : 'Belum ada'}</dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">Jadwal otomatis</dt>
                                    <dd className="text-right font-semibold">{schedule}</dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">Disimpan</dt>
                                    <dd className="text-right font-semibold">{keepFiles} file terbaru</dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">Format nama</dt>
                                    <dd className="text-right font-mono text-xs">backup_{database}_TTTT-BB-HH_JJ-MM-DD.sql.gz</dd>
                                </div>
                            </dl>
                            <p className="mt-4 text-xs leading-relaxed text-muted-foreground">
                                Cadangan otomatis berjalan bila penjadwal server aktif. Jumlah file yang disimpan dapat diubah di Konfigurasi Sistem.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>

            {restoring && <RestoreDialog backup={restoring} database={database} onClose={() => setRestoring(null)} />}
        </>
    );
}

function RestoreDialog({ backup, database, onClose }: { backup: Backup; database: string; onClose: () => void }) {
    const form = useForm({ confirmation: '' });

    return (
        <FormDialog
            open
            onOpenChange={(open) => !open && !form.processing && onClose()}
            title="Pulihkan database"
            description={
                <>
                    Seluruh data saat ini akan diganti dengan isi cadangan <span className="font-mono font-semibold break-all">{backup.name}</span> (
                    {formatDateTime(backup.created_at)}).
                </>
            }
            submitLabel="Pulihkan database"
            processing={form.processing || form.data.confirmation !== database}
            onSubmit={() => form.post(route('backups.restore', backup.name), { preserveScroll: true, onSuccess: onClose })}
        >
            <div className="flex flex-col gap-4">
                <ul className="flex list-disc flex-col gap-1.5 pl-5 text-sm leading-relaxed text-muted-foreground">
                    <li>Data yang dimasukkan setelah cadangan ini dibuat akan hilang dari sistem.</li>
                    <li>Kondisi saat ini dicadangkan otomatis lebih dulu, sehingga pemulihan dapat dibatalkan.</li>
                    <li>Jangan menutup halaman sampai proses selesai. Pengguna lain sebaiknya tidak sedang mengisi data.</li>
                </ul>
                <FormField label={`Ketik “${database}” untuk melanjutkan`} error={form.errors.confirmation} required>
                    <Input
                        value={form.data.confirmation}
                        onChange={(e) => form.setData('confirmation', e.target.value)}
                        autoComplete="off"
                        spellCheck={false}
                        className="font-mono"
                    />
                </FormField>
            </div>
        </FormDialog>
    );
}
